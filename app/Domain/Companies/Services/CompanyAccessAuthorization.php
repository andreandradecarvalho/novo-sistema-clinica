<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Domain\Companies\Enums\CompanyAccessScope;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyAccess;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Tenancy\Services\TenantContext;

final class CompanyAccessAuthorization
{
    public function allows(User $actor, Company $company, CompanyAccessScope $scope): bool
    {
        $tenantId = app(TenantContext::class)->id();
        if ((int) $actor->tenant_id !== $tenantId || (int) $company->tenant_id !== $tenantId) {
            return false;
        }
        $current = User::query()->whereKey($actor->getKey())->where('is_active', true)->first();
        if ($current === null || ! app(TenantPermission::class)->allows($current, $scope->permission())
            || ! Company::query()->whereKey($company->getKey())->where('is_active', true)->exists()) {
            return false;
        }
        $now = now();

        return CompanyAccess::query()->where('user_id', $current->getKey())->where('company_id', $company->getKey())
            ->where('scope', $scope->value)->whereNull('revoked_at')->where('valid_from', '<=', $now)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now))->exists();
    }
}
