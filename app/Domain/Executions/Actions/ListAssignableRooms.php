<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Services\ExecutionWorkflow;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ListAssignableRooms
{
    public function execute(Patient $patient, Visit $visit, ExamExecution $execution): array
    {
        $visit = $patient->visits()->whereKey($visit->getKey())->firstOrFail();
        $execution = $visit->executions()->whereKey($execution->getKey())->firstOrFail();
        $execution->setRelation('visit', $visit);
        Gate::authorize('assignRoom', $execution);
        $execution->preparationSnapshot();
        app(ExecutionWorkflow::class)->assertOpen($visit);
        $unit = Unit::query()->whereKey($visit->unit_id)->where('is_active', true)->first();
        if ($unit === null || $execution->status !== 'pending' || $execution->room_id !== null) {
            throw ValidationException::withMessages(['execution' => 'A tentativa ou unidade não permite selecionar sala.']);
        }
        $rooms = $unit->rooms()->select(['id', 'tenant_id', 'unit_id', 'room_key', 'name'])->where('is_active', true)->orderBy('ordem')->orderBy('name')->orderBy('id')->paginate(20);
        app(RegistryAudit::class)->record('executions', 'execution_room_selection_viewed', $execution, ExamExecution::class);

        return compact('visit', 'execution', 'rooms');
    }
}
