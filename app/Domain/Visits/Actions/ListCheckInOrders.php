<?php

declare(strict_types=1);

namespace App\Domain\Visits\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Visits\Models\Visit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ListCheckInOrders
{
    public function execute(Patient $patient, Appointment $appointment): LengthAwarePaginator
    {
        $appointment = $patient->appointments()->whereKey($appointment->getKey())->firstOrFail();
        Gate::authorize('checkIn', [Visit::class, $appointment]);
        $appointment->contextSnapshot();
        if ($appointment->status !== 'confirmado') {
            throw ValidationException::withMessages(['visit' => 'Confirme a reserva antes de abrir o check-in.']);
        }

        return $patient->orders()->select(['id', 'tenant_id', 'public_id', 'patient_id', 'payer_type', 'status'])->where('status', 'authorized')
            ->when(! Gate::allows('viewAny', Company::class), fn ($query) => $query->where('payer_type', 'patient'))
            ->orderByDesc('id')->paginate(20);
    }
}
