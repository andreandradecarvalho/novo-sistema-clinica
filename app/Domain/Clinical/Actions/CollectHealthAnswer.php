<?php

declare(strict_types=1);

namespace App\Domain\Clinical\Actions;

use App\Domain\Catalog\Actions\BuildHealthQuestionSnapshot;
use App\Domain\Catalog\Models\HealthQuestion;
use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Clinical\Models\HealthAnswer;
use App\Domain\Clinical\Models\HealthRecord;
use App\Domain\Executions\Services\ExecutionWorkflow;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CollectHealthAnswer
{
    public function execute(Patient $patient, Visit $visit, int $questionId, string $answer, string $key): HealthRecord
    {
        $answer = trim($answer);
        Validator::make(['question_id' => $questionId, 'answer' => $answer, 'idempotency_key' => $key], self::rules())->validate();
        try {
            return DB::transaction(function () use ($patient, $visit, $questionId, $answer, $key): HealthRecord {
                $workflow = app(ExecutionWorkflow::class);
                [$visit, $actor] = $workflow->lock($patient, $visit);
                Gate::forUser($actor)->authorize('create', [HealthRecord::class, $visit]);
                $visit->attendanceSnapshot();
                $keyHash = hash('sha256', 'health-collection:v1|'.$actor->id.'|'.strtolower($key));
                $requestHash = HealthRecord::requestHash((int) $visit->id, $questionId, $answer);
                $existing = HealthRecord::query()->where('creation_key_hash', $keyHash)->lockForUpdate()->first();
                if ($existing !== null) {
                    // Verify parent before decrypting a prior record from a different patient/visit.
                    if ((int) $existing->visit_id !== (int) $visit->id || ! hash_equals($existing->snapshot()['request_hash'], $requestHash)) {
                        $this->refuse();
                    }
                    app(RegistryAudit::class)->record('clinical', 'health_collection_replayed', $existing, HealthRecord::class);

                    return $existing;
                }
                $workflow->assertOpen($visit);
                $question = HealthQuestion::query()->whereKey($questionId)->firstOrFail();
                $group = HealthQuestionGroup::query()->whereKey($question->group_id)->lockForUpdate()->firstOrFail();
                $question = $group->questions()->whereKey($questionId)->lockForUpdate()->firstOrFail();
                $definition = app(BuildHealthQuestionSnapshot::class)->execute($group, $question);
                $now = now('UTC')->startOfSecond();
                if ($now->lessThan($visit->checked_in_at)) {
                    $this->refuse();
                }
                $record = new HealthRecord;
                $record->forceFill(['patient_id' => $visit->patient_id, 'appointment_id' => $visit->appointment_id, 'visit_id' => $visit->id,
                    'filled_by' => $actor->id, 'filled_at' => $now, 'creation_key_hash' => $keyHash, 'created_at' => $now, 'updated_at' => $now]);
                // Allocate public identity before building the encrypted owner-bound context.
                $record->public_id = (string) Str::ulid();
                $record->context_encrypted = Crypt::encryptString(json_encode(['schema_version' => 1, 'tenant_id' => (int) $visit->tenant_id,
                    'public_id' => $record->public_id, 'patient_id' => (int) $visit->patient_id, 'appointment_id' => (int) $visit->appointment_id,
                    'visit_id' => (int) $visit->id, 'actor_id' => (int) $actor->id, 'occurred_at' => $now->toIso8601String(), 'source' => 'visit',
                    'key_hash' => $keyHash, 'request_hash' => $requestHash], JSON_THROW_ON_ERROR));
                $record->save();
                $response = new HealthAnswer;
                $response->record()->associate($record);
                $response->forceFill($definition->answerColumns() + ['answer_encrypted' => Crypt::encryptString(json_encode([
                    'schema_version' => 1, 'tenant_id' => (int) $visit->tenant_id, 'record_id' => (int) $record->id, 'question_id' => $definition->questionId,
                    'code' => $definition->code, 'version' => $definition->version, 'label' => $definition->label, 'type' => $definition->type,
                    'options' => $definition->options, 'answer' => $answer,
                ], JSON_THROW_ON_ERROR))])->save();
                app(RegistryAudit::class)->record('clinical', 'health_answer_collected', $record, HealthRecord::class);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->refuse();
        }
    }

    public static function rules(): array
    {
        return ['question_id' => ['required', 'integer', 'min:1'], 'answer' => ['required', 'string', 'max:10000'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['health_record' => 'O envio diverge do registro existente ou o atendimento não permite nova coleta.']);
    }
}
