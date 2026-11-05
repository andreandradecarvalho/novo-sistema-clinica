<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Services\ExecutionWorkflow;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PrepareExamExecution
{
    public function execute(Patient $patient, Visit $visit, int $itemId, string $key, string $expectedStatus): ExamExecution
    {
        Validator::make(['exam_order_item_id' => $itemId, 'idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();
        try {
            return DB::transaction(function () use ($patient, $visit, $itemId, $key, $expectedStatus): ExamExecution {
                $workflow = app(ExecutionWorkflow::class);
                [$visit, $actor] = $workflow->lock($patient, $visit);
                Gate::forUser($actor)->authorize('prepare', [ExamExecution::class, $visit]);
                $visit->attendanceSnapshot();
                $hash = hash('sha256', 'execution-prepare:v1|'.$actor->id.'|'.strtolower($key));
                $requestHash = ExamExecution::requestHash((int) $visit->id, $itemId);
                $existing = ExamExecution::query()->where('creation_key_hash', $hash)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ((int) $existing->visit_id !== (int) $visit->id || ! hash_equals($existing->creation_request_hash, $requestHash)) {
                        $this->refuse();
                    }
                    $existing->setRelation('visit', $visit)->preparationSnapshot();
                    app(RegistryAudit::class)->record('executions', 'execution_preparation_replayed', $existing, ExamExecution::class);

                    return $existing;
                }
                $workflow->assertOpen($visit);
                if ($visit->status !== $expectedStatus) {
                    $this->refuse();
                }
                $item = $visit->order?->items()->whereKey($itemId)->lockForUpdate()->first();
                if ($item === null || $item->status !== 'authorized' || $item->quantity !== 1) {
                    $this->refuse();
                }
                $history = ExamExecution::query()->where('exam_order_item_id', $itemId)->orderBy('id')->lockForUpdate()->get();
                foreach ($history as $prior) {
                    $prior->preparationSnapshot();
                    if ($prior->status !== 'canceled') {
                        $this->refuse();
                    }
                }
                // Numbering follows the existing unique (tenant, visit, exam, attempt); patient lock serializes competing visits.
                $attempt = (int) ExamExecution::query()->where('visit_id', $visit->id)->where('exam_type_id', $item->exam_type_id)->max('attempt_no') + 1;
                if ($attempt > 65535) {
                    $this->refuse();
                }
                $now = now('UTC')->startOfSecond();
                if ($now->lessThan($visit->checked_in_at)) {
                    $this->refuse();
                }
                $context = ['schema_version' => 1, 'tenant_id' => (int) $visit->tenant_id, 'visit_id' => (int) $visit->id, 'item_id' => $itemId,
                    'exam_type_id' => (int) $item->exam_type_id, 'attempt_no' => $attempt, 'actor_id' => (int) $actor->id,
                    'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash, 'request_hash' => $requestHash];
                $execution = new ExamExecution;
                $execution->visit()->associate($visit);
                $execution->item()->associate($item);
                $execution->forceFill(['exam_type_id' => $item->exam_type_id, 'attempt_no' => $attempt, 'status' => 'pending', 'source' => 'order', 'created_by' => $actor->id,
                    'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'creation_key_hash' => $hash,
                    'creation_request_hash' => $requestHash, 'created_at' => $now, 'updated_at' => $now])->save();
                app(RegistryAudit::class)->record('executions', 'execution_prepared', $execution, ExamExecution::class);

                return $execution;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->refuse();
        }
    }

    public static function rules(): array
    {
        return ['expected_status' => ['required', 'in:open,in_progress'], 'exam_order_item_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['execution' => 'O item já possui tentativa pendente ou não permite esta preparação. Reabra a consulta.']);
    }
}
