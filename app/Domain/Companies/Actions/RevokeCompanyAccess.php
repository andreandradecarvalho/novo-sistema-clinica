<?php

declare(strict_types=1);

namespace App\Domain\Companies\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyAccess;
use App\Domain\Shared\Services\RegistryAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RevokeCompanyAccess
{
    public function execute(Company $company, CompanyAccess $access): void
    {
        DB::transaction(function () use ($company, $access): void {
            $parent = Company::query()->whereKey($company->getKey())->firstOrFail();
            $current = $parent->accesses()->whereKey($access->getKey())->lockForUpdate()->firstOrFail();
            if ($current->revoked_at !== null) {
                return;
            }
            $current->revoked_at = CarbonImmutable::now();
            $current->save();
            app(RegistryAudit::class)->record('company_access', 'company_access_revoked', $current, CompanyAccess::class);
        });
    }
}
