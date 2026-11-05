<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListOccupationalPrograms
{
    public function execute(Company $company): LengthAwarePaginator
    {
        Gate::authorize('viewAny', [OccupationalProgram::class, $company]);
        $programs = $company->programs()->select(OccupationalProgram::LIST_COLUMNS)->orderByDesc('id')->paginate(20);
        app(RegistryAudit::class)->record('occupational', 'occupational_programs_listed', $company, Company::class);

        return $programs;
    }
}
