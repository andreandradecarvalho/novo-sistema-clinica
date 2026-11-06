<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Visits\Actions\CancelVisit;
use App\Domain\Visits\Actions\CheckInAppointment;
use App\Domain\Visits\Actions\CompleteVisit;
use App\Domain\Visits\Actions\ListCheckInOrders;
use App\Domain\Visits\Actions\ReadVisit;
use App\Domain\Visits\Models\Visit;
use App\Http\Requests\CancelVisitRequest;
use App\Http\Requests\CheckInAppointmentRequest;
use App\Http\Requests\CompleteVisitRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class VisitController extends Controller
{
    public function create(Patient $patient, Appointment $appointment, ListCheckInOrders $action): View
    {
        return view('visits.create', ['patient' => $patient, 'appointment' => $appointment, 'orders' => $action->execute($patient, $appointment), 'idempotencyKey' => (string) Str::uuid()]);
    }

    public function store(CheckInAppointmentRequest $request, Patient $patient, Appointment $appointment, CheckInAppointment $action): RedirectResponse
    {
        $visit = $action->execute($patient, $appointment, (int) $request->validated('exam_order_id'), $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.visits.show', [$patient->public_id, $visit->public_id])->with('status', $visit->status === 'canceled' ? 'Check-in recuperado. O atendimento permanece cancelado.' : 'Check-in recuperado ou registrado. Consulte a situação atual do atendimento.');
    }

    public function cancel(CancelVisitRequest $request, Patient $patient, Visit $visit, CancelVisit $action): RedirectResponse
    {
        $visit = $action->execute($patient, $visit, $request->validated('reason'), $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.visits.show', [$patient->public_id, $visit->public_id])->with('status', 'Atendimento cancelado. O histórico de chegada foi preservado.');
    }

    public function complete(CompleteVisitRequest $request, Patient $patient, Visit $visit, CompleteVisit $action): RedirectResponse
    {
        $visit = $action->execute($patient, $visit, $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.visits.show', [$patient->public_id, $visit->public_id])->with('status', 'Atendimento encerrado. O histórico foi preservado.');
    }

    public function show(Patient $patient, Visit $visit, ReadVisit $action): View
    {
        $visit = $action->execute($patient, $visit);

        return view('visits.show', ['patient' => $patient, 'visit' => $visit, 'history' => $visit->attendanceSnapshot(), 'idempotencyKey' => (string) Str::uuid()]);
    }
}
