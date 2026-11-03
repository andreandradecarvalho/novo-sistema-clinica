<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Occupational\Services\EmploymentData;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\DB;

final class CreateEmployment
{
    public function execute(Patient $patient, Company $company, array $input): EmploymentLink
    {
        return DB::transaction(function () use ($patient, $company, $input): EmploymentLink {
            $data = app(EmploymentData::class);
            $data->lockPatient($patient);
            $parent = $data->company((int) $company->getKey());
            $link = new EmploymentLink;
            $link->forceFill($data->attributes($parent, $input) + ['patient_id' => $patient->getKey(), 'company_id' => $parent->getKey(), 'source' => 'manual'])->save();
            app(RegistryAudit::class)->record('employments', 'employment_created', $link, EmploymentLink::class);

            return $link;
        });
    }
}
