<?php

declare(strict_types=1);

namespace App\Domain\Companies\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Services\CompanyAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListCompanyUnits
{
    public function execute(Company $company): LengthAwarePaginator
    {
        $units = $company->units()->select(['id', 'company_id', 'tenant_id', 'name', 'city', 'state', 'is_active'])->orderBy('name')->orderBy('id')->paginate(20);
        app(CompanyAudit::class)->record('company_viewed', $company);

        return $units;
    }
}
