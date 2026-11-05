<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Policies;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Tenancy\Services\TenantContext;

final class OccupationalProgramPolicy
{
    public function viewAny(User $actor, Company $company): bool
    {
        return $actor->can('view', $company) && app(TenantPermission::class)->allows($actor, 'programs.view');
    }

    public function create(User $actor, Company $company): bool
    {
        return $this->viewAny($actor, $company) && app(TenantPermission::class)->allows($actor, 'programs.manage')
            && app(TenantPermission::class)->allows($actor, 'professionals.view');
    }

    public function view(User $actor, OccupationalProgram $program): bool
    {
        return (int) $program->tenant_id === app(TenantContext::class)->id()
            && $program->company !== null && $this->viewAny($actor, $program->company);
    }
}
