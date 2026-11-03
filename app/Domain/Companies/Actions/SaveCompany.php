<?php

declare(strict_types=1);

namespace App\Domain\Companies\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Services\CompanyAudit;
use App\Domain\Shared\ValueObjects\Cnpj;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveCompany
{
    public function execute(array $input, ?Company $company = null): Company
    {
        try {
            return DB::transaction(function () use ($input, $company): Company {
                $company ??= new Company;
                $event = $company->exists ? 'company_updated' : 'company_created';
                $company->fill(array_intersect_key($input, array_flip(['name', 'is_active'])));
                if (array_key_exists('cnpj', $input)) {
                    $company->cnpj = empty($input['cnpj']) ? null : new Cnpj($input['cnpj'])->value;
                }
                $company->save();
                app(CompanyAudit::class)->record($event, $company);

                return $company;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['cnpj' => 'Já existe uma empresa com este CNPJ nesta clínica.']);
        }
    }
}
