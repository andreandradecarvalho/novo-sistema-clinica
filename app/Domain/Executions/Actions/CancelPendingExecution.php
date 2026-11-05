<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Services\ExecutionWorkflow;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CancelPendingExecution
{
    public function execute(Patient $patient, Visit $visit, ExamExecution $execution, string $reason, string $key, string $expectedStatus): ExamExecution
    {
        $reason = trim($reason);
        Validator::make(['reason' => $reason, 'idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $visit, $execution, $reason, $key): ExamExecution {
            $workflow = app(ExecutionWorkflow::class);
            [$visit, $actor] = $workflow->lock($patient, $visit);
            $execution = $visit->executions()->whereKey($execution->getKey())->lockForUpdate()->firstOrFail();
            $execution->setRelation('visit', $visit);
            Gate::forUser($actor)->authorize('cancel', $execution);
            $context = $execution->preparationSnapshot();
            $keyHash = hash('sha256', 'execution-cancel:v1|'.$actor->id.'|'.strtolower($key));
            $requestHash = hash('sha256', $reason);
            if (isset($context['cancellation'])) {
                if (! hash_equals($context['cancellation']['key_hash'], $keyHash) || ! hash_equals($context['cancellation']['request_hash'], $requestHash)) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('executions', 'execution_cancellation_replayed', $execution, ExamExecution::class);

                return $execution;
            }
            $workflow->assertOpen($visit);
            $item = $visit->order?->items()->whereKey($execution->exam_order_item_id)->lockForUpdate()->first();
            $now = now('UTC')->startOfSecond();
            if ($item === null || $item->status !== 'authorized' || $item->quantity !== 1 || $execution->status !== 'pending' || $now->lessThan($execution->created_at)) {
                $this->refuse();
            }
            if (isset($context['room_assignment']) && $now->lessThan(CarbonImmutable::parse($context['room_assignment']['occurred_at']))) {
                $this->refuse();
            }
            $context['cancellation'] = ['from_status' => 'pending', 'actor_id' => (int) $actor->id, 'occurred_at' => $now->toIso8601String(),
                'key_hash' => $keyHash, 'request_hash' => $requestHash];
            // Reviewed bulk transition: exact tenant/visit/item/execution/previous state, no clinical timing or reassignment.
            $changed = DB::table('exam_executions')->where('tenant_id', $execution->tenant_id)->where('visit_id', $visit->id)
                ->where('exam_order_item_id', $execution->exam_order_item_id)->where('id', $execution->id)->where('status', 'pending')
                ->whereNull('started_at')->whereNull('finished_at')->whereNull('performed_by')
                ->update(['status' => 'canceled', 'reason_encrypted' => Crypt::encryptString($reason),
                    'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                $this->refuse();
            }
            $execution->refresh();
            app(RegistryAudit::class)->record('executions', 'execution_canceled', $execution, ExamExecution::class);

            return $execution;
        }, 3);
    }

    public static function rules(): array
    {
        return ['expected_status' => ['required', 'in:pending'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['execution' => 'A tentativa mudou ou não permite este cancelamento. Reabra a consulta.']);
    }
}
