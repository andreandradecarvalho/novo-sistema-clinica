<?php

declare(strict_types=1);

namespace App\Domain\Companies\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyUnit;
use App\Domain\Companies\Services\CompanyAudit;
use Illuminate\Support\Facades\DB;
use LogicException;

final class SaveCompanyUnit
{
    public function execute(Company $company, array $input, ?CompanyUnit $unit = null): CompanyUnit
    {
        return DB::transaction(function () use ($company, $input, $unit): CompanyUnit {
            $parent = Company::query()->findOrFail($company->getKey());
            if ($unit !== null && ((int) $unit->company_id !== (int) $parent->getKey() || (int) $unit->tenant_id !== (int) $parent->tenant_id)) {
                throw new LogicException('Establishment does not belong to this company.');
            }
            $unit ??= new CompanyUnit;
            $event = $unit->exists ? 'company_unit_updated' : 'company_unit_created';
            $unit->fill(array_intersect_key($input, array_flip(['name', 'city', 'state', 'is_active'])));
            $unit->company_id = $parent->getKey();
            $unit->save();
            app(CompanyAudit::class)->record($event, $unit);

            return $unit;
        });
    }
}
