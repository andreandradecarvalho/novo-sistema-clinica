<?php

declare(strict_types=1);

namespace App\Domain\Companies\Policies;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyAccess;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;

final class CompanyAccessPolicy
{
    public function viewAny(User $actor, Company $company): bool
    {
        return app(CompanyPolicy::class)->view($actor, $company)
            && app(TenantPermission::class)->allows($actor, 'company_access.view')
            && app(TenantPermission::class)->allows($actor, 'users.view');
    }

    public function create(User $actor, Company $company): bool
    {
        return $company->is_active && $this->viewAny($actor, $company)
            && app(TenantPermission::class)->allows($actor, 'company_access.manage');
    }

    public function revoke(User $actor, CompanyAccess $access, Company $company): bool
    {
        return (int) $access->company_id === (int) $company->getKey() && (int) $access->tenant_id === (int) $company->tenant_id
            && $this->viewAny($actor, $company) && app(TenantPermission::class)->allows($actor, 'company_access.manage');
    }
}
