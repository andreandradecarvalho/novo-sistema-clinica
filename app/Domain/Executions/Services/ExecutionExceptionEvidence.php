<?php

declare(strict_types=1);

namespace App\Domain\Executions\Services;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Orders\Services\ExceptionFinancialPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use LogicException;

final class ExecutionExceptionEvidence
{
    public static function validate(ExamExecution $execution, array $context): CarbonImmutable
    {
        $event = $context['exception'] ?? null;
        $start = $context['start'] ?? null;
        if (! is_array($event) || ! is_array($start) || ! in_array($execution->status, ['refused', 'incomplete'], true)
            || ($event['outcome'] ?? null) !== $execution->status || ! is_string($event['request_hash'] ?? null)
            || ! is_array($event['financial_policy'] ?? null) || ! is_int($event['financial_policy']['revision'] ?? null) || $event['financial_policy']['revision'] < 1) {
            throw new LogicException('Invalid execution exception evidence.');
        }
        $at = ExecutionCompletionEvidence::validateEvent($execution, $event, $start, $execution->status);
        $reason = Crypt::decryptString((string) $execution->getRawOriginal('reason_encrypted'));
        if ($reason === '' || trim($reason) !== $reason || mb_strlen($reason) > 1000
            || ! hash_equals(self::requestHash($execution->status, $reason), $event['request_hash'])) {
            throw new LogicException('Invalid execution exception reason.');
        }
        $policy = app(ExceptionFinancialPolicy::class)->current();
        $source = $policy['history'][$event['financial_policy']['revision'] - 1] ?? null;
        if ($source !== $event['financial_policy'] || $at->lessThan($source['occurred_at'])) {
            throw new LogicException('Execution exception policy differs from its source.');
        }

        return $at;
    }

    public static function requestHash(string $outcome, string $reason): string
    {
        return hash('sha256', json_encode(['outcome' => $outcome, 'reason' => $reason], JSON_THROW_ON_ERROR));
    }
}
