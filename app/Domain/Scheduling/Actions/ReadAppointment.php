<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\Gate;

final class ReadAppointment
{
    public function execute(Patient $patient, Appointment $appointment): Appointment
    {
        $appointment = $patient->appointments()->whereKey($appointment->getKey())->firstOrFail();
        Gate::authorize('view', $appointment);
        $appointment->contextSnapshot();
        app(RegistryAudit::class)->record('appointments', 'appointment_viewed', $appointment, Appointment::class);

        return $appointment;
    }
}
