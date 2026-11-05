<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Executions\Services\ExecutionWorkflow;
use App\Domain\Patients\Models\Patient;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class StartExamExecution
{
    public function execute(Patient $patient, Visit $visit, ExamExecution $execution, string $key, string $expectedStatus): ExamExecution
    {
        Validator::make(['idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $visit, $execution, $key): ExamExecution {
            // Same leading lock as grant/revoke: revocation and start have a defined serialization point.
            $professional = Professional::query()->where('user_id', auth()->id())->lockForUpdate()->first();
            if ($professional === null) {
                abort(403);
            }
            $exam = ExamType::query()->whereKey($execution->exam_type_id)->lockForUpdate()->firstOrFail();
            $workflow = app(ExecutionWorkflow::class);
            [$visit, $actor, $unit] = $workflow->lock($patient, $visit);
            $execution = $visit->executions()->whereKey($execution->getKey())->lockForUpdate()->firstOrFail();
            $execution->setRelation('visit', $visit);
            Gate::forUser($actor)->authorize('start', $execution);
            $context = $execution->preparationSnapshot();
            $hash = hash('sha256', 'execution-start:v1|'.$actor->id.'|'.$execution->id.'|'.strtolower($key));
            if (isset($context['start'])) {
                if (! hash_equals($context['start']['key_hash'], $hash) || $context['start']['professional_id'] !== (int) $professional->id) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('executions', 'execution_start_replayed', $execution, ExamExecution::class);

                return $execution;
            }
            $workflow->assertOpen($visit);
            $item = $visit->order?->items()->whereKey($execution->exam_order_item_id)->lockForUpdate()->first();
            $room = Room::query()->whereKey($execution->room_id)->where('unit_id', $visit->unit_id)->lockForUpdate()->first();
            $grant = ProfessionalExamAuthorization::query()->where('professional_id', $professional->id)->where('exam_type_id', $exam->id)
                ->whereNull('revoked_at')->where('granted_at', '<=', now('UTC'))->lockForUpdate()->first();
            $now = now('UTC')->startOfSecond();
            if ($execution->status !== 'pending' || $item === null || $item->status !== 'authorized' || $item->quantity !== 1
                || (int) $exam->id !== (int) $execution->exam_type_id || ! $exam->is_active || $grant === null
                || $unit->trashed() || ! $unit->is_active || $room === null || ! $room->is_active || ! isset($context['room_assignment'])
                || $now->lessThan($visit->checked_in_at) || $now->lessThan($visit->order->authorized_at)
                || $now->lessThan(CarbonImmutable::parse($context['room_assignment']['occurred_at']))) {
                $this->refuse();
            }
            $grant->reasonSnapshot();
            $event = ['actor_id' => (int) $actor->id, 'professional_id' => (int) $professional->id, 'authorization_id' => (int) $grant->id,
                'execution_id' => (int) $execution->id, 'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash];
            $context['start'] = $event;
            // Exact scoped writes under patient/order/visit/item/execution locks; audit participates in the same transaction.
            $changed = DB::table('exam_executions')->where('tenant_id', $execution->tenant_id)->where('visit_id', $visit->id)->where('id', $execution->id)
                ->where('status', 'pending')->whereNull('performed_by')->whereNull('started_at')->whereNull('finished_at')
                ->update(['status' => 'in_progress', 'performed_by' => $professional->id, 'started_at' => $now,
                    'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                $this->refuse();
            }
            DB::table('exam_order_items')->where('tenant_id', $execution->tenant_id)->where('exam_order_id', $visit->exam_order_id)->where('id', $item->id)
                ->where('status', 'authorized')->update(['status' => 'in_progress', 'updated_at' => $now]);
            $visitContext = $visit->contextSnapshot();
            if ($visit->status === 'open') {
                $visitContext['start'] = $event;
                DB::table('visits')->where('tenant_id', $execution->tenant_id)->where('id', $visit->id)->where('status', 'open')
                    ->update(['status' => 'in_progress', 'context_encrypted' => Crypt::encryptString(json_encode($visitContext, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            }
            $order = $visit->order;
            $orderContext = $order->contextSnapshot();
            if ($order->status === 'authorized') {
                $orderContext['start'] = $event;
                DB::table('exam_orders')->where('tenant_id', $execution->tenant_id)->where('id', $order->id)->where('status', 'authorized')
                    ->update(['status' => 'in_progress', 'context_encrypted' => Crypt::encryptString(json_encode($orderContext, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            }
            $execution->refresh();
            app(RegistryAudit::class)->record('executions', 'execution_started', $execution, ExamExecution::class);

            return $execution;
        }, 3);
    }

    public static function rules(): array
    {
        return ['expected_status' => ['required', 'in:pending'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['execution' => 'A tentativa, sala ou concessão profissional não permite iniciar o exame. Reabra a consulta.']);
    }
}
