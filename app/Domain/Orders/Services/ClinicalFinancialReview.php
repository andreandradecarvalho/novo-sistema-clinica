<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class ClinicalFinancialReview
{
    public function inspect(ExamOrder $order): array
    {
        if ((int) $order->tenant_id !== app(TenantContext::class)->id()) {
            throw new LogicException('Cross-tenant financial review is forbidden.');
        }
        try {
            return $this->snapshot($order);
        } catch (Throwable) {
            $this->refuse();
        }
    }

    private function snapshot(ExamOrder $order): array
    {
        $context = $order->contextSnapshot();
        $history = $context['financial_reviews'] ?? [];
        if (! is_array($history) || ! array_is_list($history)) {
            $this->refuse();
        }
        $keys = [];
        $previousAt = null;
        foreach ($history as $index => $event) {
            if (! is_array($event) || ($event['revision'] ?? null) !== $index + 1 || ($event['order_id'] ?? null) !== (int) $order->id
                || ! is_int($event['actor_id'] ?? null) || $event['actor_id'] < 1 || ! is_string($event['reason'] ?? null)
                || trim($event['reason']) !== $event['reason'] || $event['reason'] === '' || mb_strlen($event['reason']) > 1000
                || ! is_string($event['amount'] ?? null) || ! preg_match('/\A[0-9]{1,10}\.[0-9]{2}\z/', $event['amount'])
                || ! is_array($event['receipts'] ?? null) || ! array_is_list($event['receipts']) || count($event['receipts']) > 1
                || ! is_string($event['occurred_at'] ?? null)) {
                $this->refuse();
            }
            foreach (['key_hash', 'request_hash', 'clinical_fingerprint', 'expected_fingerprint'] as $field) {
                if (! is_string($event[$field] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $event[$field])) {
                    $this->refuse();
                }
            }
            foreach ($event['receipts'] as $receipt) {
                if (! is_array($receipt) || ! is_int($receipt['id'] ?? null) || $receipt['id'] < 1
                    || ! in_array($receipt['status'] ?? null, ['open', 'paid'], true) || ($receipt['amount'] ?? null) !== $event['amount']
                    || ! is_string($receipt['record_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $receipt['record_hash'])
                    || ! is_string($receipt['transition_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $receipt['transition_hash'])) {
                    $this->refuse();
                }
            }
            $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $event['occurred_at']);
            if (! $at || $at->getOffset() !== 0 || $at->format('Y-m-d\TH:i:sP') !== $event['occurred_at']
                || $at->greaterThan(now('UTC')) || $order->updated_at === null || $at->greaterThan($order->updated_at)
                || ($previousAt !== null && $at->lessThan($previousAt)) || in_array($event['key_hash'], $keys, true)
                || $event['request_hash'] !== self::requestHash($index, $event['expected_fingerprint'], $event['reason'])
                || $event['expected_fingerprint'] !== self::fingerprint($event['clinical_fingerprint'], $event['receipts'])) {
                $this->refuse();
            }
            $keys[] = $event['key_hash'];
            $previousAt = $at;
        }
        $items = $order->items()->orderBy('id')->get();
        $attempts = ExamExecution::query()->whereIn('exam_order_item_id', $items->pluck('id'))->orderBy('id')->get();
        $hasRetry = false;
        $executionProofs = [];
        foreach ($attempts as $attempt) {
            $proof = $attempt->preparationSnapshot();
            $hasRetry = $hasRetry || isset($proof['retry']) || isset($proof['repeat_of']);
            $executionProofs[] = $attempt->getRawOriginal();
        }
        if ($history !== [] && ! $hasRetry) {
            $this->refuse();
        }
        $clinical = hash('sha256', json_encode(['tenant_id' => (int) $order->tenant_id, 'order_id' => (int) $order->id,
            'patient_id' => (int) $order->patient_id, 'status' => $order->status, 'payer_type' => $order->payer_type, 'company_id' => $order->company_id,
            'authorized_at' => $order->getRawOriginal('authorized_at'), 'pricing' => $order->pricing_snapshot,
            'authorization' => $context['authorization'] ?? null, 'completion' => $context['completion'] ?? null,
            'items' => $items->map(fn ($item) => $item->getRawOriginal())->all(), 'attempts' => $executionProofs], JSON_THROW_ON_ERROR));
        $receipts = [];
        $records = $order->receivables()->orderBy('id')->get();
        foreach ($records as $receipt) {
            $receipt->transitionSnapshot();
            $receipts[] = ['id' => (int) $receipt->id, 'status' => $receipt->status, 'amount' => $receipt->amount, 'record_hash' => $receipt->recordFingerprint(),
                'transition_hash' => hash('sha256', (string) $receipt->getRawOriginal('transition_encrypted'))];
        }
        $eligible = $hasRetry && $order->status === 'completed' && $items->isNotEmpty() && $items->every(fn ($item): bool => $item->status === 'completed');
        $amount = $eligible ? app(AuthorizedOrderAmount::class)->proposeReviewAmount($order) : null;
        $eligible = $eligible && $records->every(fn ($receipt): bool => $receipt->idempotency_key === 'full-order:v1:'.$order->id && $receipt->payer_type === $order->payer_type && $receipt->company_id === $order->company_id && $receipt->external_ref === null) && count($receipts) <= 1 && collect($receipts)->every(fn ($receipt): bool => in_array($receipt['status'], ['open', 'paid'], true) && $receipt['amount'] === $amount);
        $latest = $history === [] ? null : array_last($history);
        $approved = $eligible && $latest !== null && $latest['clinical_fingerprint'] === $clinical && $latest['amount'] === $amount && ! CarbonImmutable::parse($latest['occurred_at'])->lessThan($context['completion']['occurred_at'])
            && $this->compatibleReceipts($order, $latest, $receipts);

        return ['required' => $hasRetry, 'eligible' => $eligible, 'approved' => $approved, 'revision' => count($history), 'history' => $history,
            'fingerprint' => self::fingerprint($clinical, $receipts), 'clinical_fingerprint' => $clinical, 'receipts' => $receipts, 'amount' => $amount];
    }

    public function assertApproved(ExamOrder $order, string $amount): void
    {
        $review = $this->inspect($order);
        if (! $review['approved'] || $review['amount'] !== $amount) {
            $this->refuse();
        }
    }

    public static function requestHash(int $revision, string $fingerprint, string $reason): string
    {
        return hash('sha256', json_encode(compact('revision', 'fingerprint', 'reason'), JSON_THROW_ON_ERROR));
    }

    private static function fingerprint(string $clinical, array $receipts): string
    {
        return hash('sha256', json_encode(compact('clinical', 'receipts'), JSON_THROW_ON_ERROR));
    }

    private function compatibleReceipts(ExamOrder $order, array $event, array $receipts): bool
    {
        if ($event['receipts'] === $receipts) {
            return true;
        }
        if (count($receipts) !== 1) {
            return false;
        }
        $current = $receipts[0];
        $previous = $event['receipts'][0] ?? null;
        $receipt = $order->receivables()->whereKey($current['id'])->firstOrFail();
        if ($previous === null) {
            if ($receipt->idempotency_key !== 'full-order:v1:'.$order->id || $receipt->created_at->lessThan($event['occurred_at'])) {
                return false;
            }
        } elseif ($previous['id'] !== $current['id'] || $previous['record_hash'] !== $current['record_hash'] || $previous['status'] !== 'open') {
            return false;
        }
        $transition = $receipt->transitionSnapshot();

        return $current['status'] === 'open' || ($current['status'] === 'paid' && $transition !== null && ! CarbonImmutable::parse($transition['occurred_at'])->lessThan($event['occurred_at']));
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['financial_review' => 'A revisão financeira está pendente ou indisponível. Reabra a consulta e confira as tentativas e a conta.']);
    }
}
