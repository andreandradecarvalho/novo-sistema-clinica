<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Gate;

final class ListExamExecutions
{
    public function execute(Patient $patient, Visit $visit): array
    {
        $visit = $patient->visits()->whereKey($visit->getKey())->firstOrFail();
        Gate::authorize('viewAny', [ExamExecution::class, $visit]);
        $visit->attendanceSnapshot();
        $executions = $visit->executions()->with('item')->orderByDesc('id')->paginate(20);
        $histories = [];
        foreach ($executions as $execution) {
            $histories[$execution->id] = $execution->setRelation('visit', $visit)->preparationSnapshot();
        }
        $items = $visit->order?->items()->select(['id', 'tenant_id', 'exam_order_id', 'description_snapshot', 'status'])->where('status', 'authorized')->orderBy('id')->limit(20)->get();
        app(RegistryAudit::class)->record('executions', 'executions_listed', $visit, Visit::class);

        return compact('visit', 'executions', 'histories', 'items');
    }
}
