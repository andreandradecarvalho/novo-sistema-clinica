<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Occupational\Services\EmploymentData;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VersionEmployment
{
    public function execute(Patient $patient, EmploymentLink $link, array $input): EmploymentLink
    {
        return DB::transaction(function () use ($patient, $link, $input): EmploymentLink {
            $data = app(EmploymentData::class);
            $current = $data->open($patient, $link);
            $startsOn = CarbonImmutable::parse($input['starts_on']);
            if ($startsOn->toDateString() <= $current->starts_on->toDateString()) {
                throw ValidationException::withMessages(['starts_on' => 'A nova versão deve iniciar após o início da versão anterior.']);
            }
            $company = $data->company((int) $current->company_id);
            $baseline = $current->only(['company_unit_id', 'registration_number', 'job_title', 'department']);
            $attributes = $data->attributes($company, array_replace($baseline, $input));
            $current->ends_on = $startsOn->subDay();
            $current->save();
            $next = new EmploymentLink;
            $next->forceFill($attributes + ['patient_id' => $current->patient_id, 'company_id' => $current->company_id, 'source' => 'manual'])->save();
            app(RegistryAudit::class)->record('employments', 'employment_version_closed', $current, EmploymentLink::class);
            app(RegistryAudit::class)->record('employments', 'employment_version_created', $next, EmploymentLink::class, (int) $current->getKey());

            return $next;
        });
    }
}
