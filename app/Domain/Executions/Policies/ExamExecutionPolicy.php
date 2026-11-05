<?php

declare(strict_types=1);

namespace App\Domain\Executions\Policies;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Services\ProfessionalExamAccess;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Models\Visit;

final class ExamExecutionPolicy
{
    public function viewAny(User $actor, Visit $visit): bool
    {
        return $actor->can('view', $visit) && app(TenantPermission::class)->allows($actor, 'executions.view');
    }

    public function prepare(User $actor, Visit $visit): bool
    {
        return $this->viewAny($actor, $visit) && app(TenantPermission::class)->allows($actor, 'executions.prepare');
    }

    public function view(User $actor, ExamExecution $execution): bool
    {
        return (int) $execution->tenant_id === app(TenantContext::class)->id() && $execution->visit !== null
            && $this->viewAny($actor, $execution->visit);
    }

    public function start(User $actor, ExamExecution $execution): bool
    {
        if (! $this->view($actor, $execution) || ! app(TenantPermission::class)->allows($actor, 'clinical.access')
            || ! app(TenantPermission::class)->allows($actor, 'executions.perform')) {
            return false;
        }
        if (in_array($execution->status, ['in_progress', 'completed'], true)) {
            return Professional::query()->whereKey($execution->performed_by)->where('user_id', $actor->id)->exists();
        }
        $exam = ExamType::query()->whereKey($execution->exam_type_id)->first();

        return $exam !== null && app(ProfessionalExamAccess::class)->allows($actor, $exam);
    }

    public function complete(User $actor, ExamExecution $execution): bool
    {
        if (! $this->view($actor, $execution) || ! app(TenantPermission::class)->allows($actor, 'clinical.access')
            || ! app(TenantPermission::class)->allows($actor, 'executions.perform') || ! app(TenantPermission::class)->allows($actor, 'executions.complete')
            || ! Professional::query()->whereKey($execution->performed_by)->where('user_id', $actor->id)->exists()) {
            return false;
        }
        if ($execution->status === 'completed') {
            return true;
        }
        $exam = ExamType::query()->whereKey($execution->exam_type_id)->first();

        return $exam !== null && app(ProfessionalExamAccess::class)->allows($actor, $exam);
    }

    public function cancel(User $actor, ExamExecution $execution): bool
    {
        return $this->view($actor, $execution) && app(TenantPermission::class)->allows($actor, 'executions.cancel');
    }

    public function assignRoom(User $actor, ExamExecution $execution): bool
    {
        return $this->view($actor, $execution) && app(TenantPermission::class)->allows($actor, 'executions.assign_room')
            && app(TenantPermission::class)->allows($actor, 'rooms.view');
    }
}
