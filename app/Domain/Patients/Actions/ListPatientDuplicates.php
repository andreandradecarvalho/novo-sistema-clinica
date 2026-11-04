<?php

declare(strict_types=1);

namespace App\Domain\Patients\Actions;

use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\PatientAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListPatientDuplicates
{
    public function execute(Patient $patient): LengthAwarePaginator
    {
        $candidates = Patient::query()->select(['id', 'public_id', 'name', 'birth_date', 'is_partial'])
            ->where('normalized_name', $patient->normalized_name)->whereKeyNot($patient->getKey())
            ->orderBy('birth_date')->orderBy('id')->paginate(20);
        app(PatientAudit::class)->record('patient_duplicates_listed', $patient);

        return $candidates;
    }
}
