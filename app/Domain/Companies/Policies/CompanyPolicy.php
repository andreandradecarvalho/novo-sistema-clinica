<?php

declare(strict_types=1);

namespace App\Domain\Companies\Policies;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Tenancy\Services\TenantContext;

final class CompanyPolicy
{
    public function viewAny(User $actor): bool
    {
        return app(TenantPermission::class)->allows($actor, 'companies.view');
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor) && app(TenantPermission::class)->allows($actor, 'companies.manage');
    }

    public function view(User $actor, Company $company): bool
    {
        return (int) $company->tenant_id === app(TenantContext::class)->id() && $this->viewAny($actor);
    }

    public function update(User $actor, Company $company): bool
    {
        return $this->view($actor, $company) && $this->create($actor);
    }
}
