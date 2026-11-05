<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListBookingUnits
{
    public function execute(Patient $patient): LengthAwarePaginator
    {
        Gate::authorize('create', [Appointment::class, $patient]);

        return Unit::query()->select(['id', 'tenant_id', 'name'])->where('is_active', true)
            ->when(! app(TenantPermission::class)->allows(auth()->user(), 'appointments.all_units'), fn ($query) => $query->whereKey(auth()->user()->unit_id))
            ->orderBy('name')->orderBy('id')->paginate(20);
    }
}
