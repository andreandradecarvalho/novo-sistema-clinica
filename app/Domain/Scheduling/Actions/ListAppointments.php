<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListAppointments
{
    public function execute(Patient $patient): LengthAwarePaginator
    {
        Gate::authorize('viewAny', [Appointment::class, $patient]);
        $appointments = $patient->appointments()->select(['id', 'tenant_id', 'patient_id', 'public_id', 'unit_id', 'scheduled_date', 'status'])
            ->when(! app(TenantPermission::class)->allows(auth()->user(), 'appointments.all_units'), fn ($query) => $query->where('unit_id', (int) auth()->user()->unit_id))
            ->orderByDesc('scheduled_date')->orderByDesc('id')->paginate(20);
        app(RegistryAudit::class)->record('appointments', 'appointments_listed', $patient, Patient::class);

        return $appointments;
    }
}
