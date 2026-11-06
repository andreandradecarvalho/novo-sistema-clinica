<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Executions\Services\ExecutionExceptionEvidence;
use App\Domain\Executions\Services\ExecutionWorkflow;
use App\Domain\Orders\Services\ExceptionFinancialPolicy;
use App\Domain\Patients\Models\Patient;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Setting;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ResolveExecutionException
{
    public function execute(Patient $patient, Visit $visit, ExamExecution $execution, string $outcome, string $reason, string $key, string $expectedStatus): ExamExecution
    {
        $reason = trim($reason);
        Validator::make(['outcome' => $outcome, 'reason' => $reason, 'idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $visit, $execution, $outcome, $reason, $key): ExamExecution {
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
            Gate::forUser($actor)->authorize('resolveException', $execution);
            $context = $execution->preparationSnapshot();
            $hash = hash('sha256', 'execution-exception:v1|'.$actor->id.'|'.$execution->id.'|'.strtolower($key));
            $requestHash = ExecutionExceptionEvidence::requestHash($outcome, $reason);
            if (isset($context['exception'])) {
                if (! hash_equals($context['exception']['key_hash'], $hash) || ! hash_equals($context['exception']['request_hash'], $requestHash)) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('executions', 'execution_exception_replayed', $execution, ExamExecution::class);

                return $execution;
            }
            $workflow->assertOpen($visit);
            $order = $visit->order;
            $order->contextSnapshot();
            $item = $order->items()->whereKey($execution->exam_order_item_id)->lockForUpdate()->first();
            $grant = ProfessionalExamAuthorization::query()->where('professional_id', $professional->id)->where('exam_type_id', $exam->id)
                ->whereNull('revoked_at')->where('granted_at', '<=', now('UTC'))->lockForUpdate()->first();
            if ($visit->status !== 'in_progress' || $order->status !== 'in_progress' || $execution->status !== 'in_progress' || $execution->started_at === null
                || (int) $exam->id !== (int) $execution->exam_type_id || $item === null || $item->status !== 'in_progress' || $item->quantity !== 1 || $grant === null || ! $exam->is_active
                || (int) $execution->performed_by !== (int) $professional->id) {
                $this->refuse();
            }
            $grant->reasonSnapshot();
            $setting = Setting::query()->where('key', ExceptionFinancialPolicy::KEY)->lockForUpdate()->first();
            $policy = app(ExceptionFinancialPolicy::class)->snapshot($setting);
            if ($policy['revision'] === 0) {
                throw ValidationException::withMessages(['execution' => 'Configure a política financeira da clínica antes de registrar este desfecho.']);
            }
            $policyEvent = array_last($policy['history']);
            // Capture the finish only after waiting for the policy lock and its current revision.
            $now = now('UTC')->startOfSecond();
            if ($now->lessThan($execution->started_at) || $now->lessThan($policyEvent['occurred_at'])) {
                $this->refuse();
            }
            $event = ['from_status' => 'in_progress', 'execution_id' => (int) $execution->id, 'actor_id' => (int) $actor->id,
                'professional_id' => (int) $professional->id, 'authorization_id' => (int) $grant->id, 'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash, 'outcome' => $outcome, 'request_hash' => $requestHash, 'financial_policy' => $policyEvent];
            $context['exception'] = $event;
            // Exact tenant/visit/item/execution/state writes; completion never changes prices or clinical answers.
            $changed = DB::table('exam_executions')->where('tenant_id', $execution->tenant_id)->where('visit_id', $visit->id)->where('id', $execution->id)
                ->where('exam_order_item_id', $item->id)->where('status', 'in_progress')->where('performed_by', $professional->id)->whereNotNull('started_at')->whereNull('finished_at')
                ->update(['status' => $outcome, 'reason_encrypted' => Crypt::encryptString($reason), 'finished_at' => $now, 'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            $itemChanged = DB::table('exam_order_items')->where('tenant_id', $execution->tenant_id)->where('exam_order_id', $order->id)->where('id', $item->id)
                ->where('status', 'in_progress')->update(['status' => $outcome, 'updated_at' => $now]);
            if ($changed !== 1 || $itemChanged !== 1) {
                $this->refuse();
            }
            $execution->refresh();
            app(RegistryAudit::class)->record('executions', 'execution_exception_recorded', $execution, ExamExecution::class);

            return $execution;
        }, 3);
    }

    public static function rules(): array
    {
        return ['outcome' => ['required', 'in:refused,incomplete'], 'reason' => ['required', 'string', 'max:1000'], 'expected_status' => ['required', 'in:in_progress'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['execution' => 'A tentativa ou autorização profissional não permite este desfecho. Reabra a consulta.']);
    }
}
