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
        $total = 0;
        foreach ($items as $item) {
            // Full historical catalog price remains payable after start; no partial billing or discounts.
            if (! in_array($item->status, ['authorized', 'in_progress', 'completed'], true) || $item->quantity !== 1 || $item->discount_amount !== '0.00') {
                $this->refuse();
            }
            if (in_array($item->status, ['in_progress', 'completed'], true)) {
                $executions = ExamExecution::query()->where('exam_order_item_id', $item->id)->where('status', $item->status)->get();
                if (! in_array($order->status, ['in_progress', 'completed'], true) || $executions->count() !== 1) {
                    $this->refuse();
                }
                $executions->sole()->preparationSnapshot();
            }
            $total += $this->cents($item->unit_price);
            if ($total > 999999999999) {
                $this->refuse();
            }
        }

        return intdiv($total, 100).'.'.str_pad((string) ($total % 100), 2, '0', STR_PAD_LEFT);
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
        throw ValidationException::withMessages(['receivable' => 'O pedido ou recebível não permite este registro integral. Reabra a consulta e solicite revisão autorizada.']);
    }
}
