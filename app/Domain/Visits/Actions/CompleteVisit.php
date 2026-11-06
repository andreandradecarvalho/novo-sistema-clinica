<?php

declare(strict_types=1);

namespace App\Domain\Visits\Actions;

use App\Domain\Executions\Services\ExecutionWorkflow;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use App\Domain\Visits\Services\VisitCompletionEvidence;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CompleteVisit
{
    public function execute(Patient $patient, Visit $visit, string $key, string $expectedStatus): Visit
    {
        Validator::make(['idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $visit, $key): Visit {
            // Same patient lock as preparation, collection and execution: closure cannot race a new write.
            [$visit, $actor] = app(ExecutionWorkflow::class)->lock($patient, $visit);
            Gate::forUser($actor)->authorize('complete', $visit);
            $context = $visit->attendanceSnapshot();
            $hash = hash('sha256', 'visit-complete:v1|'.$actor->id.'|'.$visit->id.'|'.strtolower($key));
            if (isset($context['completion'])) {
                if (! hash_equals($context['completion']['key_hash'], $hash)) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('visits', 'visit_completion_replayed', $visit, Visit::class);

                return $visit;
            }
            if ($visit->status !== 'in_progress') {
                $this->refuse();
            }
            $executions = $visit->executions()->orderBy('id')->lockForUpdate()->get();
            if (! $executions->contains('status', 'completed')) {
                $this->refuse();
            }
            $now = now('UTC')->startOfSecond();
            $resolved = [];
            foreach ($executions as $execution) {
                $evidence = $execution->setRelation('visit', $visit)->preparationSnapshot();
                try {
                    $terminal = VisitCompletionEvidence::terminal($execution, $evidence);
                } catch (LogicException) {
                    $this->refuse();
                }
                if ($now->lessThan($terminal['occurred_at'])) {
                    $this->refuse();
                }
                if ($terminal['successor_id'] !== null) {
                    $resolved[] = ['execution_id' => (int) $execution->id, 'completed_execution_id' => $terminal['successor_id']];
                }
            }
            // Validate existing collections without copying their clinical content into the closure event.
            foreach ($visit->healthRecords()->orderBy('id')->lockForUpdate()->get() as $record) {
                $record->setRelation('visit', $visit)->snapshot();
                if ($now->lessThan($record->filled_at)) {
                    $this->refuse();
                }
            }
            $context['completion'] = ['from_status' => 'in_progress', 'visit_id' => (int) $visit->id, 'actor_id' => (int) $actor->id,
                'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash,
                'execution_ids' => $executions->pluck('id')->map(fn ($id): int => (int) $id)->all(), 'resolved_executions' => $resolved];
            // Exact owner, parent and previous-state write; order/reservation/finance/answers remain unchanged.
            $changed = DB::table('visits')->where('tenant_id', $visit->tenant_id)->where('patient_id', $patient->id)->where('id', $visit->id)
                ->where('status', 'in_progress')->whereNull('completed_at')
                ->update(['status' => 'completed', 'completed_at' => $now, 'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                $this->refuse();
            }
            $visit->refresh()->attendanceSnapshot();
            app(RegistryAudit::class)->record('visits', 'visit_completed', $visit, Visit::class);

            return $visit;
        }, 3);
    }

    public static function rules(): array
    {
        return ['expected_status' => ['required', 'in:in_progress'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['visit' => 'O atendimento possui pendências ou não permite este encerramento. Reabra a consulta.']);
    }
}
