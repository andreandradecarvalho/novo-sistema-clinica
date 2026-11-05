<?php

declare(strict_types=1);

namespace App\Domain\Executions\Services;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Professionals\Models\Professional;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use LogicException;

final class ExecutionStartEvidence
{
    public static function validate(mixed $event, ?int $visitId = null, ?int $orderId = null): CarbonImmutable
    {
        if (! is_array($event)) {
            throw new LogicException('Missing execution start evidence.');
        }
        foreach (['actor_id', 'professional_id', 'authorization_id', 'execution_id'] as $key) {
            if (! is_int($event[$key] ?? null) || $event[$key] < 1) {
                throw new LogicException('Invalid execution start identity.');
            }
        }
        if (! is_string($event['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $event['key_hash']) || ! is_string($event['occurred_at'] ?? null)) {
            throw new LogicException('Invalid execution start metadata.');
        }
        $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $event['occurred_at']);
        if (! $at || $at->format('Y-m-d\TH:i:sP') !== $event['occurred_at'] || $at->getOffset() !== 0) {
            throw new LogicException('Invalid execution start time.');
        }
        $execution = ExamExecution::query()->whereKey($event['execution_id'])->first();
        $professional = Professional::query()->whereKey($event['professional_id'])->where('user_id', $event['actor_id'])->first();
        $grant = ProfessionalExamAuthorization::query()->whereKey($event['authorization_id'])->where('professional_id', $event['professional_id'])->first();
        if ($execution === null || $professional === null || $grant === null || (int) $grant->exam_type_id !== (int) $execution->exam_type_id
            || $grant->granted_at->greaterThan($at) || ($grant->revoked_at !== null && $grant->revoked_at->lessThan($at))
            || $execution->item?->status !== $execution->status || $execution->visit?->status !== 'in_progress' || ! in_array($execution->item->order?->status, ['in_progress', 'completed'], true)
            || ! in_array($execution->status, ['in_progress', 'completed'], true) || (int) $execution->performed_by !== $event['professional_id'] || $execution->started_at === null
            || ! $at->equalTo($execution->started_at) || ($visitId !== null && (int) $execution->visit_id !== $visitId)
            || ($orderId !== null && (int) $execution->item->exam_order_id !== $orderId)) {
            throw new LogicException('Execution start differs from its historical links.');
        }
        if ((int) $execution->visit->exam_order_id !== (int) $execution->item->exam_order_id || (int) $execution->visit->patient_id !== (int) $execution->item->order->patient_id) {
            throw new LogicException('Execution start parent links differ.');
        }
        $context = json_decode(Crypt::decryptString((string) $execution->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
        if (($context['start'] ?? null) !== $event) {
            throw new LogicException('Execution start event differs from its source.');
        }

        if ($execution->status === 'completed') {
            ExecutionCompletionEvidence::validate($execution, $context);
        } elseif (isset($context['completion']) || $execution->finished_at !== null) {
            throw new LogicException('Unexpected execution completion.');
        }

        return $at;
    }
}
