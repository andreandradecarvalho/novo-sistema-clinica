<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Orders\Actions\CreateExamOrder;
use App\Domain\Orders\Actions\ListExamOrders;
use App\Domain\Orders\Actions\ListOrderExams;
use App\Domain\Orders\Actions\ReadExamOrder;
use App\Domain\Orders\Actions\TransitionExamOrder;
use App\Domain\Orders\Enums\OrderTransition;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Http\Requests\CreateExamOrderRequest;
use App\Http\Requests\TransitionExamOrderRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class ExamOrderController extends Controller
{
    public function index(Patient $patient, ListExamOrders $action): View
    {
        return view('orders.index', ['patient' => $patient, 'orders' => $action->execute($patient)]);
    }

    public function create(Patient $patient, ListOrderExams $action): View
    {
        return view('orders.create', ['patient' => $patient, 'exams' => $action->execute($patient)]);
    }

    public function store(CreateExamOrderRequest $request, Patient $patient, CreateExamOrder $action): RedirectResponse
    {
        $order = $action->execute($patient, $request->validated('exam_ids'));

        return redirect()->route('patients.orders.show', [$patient->public_id, $order->public_id])->with('status', 'Rascunho de pedido registrado.');
    }

    public function show(Patient $patient, ExamOrder $order, ReadExamOrder $action): View
    {
        $order = $action->execute($patient, $order);

        return view('orders.show', ['patient' => $patient, 'order' => $order, 'history' => $order->contextSnapshot()]);
    }

    public function authorize(TransitionExamOrderRequest $request, Patient $patient, ExamOrder $order, TransitionExamOrder $action): RedirectResponse
    {
        $action->execute($patient, $order, OrderTransition::Authorize, $request->validated('expected_status'));

        return redirect()->route('patients.orders.show', [$patient->public_id, $order->public_id])->with('status', 'Pedido autorizado.');
    }

    public function cancel(TransitionExamOrderRequest $request, Patient $patient, ExamOrder $order, TransitionExamOrder $action): RedirectResponse
    {
        $action->execute($patient, $order, OrderTransition::Cancel, $request->validated('expected_status'), $request->validated('reason'));

        return redirect()->route('patients.orders.show', [$patient->public_id, $order->public_id])->with('status', 'Pedido cancelado.');
    }
}
