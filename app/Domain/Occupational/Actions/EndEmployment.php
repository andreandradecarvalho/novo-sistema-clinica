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

final class EndEmployment
{
    public function execute(Patient $patient, EmploymentLink $link, string $endsOn): EmploymentLink
    {
        return DB::transaction(function () use ($patient, $link, $endsOn): EmploymentLink {
            $current = app(EmploymentData::class)->open($patient, $link);
            $date = CarbonImmutable::parse($endsOn);
            if ($date->toDateString() < $current->starts_on->toDateString()) {
                throw ValidationException::withMessages(['termination_date' => 'O desligamento não pode anteceder o início da versão.']);
            }
            $current->ends_on = $date;
            $current->termination_date = $date;
            $current->save();
            app(RegistryAudit::class)->record('employments', 'employment_terminated', $current, EmploymentLink::class);

            return $current;
        });
    }
}
