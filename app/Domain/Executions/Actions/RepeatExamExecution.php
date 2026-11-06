<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Services\ExecutionRetryEvidence;
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

final class RepeatExamExecution
{
    public function execute(Patient $patient, Visit $visit, ExamExecution $execution, string $reason, string $key, string $expectedStatus): ExamExecution
    {
        $reason = trim($reason);
        Validator::make(['reason' => $reason, 'idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        try {
            return DB::transaction(function () use ($patient, $visit, $execution, $reason, $key, $expectedStatus): ExamExecution {
                $workflow = app(ExecutionWorkflow::class);
                [$visit, $actor] = $workflow->lock($patient, $visit);
                $execution = $visit->executions()->whereKey($execution->getKey())->lockForUpdate()->firstOrFail();
                $execution->setRelation('visit', $visit);
                Gate::forUser($actor)->authorize('repeat', $execution);
                $context = $execution->preparationSnapshot();
                $hash = hash('sha256', 'execution-repeat:v1|'.$actor->id.'|'.strtolower($key));
                $requestHash = ExecutionRetryEvidence::requestHash((int) $execution->id, $reason);
                if (isset($context['retry'])) {
                    if (! hash_equals($context['retry']['key_hash'], $hash) || ! hash_equals($context['retry']['request_hash'], $requestHash)) {
                        $this->refuse();
                    }
                    $next = $visit->executions()->whereKey($context['retry']['next_id'])->firstOrFail();
                    $next->preparationSnapshot();
                    app(RegistryAudit::class)->record('executions', 'execution_repetition_replayed', $next, ExamExecution::class);

                    return $next;
                }
                $workflow->assertOpen($visit);
                $item = $visit->order->items()->whereKey($execution->exam_order_item_id)->lockForUpdate()->firstOrFail();
                if ($visit->status !== 'in_progress' || $visit->order->status !== 'in_progress' || $execution->status !== $expectedStatus || $item->quantity !== 1
                    || ! in_array($execution->status, ['refused', 'incomplete', 'canceled'], true)
                    || ($execution->status === 'canceled' ? (! isset($context['repeat_of']) || $item->status !== 'authorized') : $item->status !== $execution->status)
                    || ExamExecution::query()->where('creation_key_hash', $hash)->exists()) {
                    $this->refuse();
                }
                foreach (ExamExecution::query()->where('exam_order_item_id', $item->id)->lockForUpdate()->get() as $prior) {
                    $prior->preparationSnapshot();
                    if ((int) $prior->id !== (int) $execution->id && ! in_array($prior->status, ['canceled', 'refused', 'incomplete'], true)) {
                        $this->refuse();
                    }
                }
                $attempt = (int) $visit->executions()->where('exam_type_id', $execution->exam_type_id)->max('attempt_no') + 1;
                $now = now('UTC')->startOfSecond();
                $previousAt = $context[$execution->status === 'canceled' ? 'cancellation' : 'exception']['occurred_at'];
                if ($attempt > 65535 || $now->lessThan($previousAt)) {
                    $this->refuse();
                }
                $nextContext = ['schema_version' => 1, 'tenant_id' => (int) $visit->tenant_id, 'visit_id' => (int) $visit->id, 'item_id' => (int) $item->id,
                    'exam_type_id' => (int) $execution->exam_type_id, 'attempt_no' => $attempt, 'actor_id' => (int) $actor->id,
                    'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash, 'request_hash' => ExamExecution::requestHash((int) $visit->id, (int) $item->id)];
                $next = new ExamExecution;
                $next->visit()->associate($visit);
                $next->item()->associate($item);
                $next->forceFill(['exam_type_id' => $execution->exam_type_id, 'attempt_no' => $attempt, 'status' => 'pending', 'source' => 'order', 'created_by' => $actor->id,
                    'context_encrypted' => Crypt::encryptString(json_encode($nextContext, JSON_THROW_ON_ERROR)), 'creation_key_hash' => $hash,
                    'creation_request_hash' => $nextContext['request_hash'], 'created_at' => $now, 'updated_at' => $now])->save();
                $event = ['previous_id' => (int) $execution->id, 'next_id' => (int) $next->id, 'from_status' => $execution->status, 'actor_id' => (int) $actor->id,
                    'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash, 'request_hash' => $requestHash, 'reason' => $reason];
                // Persist raw envelopes, without derived plaintext exception/cancellation reasons from readers.
                $sourceContext = json_decode(Crypt::decryptString((string) $execution->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
                $sourceContext['retry'] = $event;
                $nextContext['repeat_of'] = $event;
                foreach ([[$execution, $sourceContext], [$next, $nextContext]] as [$row, $envelope]) {
                    DB::table('exam_executions')->where('tenant_id', $visit->tenant_id)->where('visit_id', $visit->id)->where('exam_order_item_id', $item->id)->where('id', $row->id)
                        ->update(['context_encrypted' => Crypt::encryptString(json_encode($envelope, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
                }
                DB::table('exam_order_items')->where('tenant_id', $visit->tenant_id)->where('exam_order_id', $visit->exam_order_id)->where('id', $item->id)
                    ->where('status', $item->status)->update(['status' => 'authorized', 'updated_at' => $now]);
                $next->refresh()->preparationSnapshot();
                app(RegistryAudit::class)->record('executions', 'execution_repeated', $next, ExamExecution::class);

                return $next;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->refuse();
        }
    }

    public static function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid'], 'expected_status' => ['required', 'in:refused,incomplete,canceled']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['execution' => 'A tentativa não permite esta repetição. Reabra a consulta.']);
    }
}
