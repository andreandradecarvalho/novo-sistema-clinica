<?php

declare(strict_types=1);

namespace App\Domain\Clinical\Actions;

use App\Domain\Catalog\Actions\BuildHealthQuestionSnapshot;
use App\Domain\Catalog\Models\HealthQuestion;
use App\Domain\Clinical\Models\HealthRecord;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReadHealthRecords
{
    public function index(Patient $patient, Visit $visit): array
    {
        $visit = $patient->visits()->whereKey($visit->getKey())->firstOrFail();
        Gate::authorize('viewAny', [HealthRecord::class, $visit]);
        $visit->attendanceSnapshot();
        $records = $visit->healthRecords()->select(['id', 'tenant_id', 'public_id', 'patient_id', 'appointment_id', 'visit_id', 'filled_by', 'filled_at'])->orderByDesc('id')->paginate(20);
        app(RegistryAudit::class)->record('clinical', 'health_records_listed', $visit, Visit::class);

        return compact('visit', 'records');
    }

    public function create(Patient $patient, Visit $visit, mixed $questionId): array
    {
        $visit = $patient->visits()->whereKey($visit->getKey())->firstOrFail();
        Gate::authorize('create', [HealthRecord::class, $visit]);
        $visit->attendanceSnapshot();
        if (! in_array($visit->status, ['open', 'in_progress'], true)) {
            throw ValidationException::withMessages(['health_record' => 'O atendimento não permite nova coleta.']);
        }
        Validator::make(['question_id' => $questionId], ['question_id' => ['nullable', 'integer', 'min:1']])->validate();
        $snapshot = null;
        if ($questionId !== null) {
            $question = HealthQuestion::query()->whereKey((int) $questionId)->firstOrFail();
            $snapshot = app(BuildHealthQuestionSnapshot::class)->execute($question->group()->firstOrFail(), $question);
        }
        $questions = HealthQuestion::query()->select(['id', 'tenant_id', 'group_id', 'code', 'version', 'label'])->where('type', 'text')->whereNull('options')->where('applies_to', 'all')
            ->orderBy('code')->orderByDesc('version')->paginate(20)->withQueryString();
        app(RegistryAudit::class)->record('clinical', 'health_collection_form_viewed', $visit, Visit::class);

        return compact('visit', 'questions', 'snapshot');
    }

    public function show(Patient $patient, Visit $visit, HealthRecord $record): array
    {
        $visit = $patient->visits()->whereKey($visit->getKey())->firstOrFail();
        $record = $visit->healthRecords()->whereKey($record->getKey())->firstOrFail();
        $record->setRelation('visit', $visit);
        Gate::authorize('view', $record);
        $history = $record->snapshot();
        app(RegistryAudit::class)->record('clinical', 'health_record_viewed', $record, HealthRecord::class);

        return compact('visit', 'record', 'history');
    }
}
