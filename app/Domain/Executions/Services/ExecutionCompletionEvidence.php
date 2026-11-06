<?php

declare(strict_types=1);

namespace App\Domain\Executions\Services;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use Carbon\CarbonImmutable;
use LogicException;

final class ExecutionCompletionEvidence
{
    public static function validate(ExamExecution $execution, array $context): CarbonImmutable
    {
        $event = $context['completion'] ?? null;
        $start = $context['start'] ?? null;
        if (! is_array($event) || ! is_array($start)) {
            throw new LogicException('Missing execution completion evidence.');
        }

        return self::validateEvent($execution, $event, $start, 'completed');
    }

    public static function validateEvent(ExamExecution $execution, array $event, array $start, string $status): CarbonImmutable
    {
        if (($event['from_status'] ?? null) !== 'in_progress'
            || ($event['execution_id'] ?? null) !== (int) $execution->id || ($event['professional_id'] ?? null) !== (int) $execution->performed_by
            || ($event['actor_id'] ?? null) !== ($start['actor_id'] ?? null) || $event['professional_id'] !== ($start['professional_id'] ?? null)
            || ! is_int($event['authorization_id'] ?? null) || $event['authorization_id'] < 1
            || ! is_string($event['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $event['key_hash'])
            || ! is_string($event['occurred_at'] ?? null) || $execution->status !== $status || $execution->finished_at === null || $execution->started_at === null) {
            throw new LogicException('Invalid execution finish evidence.');
        }
        $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $event['occurred_at']);
        if (! $at || $at->getOffset() !== 0 || $at->format('Y-m-d\TH:i:sP') !== $event['occurred_at']
            || ! $at->equalTo($execution->finished_at) || $at->lessThan($execution->started_at)) {
            throw new LogicException('Invalid execution completion time.');
        }
        $grant = ProfessionalExamAuthorization::query()->whereKey($event['authorization_id'])->where('professional_id', $execution->performed_by)->where('exam_type_id', $execution->exam_type_id)->first();
        if ($grant === null || $grant->granted_at->greaterThan($at) || ($grant->revoked_at !== null && $grant->revoked_at->lessThan($at))) {
            throw new LogicException('Completion differs from its historical authorization.');
        }

        return $at;
    }
}
