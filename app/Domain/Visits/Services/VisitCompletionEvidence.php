<?php

declare(strict_types=1);

namespace App\Domain\Visits\Services;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Services\ExecutionRetryEvidence;
use App\Domain\Visits\Models\Visit;
use Carbon\CarbonImmutable;
use LogicException;

final class VisitCompletionEvidence
{
    // Metadata-only validation is safe from execution/order readers: never recurse into parent snapshots.
    public static function metadata(Visit $visit, mixed $event): CarbonImmutable
    {
        if (! is_array($event) || ($event['from_status'] ?? null) !== 'in_progress' || ($event['visit_id'] ?? null) !== (int) $visit->id
            || ! is_int($event['actor_id'] ?? null) || $event['actor_id'] < 1 || ! is_string($event['occurred_at'] ?? null)
            || ! is_string($event['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $event['key_hash'])
            || ! is_array($event['execution_ids'] ?? null) || $event['execution_ids'] === []
            || $visit->status !== 'completed' || $visit->completed_at === null) {
            throw new LogicException('Invalid visit completion evidence.');
        }
        $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $event['occurred_at']);
        if (! $at || $at->format('Y-m-d\TH:i:sP') !== $event['occurred_at'] || $at->getOffset() !== 0
            || ! $at->equalTo($visit->completed_at) || $at->lessThan($visit->checked_in_at)) {
            throw new LogicException('Invalid visit completion time.');
        }

        return $at;
    }

    public static function validate(Visit $visit, mixed $event): void
    {
        $at = self::metadata($visit, $event);
        $executions = $visit->executions()->orderBy('id')->get();
        if ($event['execution_ids'] !== $executions->pluck('id')->map(fn ($id): int => (int) $id)->all()
            || ! $executions->contains('status', 'completed')) {
            throw new LogicException('Visit completion attempts differ.');
        }
        $resolved = [];
        foreach ($executions as $execution) {
            $context = $execution->contextSnapshot();
            $item = $execution->item;
            $terminal = self::terminal($execution, $context);
            if ($terminal['successor_id'] !== null) {
                $resolved[] = ['execution_id' => (int) $execution->id, 'completed_execution_id' => $terminal['successor_id']];
            }
            if ($item === null
                || (int) $item->exam_order_id !== (int) $visit->exam_order_id || (int) $item->exam_type_id !== (int) $execution->exam_type_id
                || $at->lessThan($terminal['occurred_at'])) {
                throw new LogicException('Unfinished or inconsistent visit attempt.');
            }
            if (isset($context['room_assignment']) && ($context['room_assignment']['unit_id'] !== (int) $visit->unit_id
                || (int) $execution->room?->unit_id !== (int) $visit->unit_id)) {
                throw new LogicException('Visit attempt room differs.');
            }
        }
        if (($event['resolved_executions'] ?? []) !== $resolved) {
            throw new LogicException('Visit retry resolutions differ.');
        }
    }

    /** @return array{occurred_at: CarbonImmutable, successor_id: int|null} */
    public static function terminal(ExamExecution $execution, array $context): array
    {
        if ($execution->status === 'completed') {
            return ['occurred_at' => CarbonImmutable::parse($context['completion']['occurred_at']), 'successor_id' => null];
        }
        if ($execution->status === 'canceled' && ! isset($context['retry']) && ! isset($context['repeat_of'])) {
            return ['occurred_at' => CarbonImmutable::parse($context['cancellation']['occurred_at']), 'successor_id' => null];
        }
        if (! in_array($execution->status, ['refused', 'incomplete', 'canceled'], true)) {
            throw new LogicException('Unfinished visit attempt.');
        }
        $successor = ExecutionRetryEvidence::completedSuccessor($execution, $context);

        return ['occurred_at' => CarbonImmutable::parse($successor->finished_at), 'successor_id' => (int) $successor->id];
    }
}
