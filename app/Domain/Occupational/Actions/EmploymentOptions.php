<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Occupational\Models\EmploymentLink;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class EmploymentOptions
{
    public function companyFor(EmploymentLink $link): Company
    {
        if ($link->ends_on !== null || $link->starts_on === null) {
            throw ValidationException::withMessages(['starts_on' => 'Vínculo indisponível para nova versão.']);
        }

        return Company::query()->whereKey($link->company_id)->where('is_active', true)->firstOrFail();
    }

    public function companies(): LengthAwarePaginator
    {
        return Company::query()->select(['id', 'public_id', 'name'])->where('is_active', true)->orderBy('name')->orderBy('id')->paginate(20);
    }

    public function units(Company $company): Collection
    {
        return $company->units()->select(['id', 'name'])->where('is_active', true)->orderBy('name')->orderBy('id')->get();
    }
}
