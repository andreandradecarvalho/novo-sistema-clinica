<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Actions\CancelAppointment;
use App\Domain\Scheduling\Actions\ConfirmAppointment;
use App\Domain\Scheduling\Actions\CreateAppointment;
use App\Domain\Scheduling\Actions\ListAppointments;
use App\Domain\Scheduling\Actions\ListBookingUnits;
use App\Domain\Scheduling\Actions\ReadAppointment;
use App\Domain\Scheduling\Models\Appointment;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\ConfirmAppointmentRequest;
use App\Http\Requests\CreateAppointmentRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class AppointmentController extends Controller
{
    public function index(Patient $patient, ListAppointments $action): View
    {
        return view('appointments.index', ['patient' => $patient, 'appointments' => $action->execute($patient)]);
    }

    public function create(Patient $patient, ListBookingUnits $action): View
    {
        return view('appointments.create', ['patient' => $patient, 'units' => $action->execute($patient), 'idempotencyKey' => (string) Str::uuid()]);
    }

    public function store(CreateAppointmentRequest $request, Patient $patient, CreateAppointment $action): RedirectResponse
    {
        $appointment = $action->execute($patient, (int) $request->validated('unit_id'), $request->validated('scheduled_date'), $request->validated('idempotency_key'));

        return redirect()->route('patients.appointments.show', [$patient->public_id, $appointment->public_id])->with('status', 'Reserva disponível para consulta.');
    }

    public function show(Patient $patient, Appointment $appointment, ReadAppointment $action): View
    {
        $appointment = $action->execute($patient, $appointment);

        return view('appointments.show', ['patient' => $patient, 'appointment' => $appointment, 'history' => $appointment->contextSnapshot(), 'idempotencyKey' => (string) Str::uuid()]);
    }

    public function confirm(ConfirmAppointmentRequest $request, Patient $patient, Appointment $appointment, ConfirmAppointment $action): RedirectResponse
    {
        $action->execute($patient, $appointment, $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.appointments.show', [$patient->public_id, $appointment->public_id])->with('status', 'Confirmação manual registrada.');
    }

    public function cancel(CancelAppointmentRequest $request, Patient $patient, Appointment $appointment, CancelAppointment $action): RedirectResponse
    {
        $action->execute($patient, $appointment, $request->validated('reason'), $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.appointments.show', [$patient->public_id, $appointment->public_id])->with('status', 'Reserva cancelada.');
    }
}
