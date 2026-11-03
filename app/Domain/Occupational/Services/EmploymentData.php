<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Services;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyUnit;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Models\Patient;
use Illuminate\Validation\ValidationException;

final class EmploymentData
{
    public function lockPatient(Patient $patient): Patient
    {
        return Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
    }

    public function open(Patient $patient, EmploymentLink $link): EmploymentLink
    {
        $this->lockPatient($patient);
        $current = EmploymentLink::query()->where('patient_id', $patient->getKey())->whereKey($link->getKey())->lockForUpdate()->firstOrFail();
        if ($current->ends_on !== null || $current->starts_on === null) {
            throw ValidationException::withMessages(['starts_on' => 'O vínculo já possui encerramento ou não tem início conhecido para esta operação.']);
        }

        return $current;
    }

    public function company(int $id): Company
    {
        $company = Company::query()->whereKey($id)->where('is_active', true)->lockForUpdate()->first();
        if ($company === null) {
            throw ValidationException::withMessages(['company_id' => 'Empresa indisponível nesta clínica.']);
        }

        return $company;
    }

    public function attributes(Company $company, array $input): array
    {
        $unitId = $input['company_unit_id'] ?? null;
        $unit = null;
        if ($unitId !== null) {
            $unit = CompanyUnit::query()->whereKey($unitId)->where('company_id', $company->getKey())->where('is_active', true)->lockForUpdate()->first();
            if ($unit === null) {
                throw ValidationException::withMessages(['company_unit_id' => 'Selecione um estabelecimento ativo desta empresa.']);
            }
        }

        return [
            'company_unit_id' => $unitId,
            'context_encrypted' => ['version' => 1, 'company_name' => $company->name, 'company_unit_name' => $unit?->name],
        ] + array_intersect_key($input, array_flip(['registration_number', 'job_title', 'department', 'starts_on']));
    }
}
