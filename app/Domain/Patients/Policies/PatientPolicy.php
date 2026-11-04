<?php

declare(strict_types=1);

namespace App\Domain\Patients\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\PatientIdentityGroup;
use App\Domain\Tenancy\Services\TenantContext;

final class PatientPolicy
{
    private function allowed(User $actor, string $permission): bool
    {
        return app(TenantPermission::class)->allows($actor, $permission);
    }

    public function viewAny(User $actor): bool
    {
        return $this->allowed($actor, 'patients.view');
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor) && $this->allowed($actor, 'patients.manage');
    }

    public function view(User $actor, Patient $patient): bool
    {
        return (int) $patient->tenant_id === app(TenantContext::class)->id() && $this->viewAny($actor);
    }

    public function review(User $actor, Patient $patient): bool
    {
        return $this->view($actor, $patient) && $this->allowed($actor, 'patients.review');
    }

    public function merge(User $actor, Patient $patient, Patient $candidate): bool
    {
        return ! $patient->is($candidate) && $this->review($actor, $patient) && $this->review($actor, $candidate)
            && $this->update($actor, $patient) && $this->update($actor, $candidate) && $this->allowed($actor, 'patients.merge') && $this->canReviewEmployments($actor, $patient, $candidate);
    }

    private function canReviewEmployments(User $actor, Patient $patient, Patient $candidate): bool
    {
        if ($this->allowed($actor, 'employments.view')) {
            return true;
        }
        $group = app(PatientIdentityGroup::class);

        return ! EmploymentLink::query()->whereIn('patient_id', [...$group->memberIds($patient), ...$group->memberIds($candidate)])->exists();
    }

    public function update(User $actor, Patient $patient): bool
    {
        return $this->view($actor, $patient) && $this->create($actor);
    }
}
