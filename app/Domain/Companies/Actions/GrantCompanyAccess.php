<?php

declare(strict_types=1);

namespace App\Domain\Companies\Actions;

use App\Domain\Companies\Enums\CompanyAccessScope;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyAccess;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GrantCompanyAccess
{
    public function execute(Company $company, array $input): CompanyAccess
    {
        return DB::transaction(function () use ($company, $input): CompanyAccess {
            $current = Company::query()->whereKey($company->getKey())->where('is_active', true)->lockForUpdate()->first();
            if ($current === null) {
                throw ValidationException::withMessages(['company_id' => 'Empresa indisponível nesta clínica.']);
            }
            $user = User::query()->whereKey($input['user_id'])->where('is_active', true)->lockForUpdate()->first();
            if ($user === null) {
                throw ValidationException::withMessages(['user_id' => 'Selecione um integrante ativo desta clínica.']);
            }
            $scope = CompanyAccessScope::tryFrom($input['scope']);
            if ($scope === null) {
                throw ValidationException::withMessages(['scope' => 'Categoria indisponível.']);
            }
            $timezone = app(TenantContext::class)->require()->timezone;
            $from = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input['valid_from'], $timezone)->utc();
            $until = empty($input['valid_until']) ? null : CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input['valid_until'], $timezone)->utc();
            if ($until !== null && $until->lessThan($from)) {
                throw ValidationException::withMessages(['valid_until' => 'O fim deve ser igual ou posterior ao início.']);
            }
            $access = new CompanyAccess;
            $access->forceFill(['company_id' => $current->getKey(), 'user_id' => $user->getKey(), 'scope' => $scope, 'valid_from' => $from, 'valid_until' => $until])->save();
            app(RegistryAudit::class)->record('company_access', 'company_access_granted', $access, CompanyAccess::class);

            return $access;
        });
    }
}
