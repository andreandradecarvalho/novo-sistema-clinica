<?php

declare(strict_types=1);

namespace App\Domain\Patients\Actions;

use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\PatientAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListPatients
{
    public function execute(): LengthAwarePaginator
    {
        $patients = Patient::query()->select(['id', 'public_id', 'name', 'birth_date', 'is_partial'])->orderBy('name')->orderBy('id')->paginate(20);
        app(PatientAudit::class)->record('patients_listed');

        return $patients;
    }
}
