<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Orders\Models\ExamOrder;
use Illuminate\Validation\ValidationException;

final class AuthorizedOrderAmount
{
    public function calculate(ExamOrder $order): string
    {
        return $this->amount($order, true);
    }

    // A proposal is never an authorization to create or settle a receivable.
    public function proposeReviewAmount(ExamOrder $order): string
    {
        if ($order->status !== 'completed' || $order->items()->where('status', '!=', 'completed')->exists()) {
            $this->refuse();
        }

        return $this->amount($order, false);
    }

    private function amount(ExamOrder $order, bool $requireReview): string
    {
        $context = $order->contextSnapshot();
        $pricing = $order->pricing_snapshot;
        if (! in_array($order->status, ['authorized', 'in_progress', 'completed'], true) || $order->authorized_at === null || ! isset($context['authorization']) || isset($context['cancellation'])
            || ! is_array($pricing) || ($pricing['schema_version'] ?? null) !== 1 || ($pricing['source'] ?? null) !== 'catalog'
            || ($pricing['payer_type'] ?? null) !== $order->payer_type || ($context['authorization']['from_status'] ?? null) !== 'draft') {
            $this->refuse();
        }
        $items = $order->items()->select(['id', 'tenant_id', 'exam_order_id', 'status', 'quantity', 'unit_price', 'discount_amount'])->orderBy('id')->lockForUpdate()->get();
        if ($items->isEmpty()) {
            $this->refuse();
        }
        // A repeated item is never charged twice or released without a separate explicit financial review.
        $hasRetry = false;
        foreach (ExamExecution::query()->whereIn('exam_order_item_id', $items->pluck('id'))->get() as $attempt) {
            $attemptContext = $attempt->preparationSnapshot();
            if (isset($attemptContext['repeat_of']) || isset($attemptContext['retry'])) {
                $hasRetry = true;
            }
        }
        $exceptionModes = [];
        foreach ($items as $item) {
            if (in_array($item->status, ['refused', 'incomplete'], true)) {
                $attempts = ExamExecution::query()->where('exam_order_item_id', $item->id)->where('status', $item->status)->get();
                if ($attempts->count() !== 1) {
                    $this->refuse();
                }
                $evidence = $attempts->sole()->preparationSnapshot();
                $mode = $evidence['exception']['financial_policy']['mode'];
                if ($mode === 'review_required') {
                    $this->refuse();
                }
                $exceptionModes[$item->id] = $mode;
            }
        }
        // A completed-only exception requires the remaining items to reach a terminal outcome before charging.
        if (in_array('charge_completed', $exceptionModes, true) && $items->contains(fn ($item): bool => ! in_array($item->status, ['completed', 'refused', 'incomplete'], true))) {
            $this->refuse();
        }
        $total = 0;
        foreach ($items as $item) {
            // Full historical catalog price remains payable after start; no partial billing or discounts.
            if (! in_array($item->status, ['authorized', 'in_progress', 'completed', 'refused', 'incomplete'], true) || $item->quantity !== 1 || $item->discount_amount !== '0.00') {
                $this->refuse();
            }
            if (in_array($item->status, ['in_progress', 'completed'], true)) {
                $executions = ExamExecution::query()->where('exam_order_item_id', $item->id)->where('status', $item->status)->get();
                if (! in_array($order->status, ['in_progress', 'completed'], true) || $executions->count() !== 1) {
                    $this->refuse();
                }
                $executions->sole()->preparationSnapshot();
            }
            if (($exceptionModes[$item->id] ?? null) !== 'charge_completed') {
                $total += $this->cents($item->unit_price);
            }
            if ($total > 999999999999) {
                $this->refuse();
            }
        }

        $amount = intdiv($total, 100).'.'.str_pad((string) ($total % 100), 2, '0', STR_PAD_LEFT);
        if ($hasRetry && $requireReview) {
            app(ClinicalFinancialReview::class)->assertApproved($order, $amount);
        }

        return $amount;
    }

    private function cents(string $amount): int
    {
        if (! preg_match('/^([0-9]{1,10})\.([0-9]{2})$/D', $amount, $parts)) {
            $this->refuse();
        }

        return (int) $parts[1] * 100 + (int) $parts[2];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['receivable' => 'O pedido exige revisão financeira ou não permite este registro. Reabra a consulta e solicite revisão autorizada.']);
    }
}
