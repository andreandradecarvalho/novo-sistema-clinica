<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Services\ExecutionWorkflow;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AssignExecutionRoom
{
    public function execute(Patient $patient, Visit $visit, ExamExecution $execution, int $roomId, string $key, string $expectedStatus): ExamExecution
    {
        Validator::make(['room_id' => $roomId, 'idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $visit, $execution, $roomId, $key): ExamExecution {
            $workflow = app(ExecutionWorkflow::class);
            [$visit, $actor, $unit] = $workflow->lock($patient, $visit);
            $execution = $visit->executions()->whereKey($execution->getKey())->lockForUpdate()->firstOrFail();
            $execution->setRelation('visit', $visit);
            Gate::forUser($actor)->authorize('assignRoom', $execution);
            $context = $execution->preparationSnapshot();
            $hash = hash('sha256', 'execution-room:v1|'.$actor->id.'|'.strtolower($key));
            if (isset($context['room_assignment'])) {
                if ($context['room_assignment']['room_id'] !== $roomId || ! hash_equals($context['room_assignment']['key_hash'], $hash)) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('executions', 'execution_room_assignment_replayed', $execution, ExamExecution::class);

                return $execution;
            }
            $workflow->assertOpen($visit);
            $item = $visit->order?->items()->whereKey($execution->exam_order_item_id)->lockForUpdate()->first();
            $room = Room::query()->whereKey($roomId)->where('unit_id', $visit->unit_id)->lockForUpdate()->first();
            $now = now('UTC')->startOfSecond();
            if ($execution->status !== 'pending' || $execution->room_id !== null || $item === null || $item->status !== 'authorized' || $item->quantity !== 1
                || $unit->trashed() || ! $unit->is_active || $room === null || ! $room->is_active || $now->lessThan($execution->created_at)) {
                $this->refuse();
            }
            $context['room_assignment'] = ['room_id' => $roomId, 'unit_id' => (int) $visit->unit_id, 'room_key' => $room->room_key, 'room_name' => $room->name,
                'actor_id' => (int) $actor->id, 'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash];
            // Reviewed exact tenant/visit/item/execution/pending-state write; no professional, clinical timing or workflow state changes.
            $changed = DB::table('exam_executions')->where('tenant_id', $execution->tenant_id)->where('visit_id', $visit->id)->where('id', $execution->id)
                ->where('exam_order_item_id', $execution->exam_order_item_id)->where('status', 'pending')->whereNull('room_id')->whereNull('performed_by')
                ->whereNull('started_at')->whereNull('finished_at')->update(['room_id' => $roomId,
                    'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                $this->refuse();
            }
            $execution->refresh();
            app(RegistryAudit::class)->record('executions', 'execution_room_assigned', $execution, ExamExecution::class);

            return $execution;
        }, 3);
    }

    public static function rules(): array
    {
        return ['expected_status' => ['required', 'in:pending'], 'room_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['execution' => 'A tentativa ou sala não permite esta atribuição. Reabra a consulta.']);
    }
}
