<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Orders\Actions\CreateClinicalReceivable;
use App\Domain\Orders\Actions\ListClinicalReceivables;
use App\Domain\Orders\Actions\ReviewClinicalOrder;
use App\Domain\Orders\Actions\TransitionClinicalReceivable;
use App\Domain\Orders\Enums\ReceivableTransition;
use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Orders\Services\ClinicalFinancialReview;
use App\Domain\Patients\Models\Patient;
use App\Http\Requests\CreateClinicalReceivableRequest;
use App\Http\Requests\ReviewClinicalOrderRequest;
use App\Http\Requests\TransitionClinicalReceivableRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class ClinicalReceivableController extends Controller
{
    public function index(Patient $patient, ExamOrder $order, ListClinicalReceivables $action): View
    {
        $order = $action->execute($patient, $order);

        return view('orders.receivables', ['patient' => $patient, 'order' => $order, 'history' => $order->contextSnapshot(), 'review' => app(ClinicalFinancialReview::class)->inspect($order)]);
    }

    public function review(ReviewClinicalOrderRequest $request, Patient $patient, ExamOrder $order, ReviewClinicalOrder $action): RedirectResponse
    {
        $action->execute($patient, $order, (int) $request->validated('expected_revision'), $request->validated('expected_fingerprint'), $request->validated('reason'), $request->validated('idempotency_key'));

        return redirect()->route('patients.orders.receivables.index', [$patient->public_id, $order->public_id])->with('status', 'Revisão registrada. Cobrança e baixa continuam operações separadas.');
    }

    public function store(CreateClinicalReceivableRequest $request, Patient $patient, ExamOrder $order, CreateClinicalReceivable $action): RedirectResponse
    {
        $action->execute($patient, $order, $request->validated('due_date'));

        return redirect()->route('patients.orders.receivables.index', [$patient->public_id, $order->public_id])->with('status', 'Recebível disponível para consulta.');
    }

    public function settle(TransitionClinicalReceivableRequest $request, Patient $patient, ExamOrder $order, ClinicalReceivable $receivable, TransitionClinicalReceivable $action): RedirectResponse
    {
        $action->execute($patient, $order, $receivable, ReceivableTransition::Settle, $request->validated('reason'), $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.orders.receivables.index', [$patient->public_id, $order->public_id])->with('status', 'Baixa interna registrada.');
    }

    public function cancel(TransitionClinicalReceivableRequest $request, Patient $patient, ExamOrder $order, ClinicalReceivable $receivable, TransitionClinicalReceivable $action): RedirectResponse
    {
        $action->execute($patient, $order, $receivable, ReceivableTransition::Cancel, $request->validated('reason'), $request->validated('idempotency_key'), $request->validated('expected_status'));

        return redirect()->route('patients.orders.receivables.index', [$patient->public_id, $order->public_id])->with('status', 'Cancelamento financeiro registrado.');
    }
}
