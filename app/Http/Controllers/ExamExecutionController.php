<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Executions\Actions\AssignExecutionRoom;
use App\Domain\Executions\Actions\CancelPendingExecution;
use App\Domain\Executions\Actions\CompleteExamExecution;
use App\Domain\Executions\Actions\ListAssignableRooms;
use App\Domain\Executions\Actions\ListExamExecutions;
use App\Domain\Executions\Actions\PrepareExamExecution;
use App\Domain\Executions\Actions\StartExamExecution;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Patients\Models\Patient;
use App\Domain\Visits\Models\Visit;
use App\Http\Requests\AssignExecutionRoomRequest;
use App\Http\Requests\CancelPendingExecutionRequest;
use App\Http\Requests\CompleteExamExecutionRequest;
use App\Http\Requests\PrepareExamExecutionRequest;
use App\Http\Requests\StartExamExecutionRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class ExamExecutionController extends Controller
{
    public function index(Patient $patient, Visit $visit, ListExamExecutions $action): View
    {
        return view('executions.index', ['patient' => $patient, 'idempotencyKey' => (string) Str::uuid()] + $action->execute($patient, $visit));
    }

    public function store(PrepareExamExecutionRequest $request, Patient $patient, Visit $visit, PrepareExamExecution $action): RedirectResponse
    {
        $action->execute($patient, $visit, (int) $request->validated('exam_order_item_id'), $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.visits.executions.index', [$patient->public_id, $visit->public_id])->with('status', 'Preparação registrada. Consulte a situação da tentativa.');
    }

    public function room(Patient $patient, Visit $visit, ExamExecution $execution, ListAssignableRooms $action): View
    {
        return view('executions.room', ['patient' => $patient] + $action->execute($patient, $visit, $execution));
    }

    public function assignRoom(AssignExecutionRoomRequest $request, Patient $patient, Visit $visit, ExamExecution $execution, AssignExecutionRoom $action): RedirectResponse
    {
        $action->execute($patient, $visit, $execution, (int) $request->validated('room_id'), $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.visits.executions.index', [$patient->public_id, $visit->public_id])->with('status', 'Atribuição de sala registrada. Consulte a situação da tentativa.');
    }

    public function start(StartExamExecutionRequest $request, Patient $patient, Visit $visit, ExamExecution $execution, StartExamExecution $action): RedirectResponse
    {
        $action->execute($patient, $visit, $execution, $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.visits.executions.index', [$patient->public_id, $visit->public_id])->with('status', 'Início do exame registrado pelo profissional responsável.');
    }

    public function complete(CompleteExamExecutionRequest $request, Patient $patient, Visit $visit, ExamExecution $execution, CompleteExamExecution $action): RedirectResponse
    {
        $action->execute($patient, $visit, $execution, $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.visits.executions.index', [$patient->public_id, $visit->public_id])->with('status', 'Execução concluída. Resultados e laudos têm seus próprios fluxos.');
    }

    public function cancel(CancelPendingExecutionRequest $request, Patient $patient, Visit $visit, ExamExecution $execution, CancelPendingExecution $action): RedirectResponse
    {
        $action->execute($patient, $visit, $execution, $request->validated('reason'), $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.visits.executions.index', [$patient->public_id, $visit->public_id])->with('status', 'Cancelamento da tentativa registrado.');
    }
}
