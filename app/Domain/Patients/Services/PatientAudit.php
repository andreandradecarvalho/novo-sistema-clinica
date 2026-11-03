<?php

declare(strict_types=1);

namespace App\Domain\Patients\Services;

use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;

final class PatientAudit
{
    public function record(string $event, ?Patient $patient = null): void
    {
        app(RegistryAudit::class)->record('patients', $event, $patient, Patient::class);
    }
}
