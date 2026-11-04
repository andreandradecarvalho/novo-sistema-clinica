<?php

declare(strict_types=1);

namespace App\Domain\Patients\Actions;

use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientConsent;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListPatientConsents
{
    public function execute(Patient $patient): LengthAwarePaginator
    {
        Gate::authorize('viewAny', [PatientConsent::class, $patient]);
        $consents = $patient->consents()->select(PatientConsent::LIST_COLUMNS)->orderByDesc('occurred_at')->orderByDesc('id')->paginate(20);
        app(RegistryAudit::class)->record('consents', 'patient_consents_listed', $patient, Patient::class);

        return $consents;
    }
}
