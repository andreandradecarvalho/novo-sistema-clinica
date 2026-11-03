<?php

declare(strict_types=1);

namespace App\Domain\Companies\Policies;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyUnit;
use App\Domain\Identity\Models\User;

final class CompanyUnitPolicy
{
    public function create(User $actor, Company $company): bool
    {
        return app(CompanyPolicy::class)->update($actor, $company);
    }

    public function update(User $actor, CompanyUnit $unit, Company $company): bool
    {
        return (int) $unit->tenant_id === (int) $company->tenant_id
            && (int) $unit->company_id === (int) $company->getKey() && $this->create($actor, $company);
    }
}
