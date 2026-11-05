<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Orders\Enums\ReceivableTransition;
use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Orders\Services\AuthorizedOrderAmount;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class TransitionClinicalReceivable
{
    public function execute(Patient $patient, ExamOrder $order, ClinicalReceivable $receivable, ReceivableTransition $transition, string $reason, string $idempotencyKey, string $expectedStatus): ClinicalReceivable
    {
        $reason = trim($reason);
        Validator::make(['reason' => $reason, 'idempotency_key' => $idempotencyKey, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $order, $receivable, $transition, $reason, $idempotencyKey): ClinicalReceivable {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            $order = $patient->orders()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $receivable = $order->receivables()->whereKey($receivable->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($transition->value, $receivable);
            $context = $order->contextSnapshot();
            $previous = $receivable->transitionSnapshot();
            $keyHash = hash('sha256', 'receivable-transition:v1|'.$actor->id.'|'.strtolower($idempotencyKey));
            $requestHash = hash('sha256', json_encode(['operation' => $transition->value, 'reason' => $reason], JSON_THROW_ON_ERROR));
            if ($previous !== null) {
                if (! hash_equals($previous['key_hash'], $keyHash) || ! hash_equals($previous['request_hash'], $requestHash) || $previous['operation'] !== $transition->value) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('receivables', 'clinical_receivable_transition_replayed', $receivable, ClinicalReceivable::class);

                return $receivable;
            }
            if (! in_array($order->status, ['authorized', 'in_progress', 'completed'], true) || $order->authorized_at === null || ! isset($context['authorization']) || isset($context['cancellation'])
                || $receivable->payer_type !== $order->payer_type || $receivable->company_id !== $order->company_id || $receivable->external_ref !== null) {
                $this->refuse();
            }
            if ($transition === ReceivableTransition::Cancel && Visit::query()->where('exam_order_id', $order->id)->lockForUpdate()->first(['id']) !== null) {
                $this->refuse();
            }
            if (app(AuthorizedOrderAmount::class)->calculate($order) !== $receivable->amount) {
                $this->refuse();
            }
            $now = now('UTC')->startOfSecond();
            $evidence = ['schema_version' => 1, 'tenant_id' => (int) $receivable->tenant_id, 'receivable_id' => (int) $receivable->id,
                'exam_order_id' => (int) $order->id, 'record_hash' => $receivable->recordFingerprint(), 'operation' => $transition->value, 'actor_id' => (int) $actor->id,
                'occurred_at' => $now->toIso8601String(), 'reason' => $reason, 'key_hash' => $keyHash, 'request_hash' => $requestHash];
            // Deliberate scoped write: only financial state/evidence/date, never price, payer or ownership.
            $changed = DB::table('clinical_receivables')->where('tenant_id', $receivable->tenant_id)->where('id', $receivable->id)->where('exam_order_id', $order->id)
                ->where('status', 'open')->whereNull('settled_at')->whereNull('transition_encrypted')
                ->update(['status' => $transition->status(), 'settled_at' => $transition === ReceivableTransition::Settle ? $now : null,
                    'transition_encrypted' => Crypt::encryptString(json_encode($evidence, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                $this->refuse();
            }
            $receivable->refresh();
            app(RegistryAudit::class)->record('receivables', 'clinical_receivable_'.$transition->status(), $receivable, ClinicalReceivable::class);

            return $receivable;
        }, 3);
    }

    public static function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid'], 'expected_status' => ['required', 'in:open']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['receivable' => 'O recebível mudou ou não permite esta operação. Reabra a consulta.']);
    }
}
