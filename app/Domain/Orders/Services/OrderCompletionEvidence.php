<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Services\ExecutionCompletionEvidence;
use App\Domain\Orders\Models\ExamOrder;
use Carbon\CarbonImmutable;
use LogicException;

final class OrderCompletionEvidence
{
    public static function validate(ExamOrder $order, mixed $event): CarbonImmutable
    {
        if (! is_array($event) || ! is_array($event['item_ids'] ?? null) || ! is_int($event['execution_id'] ?? null)) {
            throw new LogicException('Missing order completion evidence.');
        }
        $items = $order->items()->orderBy('id')->get(['id', 'tenant_id', 'exam_order_id', 'status', 'quantity']);
        if ($items->isEmpty() || $items->pluck('id')->map(fn ($id): int => (int) $id)->all() !== $event['item_ids']) {
            throw new LogicException('Order completion item set differs.');
        }
        $at = null;
        $latest = null;
        foreach ($items as $item) {
            if ($item->status !== 'completed' || $item->quantity !== 1) {
                throw new LogicException('Order contains unfinished items.');
            }
            $execution = ExamExecution::query()->where('exam_order_item_id', $item->id)->where('status', 'completed')->sole();
            // Context validation is deliberately non-recursive: no parent attendance/context call here.
            $context = $execution->contextSnapshot();
            $completedAt = ExecutionCompletionEvidence::validate($execution, $context);
            if ($latest === null || $completedAt->greaterThan($latest)) {
                $latest = $completedAt;
            }
            if ((int) $execution->id === $event['execution_id']) {
                $expected = $event;
                unset($expected['item_ids']);
                if ($expected !== $context['completion']) {
                    throw new LogicException('Order completion differs from the last execution.');
                }
                $at = $completedAt;
            }
        }
        if ($at === null || $order->authorized_at === null || $at->lessThan($order->authorized_at)) {
            throw new LogicException('Invalid order completion source.');
        }
        if ($at->lessThan($latest)) {
            throw new LogicException('Order completion predates an item.');
        }

        return $at;
    }
}
