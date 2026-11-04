<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Policies\PatientPolicy;
use App\Domain\Patients\Services\PatientIdentityGroup;

final class EmploymentLinkPolicy
{
    public function viewAny(User $actor, Patient $patient): bool
    {
        return app(PatientPolicy::class)->view($actor, $patient) && app(TenantPermission::class)->allows($actor, 'employments.view');
    }

    public function create(User $actor, Patient $patient): bool
    {
        return $this->viewAny($actor, $patient) && app(PatientPolicy::class)->update($actor, $patient)
            && app(TenantPermission::class)->allows($actor, 'employments.manage')
            && app(TenantPermission::class)->allows($actor, 'companies.view');
    }

    public function view(User $actor, EmploymentLink $link, Patient $patient): bool
    {
        return (int) $link->tenant_id === (int) $patient->tenant_id && $this->viewAny($actor, $patient)
            && app(PatientIdentityGroup::class)->contains($patient, (int) $link->patient_id);
    }

    public function update(User $actor, EmploymentLink $link, Patient $patient): bool
    {
        return $this->view($actor, $link, $patient) && $this->create($actor, $patient);
    }
}
