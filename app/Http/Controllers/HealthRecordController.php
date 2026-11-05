<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Clinical\Actions\CollectHealthAnswer;
use App\Domain\Clinical\Actions\ReadHealthRecords;
use App\Domain\Clinical\Models\HealthRecord;
use App\Domain\Patients\Models\Patient;
use App\Domain\Visits\Models\Visit;
use App\Http\Requests\CollectHealthAnswerRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class HealthRecordController extends Controller
{
    public function index(Patient $patient, Visit $visit, ReadHealthRecords $action): View
    {
        return view('health-records.index', ['patient' => $patient] + $action->index($patient, $visit));
    }

    public function create(Request $request, Patient $patient, Visit $visit, ReadHealthRecords $action): View
    {
        return view('health-records.create', ['patient' => $patient, 'idempotencyKey' => (string) Str::uuid()] + $action->create($patient, $visit, $request->query('question_id')));
    }

    public function store(CollectHealthAnswerRequest $request, Patient $patient, Visit $visit, CollectHealthAnswer $action): RedirectResponse
    {
        $record = $action->execute($patient, $visit, (int) $request->validated('question_id'), $request->validated('answer'), $request->validated('idempotency_key'));

        return redirect()->route('patients.visits.health-records.show', [$patient->public_id, $visit->public_id, $record->public_id])->with('status', 'Resposta registrada com a versão escolhida.');
    }

    public function show(Patient $patient, Visit $visit, HealthRecord $healthRecord, ReadHealthRecords $action): View
    {
        return view('health-records.show', ['patient' => $patient] + $action->show($patient, $visit, $healthRecord));
    }
}
