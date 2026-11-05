<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;

final class AppointmentPolicy
{
    public function viewAny(User $actor, Patient $patient): bool
    {
        return $actor->can('view', $patient) && app(TenantPermission::class)->allows($actor, 'appointments.view')
            && app(TenantPermission::class)->allows($actor, 'units.view');
    }

    public function create(User $actor, Patient $patient): bool
    {
        return $this->viewAny($actor, $patient) && app(TenantPermission::class)->allows($actor, 'appointments.create');
    }

    public function bookAt(User $actor, Patient $patient, Unit $unit): bool
    {
        return $this->create($actor, $patient) && $actor->can('view', $unit) && $this->withinUnit($actor, (int) $unit->id);
    }

    public function view(User $actor, Appointment $appointment): bool
    {
        return (int) $appointment->tenant_id === app(TenantContext::class)->id() && $appointment->patient !== null
            && $this->viewAny($actor, $appointment->patient) && $appointment->unit_id !== null && $this->withinUnit($actor, (int) $appointment->unit_id);
    }

    public function cancel(User $actor, Appointment $appointment): bool
    {
        return $this->view($actor, $appointment) && app(TenantPermission::class)->allows($actor, 'appointments.cancel');
    }

    public function confirm(User $actor, Appointment $appointment): bool
    {
        return $this->view($actor, $appointment) && app(TenantPermission::class)->allows($actor, 'appointments.confirm');
    }

    private function withinUnit(User $actor, int $unitId): bool
    {
        return (int) $actor->unit_id === $unitId || app(TenantPermission::class)->allows($actor, 'appointments.all_units');
    }
}
