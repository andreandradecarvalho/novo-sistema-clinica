<?php

declare(strict_types=1);

namespace App\Domain\Companies\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Services\CompanyAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListCompanies
{
    public function execute(): LengthAwarePaginator
    {
        $companies = Company::query()->select(['id', 'public_id', 'name', 'cnpj', 'is_active'])->orderBy('name')->orderBy('id')->paginate(20);
        app(CompanyAudit::class)->record('companies_listed');

        return $companies;
    }
}
