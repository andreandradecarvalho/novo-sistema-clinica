<?php

declare(strict_types=1);

namespace App\Domain\Executions\Services;

use App\Domain\Executions\Models\ExamExecution;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use LogicException;

final class ExecutionRetryEvidence
{
    // Does not call parent/attempt snapshots: start readers also use this boundary.
    public static function validate(ExamExecution $execution, array $context): ExamExecution
    {
        if (! isset($context['retry']) && ! isset($context['repeat_of'])) {
            self::assertItemStatus($execution);
            if ($execution->status !== 'canceled' && ExamExecution::query()->where('exam_order_item_id', $execution->exam_order_item_id)->where('id', '!=', $execution->id)->where('status', '!=', 'canceled')->exists()) {
                throw new LogicException('Missing retry link between active or terminal attempts.');
            }

            return $execution;
        }
        $attempts = ExamExecution::query()->where('exam_order_item_id', $execution->exam_order_item_id)->orderBy('id')->get()->keyBy('id');
        $contexts = [];
        foreach ($attempts as $attempt) {
            $contexts[$attempt->id] = json_decode(Crypt::decryptString((string) $attempt->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
        }
        $head = $execution;
        while (isset($contexts[$head->id]['repeat_of'])) {
            $previous = $contexts[$head->id]['repeat_of']['previous_id'] ?? null;
            if (! is_int($previous) || $previous >= $head->id || ! isset($attempts[$previous])) {
                throw new LogicException('Invalid retry predecessor.');
            }
            $head = $attempts[$previous];
        }
        $seen = [];
        $current = $head;
        while (true) {
            self::identity($current, $contexts[$current->id]);
            $seen[] = (int) $current->id;
            $event = $contexts[$current->id]['retry'] ?? null;
            if ($event === null) {
                break;
            }
            $nextId = $event['next_id'] ?? null;
            $next = is_int($nextId) ? $attempts->get($nextId) : null;
            if ($next === null || $nextId <= $current->id || ($contexts[$nextId]['repeat_of'] ?? null) !== $event
                || ($event['previous_id'] ?? null) !== (int) $current->id || ($event['from_status'] ?? null) !== $current->status
                || ! in_array($current->status, ['refused', 'incomplete', 'canceled'], true)
                || ($current->status === 'canceled' && ! isset($contexts[$current->id]['repeat_of']))
                || (int) $next->visit_id !== (int) $current->visit_id || (int) $next->exam_type_id !== (int) $current->exam_type_id
                || $next->attempt_no <= $current->attempt_no || ! is_int($event['actor_id'] ?? null) || $event['actor_id'] !== (int) $next->created_by
                || ! is_string($event['reason'] ?? null) || trim($event['reason']) !== $event['reason'] || $event['reason'] === '' || mb_strlen($event['reason']) > 1000
                || ($event['key_hash'] ?? null) !== $next->creation_key_hash
                || ($event['request_hash'] ?? null) !== self::requestHash((int) $current->id, $event['reason'])
                || ($contexts[$nextId]['key_hash'] ?? null) !== $next->creation_key_hash
                || ($contexts[$nextId]['request_hash'] ?? null) !== ExamExecution::requestHash((int) $next->visit_id, (int) $next->exam_order_item_id)
                || ($contexts[$nextId]['tenant_id'] ?? null) !== (int) $next->tenant_id || ($contexts[$nextId]['visit_id'] ?? null) !== (int) $next->visit_id
                || ($contexts[$nextId]['item_id'] ?? null) !== (int) $next->exam_order_item_id || ($contexts[$nextId]['exam_type_id'] ?? null) !== (int) $next->exam_type_id
                || ($contexts[$nextId]['attempt_no'] ?? null) !== $next->attempt_no || ($contexts[$nextId]['actor_id'] ?? null) !== (int) $next->created_by
                || ($contexts[$nextId]['occurred_at'] ?? null) !== ($event['occurred_at'] ?? null) || ! is_string($event['occurred_at'] ?? null)) {
                throw new LogicException('Invalid retry succession evidence.');
            }
            $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $event['occurred_at']);
            if (! $at || $at->getOffset() !== 0 || $at->format('Y-m-d\TH:i:sP') !== $event['occurred_at'] || ! $at->equalTo($next->created_at)) {
                throw new LogicException('Invalid retry time.');
            }
            if ($current->status === 'canceled') {
                $cancellation = $contexts[$current->id]['cancellation'] ?? [];
                $reason = Crypt::decryptString((string) $current->getRawOriginal('reason_encrypted'));
                if ($current->started_at !== null || $current->finished_at !== null || $current->performed_by !== null
                    || ($cancellation['from_status'] ?? null) !== 'pending' || ($cancellation['request_hash'] ?? null) !== hash('sha256', $reason)
                    || ! is_string($cancellation['occurred_at'] ?? null) || $at->lessThan($cancellation['occurred_at'])) {
                    throw new LogicException('Invalid canceled retry predecessor.');
                }
            } else {
                ExecutionStartEvidence::historical($current, $contexts[$current->id]);
                if ($at->lessThan($current->finished_at)) {
                    throw new LogicException('Retry predates exceptional outcome.');
                }
            }
            $current = $next;
        }
        foreach ($attempts as $attempt) {
            if (! in_array((int) $attempt->id, $seen, true) && ($attempt->status !== 'canceled' || isset($contexts[$attempt->id]['retry']) || isset($contexts[$attempt->id]['repeat_of']))) {
                throw new LogicException('Unlinked execution competes with retry chain.');
            }
        }
        self::assertItemStatus($current, true);
        if ($execution->visit?->status === 'completed' && $current->status !== 'completed') {
            throw new LogicException('Closed visit has an unresolved retry chain.');
        }

        return $current;
    }

    public static function completedSuccessor(ExamExecution $execution, array $context): ExamExecution
    {
        $current = self::validate($execution, $context);
        if ($current->status !== 'completed' || $current->finished_at === null || (int) $current->id === (int) $execution->id
            || (int) $current->visit_id !== (int) $execution->visit_id || (int) $current->exam_order_item_id !== (int) $execution->exam_order_item_id) {
            throw new LogicException('Retry has no completed successor in this visit.');
        }
        $source = json_decode(Crypt::decryptString((string) $current->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
        ExecutionStartEvidence::historical($current, $source);

        return $current;
    }

    public static function requestHash(int $previousId, string $reason): string
    {
        return hash('sha256', json_encode(['previous_id' => $previousId, 'reason' => $reason], JSON_THROW_ON_ERROR));
    }

    private static function identity(ExamExecution $execution, array $context): void
    {
        if (($context['schema_version'] ?? null) !== 1 || ($context['tenant_id'] ?? null) !== (int) $execution->tenant_id
            || ($context['visit_id'] ?? null) !== (int) $execution->visit_id || ($context['item_id'] ?? null) !== (int) $execution->exam_order_item_id
            || ($context['exam_type_id'] ?? null) !== (int) $execution->exam_type_id || ($context['attempt_no'] ?? null) !== $execution->attempt_no
            || ($context['actor_id'] ?? null) !== (int) $execution->created_by || ($context['key_hash'] ?? null) !== $execution->creation_key_hash
            || ($context['request_hash'] ?? null) !== $execution->creation_request_hash
            || $execution->creation_request_hash !== ExamExecution::requestHash((int) $execution->visit_id, (int) $execution->exam_order_item_id)
            || $execution->source !== 'order' || ! is_string($context['occurred_at'] ?? null)) {
            throw new LogicException('Retry node identity differs.');
        }
        $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $context['occurred_at']);
        if (! $at || $at->getOffset() !== 0 || $at->format('Y-m-d\TH:i:sP') !== $context['occurred_at'] || ! $at->equalTo($execution->created_at)) {
            throw new LogicException('Retry node time differs.');
        }
    }

    private static function assertItemStatus(ExamExecution $execution, bool $chain = false): void
    {
        // Ordinary canceled preparations may precede the first execution without being retries.
        if ($execution->status === 'canceled' && ! $chain) {
            return;
        }
        $expected = in_array($execution->status, ['pending', 'canceled'], true) ? 'authorized' : $execution->status;
        if ($execution->item?->status !== $expected) {
            throw new LogicException('Current execution differs from item status.');
        }
    }
}
