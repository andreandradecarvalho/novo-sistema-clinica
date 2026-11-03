<?php

declare(strict_types=1);

namespace App\Domain\Patients\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Patients\Models\Patient;
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

    public function update(User $actor, Patient $patient): bool
    {
        return $this->view($actor, $patient) && $this->create($actor);
    }
}
