<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Orders\Services\ClinicalFinancialReview;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReviewClinicalOrder
{
    public function execute(Patient $patient, ExamOrder $order, int $revision, string $fingerprint, string $reason, string $key): array
    {
        $reason = trim($reason);
        Validator::make(['expected_revision' => $revision, 'expected_fingerprint' => $fingerprint, 'reason' => $reason, 'idempotency_key' => $key], self::rules())->validate();

        return DB::transaction(function () use ($patient, $order, $revision, $fingerprint, $reason, $key): array {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            $order = $patient->orders()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('review', [ClinicalReceivable::class, $order]);
            $order->items()->lockForUpdate()->get(['id']);
            $order->receivables()->lockForUpdate()->get(['id']);
            $reader = app(ClinicalFinancialReview::class);
            $snapshot = $reader->inspect($order);
            $keyHash = hash('sha256', 'clinical-financial-review:v1|'.$order->id.'|'.$actor->id.'|'.strtolower($key));
            $requestHash = ClinicalFinancialReview::requestHash($revision, $fingerprint, $reason);
            foreach ($snapshot['history'] as $event) {
                if (hash_equals($event['key_hash'], $keyHash)) {
                    if (! hash_equals($event['request_hash'], $requestHash)) {
                        $this->refuse();
                    }
                    app(RegistryAudit::class)->record('receivables', 'clinical_financial_review_replayed', $order, ExamOrder::class);

                    return $snapshot;
                }
            }
            if (! $snapshot['eligible'] || $snapshot['revision'] !== $revision || ! hash_equals($snapshot['fingerprint'], $fingerprint)) {
                $this->refuse();
            }
            $now = now('UTC')->startOfSecond();
            $context = $order->contextSnapshot();
            if ($now->lessThan($context['completion']['occurred_at']) || ($snapshot['history'] !== [] && $now->lessThan(array_last($snapshot['history'])['occurred_at']))) {
                $this->refuse();
            }
            foreach ($order->receivables()->get() as $receipt) {
                $transition = $receipt->transitionSnapshot();
                if ($now->lessThan($receipt->created_at) || ($transition !== null && $now->lessThan($transition['occurred_at']))) {
                    $this->refuse();
                }
            }
            $context['financial_reviews'] = [...$snapshot['history'], ['revision' => $revision + 1, 'order_id' => (int) $order->id, 'actor_id' => (int) $actor->id,
                'occurred_at' => $now->toIso8601String(), 'reason' => $reason, 'key_hash' => $keyHash, 'request_hash' => $requestHash,
                'expected_fingerprint' => $fingerprint, 'clinical_fingerprint' => $snapshot['clinical_fingerprint'], 'receipts' => $snapshot['receipts'], 'amount' => $snapshot['amount']]];
            $changed = DB::table('exam_orders')->where('tenant_id', $order->tenant_id)->where('patient_id', $patient->id)->where('id', $order->id)->where('status', 'completed')
                ->update(['context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                $this->refuse();
            }
            $order->refresh();
            app(RegistryAudit::class)->record('receivables', 'clinical_financial_reviewed', $order, ExamOrder::class);

            return $reader->inspect($order);
        }, 3);
    }

    public static function rules(): array
    {
        return ['expected_revision' => ['required', 'integer', 'min:0', 'max:2147483646'], 'expected_fingerprint' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
            'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['financial_review' => 'As tentativas ou a conta mudaram, ou não permitem esta revisão. Reabra a consulta.']);
    }
}
