<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Executions\Services\ExecutionWorkflow;
use App\Domain\Patients\Models\Patient;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CompleteExamExecution
{
    public function execute(Patient $patient, Visit $visit, ExamExecution $execution, string $key, string $expectedStatus): ExamExecution
    {
        Validator::make(['idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $visit, $execution, $key): ExamExecution {
            // Professional lock is shared with grant/revoke and start; patient lock serializes item/order changes.
            $professional = Professional::query()->where('user_id', auth()->id())->lockForUpdate()->first();
            if ($professional === null) {
                abort(403);
            }
            $exam = ExamType::query()->whereKey($execution->exam_type_id)->lockForUpdate()->firstOrFail();
            $workflow = app(ExecutionWorkflow::class);
            [$visit, $actor] = $workflow->lock($patient, $visit);
            $execution = $visit->executions()->whereKey($execution->getKey())->lockForUpdate()->firstOrFail();
            $execution->setRelation('visit', $visit);
            Gate::forUser($actor)->authorize('complete', $execution);
            $context = $execution->preparationSnapshot();
            $hash = hash('sha256', 'execution-complete:v1|'.$actor->id.'|'.$execution->id.'|'.strtolower($key));
            if (isset($context['completion'])) {
                if (! hash_equals($context['completion']['key_hash'], $hash)) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('executions', 'execution_completion_replayed', $execution, ExamExecution::class);

                return $execution;
            }
            $workflow->assertOpen($visit);
            $order = $visit->order;
            $orderContext = $order->contextSnapshot();
            $items = $order->items()->orderBy('id')->lockForUpdate()->get();
            $item = $items->firstWhere('id', $execution->exam_order_item_id);
            $grant = ProfessionalExamAuthorization::query()->where('professional_id', $professional->id)->where('exam_type_id', $exam->id)
                ->whereNull('revoked_at')->where('granted_at', '<=', now('UTC'))->lockForUpdate()->first();
            $now = now('UTC')->startOfSecond();
            if ($visit->status !== 'in_progress' || $order->status !== 'in_progress' || $execution->status !== 'in_progress' || $execution->started_at === null
                || (int) $exam->id !== (int) $execution->exam_type_id || $item === null || $item->status !== 'in_progress' || $item->quantity !== 1 || $grant === null || ! $exam->is_active
                || $now->lessThan($execution->started_at) || (int) $execution->performed_by !== (int) $professional->id) {
                $this->refuse();
            }
            $grant->reasonSnapshot();
            foreach ($items as $other) {
                if (! in_array($other->status, ['authorized', 'in_progress', 'completed', 'refused', 'incomplete'], true) || $other->quantity !== 1) {
                    $this->refuse();
                }
                if (in_array($other->status, ['completed', 'refused', 'incomplete'], true)) {
                    $history = ExamExecution::query()->where('exam_order_item_id', $other->id)->where('status', $other->status)->get()->filter(fn ($prior): bool => ! isset($prior->preparationSnapshot()['retry']));
                    if ($history->count() !== 1) {
                        $this->refuse();
                    }
                    $prior = $history->sole();
                    $prior->preparationSnapshot();
                    if ($now->lessThan($prior->finished_at)) {
                        $this->refuse();
                    }
                }
            }
            $event = ['from_status' => 'in_progress', 'execution_id' => (int) $execution->id, 'actor_id' => (int) $actor->id,
                'professional_id' => (int) $professional->id, 'authorization_id' => (int) $grant->id, 'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash];
            $context['completion'] = $event;
            // Exact tenant/visit/item/execution/state writes; completion never changes prices or clinical answers.
            $changed = DB::table('exam_executions')->where('tenant_id', $execution->tenant_id)->where('visit_id', $visit->id)->where('id', $execution->id)
                ->where('exam_order_item_id', $item->id)->where('status', 'in_progress')->where('performed_by', $professional->id)->whereNotNull('started_at')->whereNull('finished_at')
                ->update(['status' => 'completed', 'finished_at' => $now, 'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            $itemChanged = DB::table('exam_order_items')->where('tenant_id', $execution->tenant_id)->where('exam_order_id', $order->id)->where('id', $item->id)
                ->where('status', 'in_progress')->update(['status' => 'completed', 'updated_at' => $now]);
            if ($changed !== 1 || $itemChanged !== 1) {
                $this->refuse();
            }
            if ($items->every(fn ($other): bool => (int) $other->id === (int) $item->id || $other->status === 'completed')) {
                $orderContext['completion'] = $event + ['item_ids' => $items->pluck('id')->map(fn ($id): int => (int) $id)->all()];
                $changed = DB::table('exam_orders')->where('tenant_id', $execution->tenant_id)->where('id', $order->id)->where('status', 'in_progress')
                    ->update(['status' => 'completed', 'context_encrypted' => Crypt::encryptString(json_encode($orderContext, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
                if ($changed !== 1) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('orders', 'exam_order_completed', $order->refresh(), $order::class);
            }
            $execution->refresh();
            app(RegistryAudit::class)->record('executions', 'execution_completed', $execution, ExamExecution::class);

            return $execution;
        }, 3);
    }

    public static function rules(): array
    {
        return ['expected_status' => ['required', 'in:in_progress'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['execution' => 'A tentativa ou autorização profissional não permite esta conclusão. Reabra a consulta.']);
    }
}
