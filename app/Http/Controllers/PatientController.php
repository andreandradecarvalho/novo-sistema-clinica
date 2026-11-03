<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Patients\Actions\ListPatients;
use App\Domain\Patients\Actions\SavePatient;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\PatientAudit;
use App\Http\Requests\SavePatientRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PatientController extends Controller
{
    public function index(ListPatients $action): View
    {
        Gate::authorize('viewAny', Patient::class);

        return view('patients.index', ['patients' => $action->execute()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Patient::class);

        return view('patients.form', ['patient' => new Patient]);
    }

    public function store(SavePatientRequest $request, SavePatient $action): RedirectResponse
    {
        $patient = $action->execute($request->validated());

        return redirect()->route('patients.show', $patient->public_id)->with('status', 'Paciente cadastrado.');
    }

    public function show(Patient $patient): View
    {
        Gate::authorize('view', $patient);
        app(PatientAudit::class)->record('patient_viewed', $patient);

        return view('patients.show', ['patient' => $patient]);
    }

    public function edit(Patient $patient): View
    {
        Gate::authorize('update', $patient);
        app(PatientAudit::class)->record('patient_edit_viewed', $patient);

        return view('patients.form', ['patient' => $patient]);
    }

    public function update(SavePatientRequest $request, Patient $patient, SavePatient $action): RedirectResponse
    {
        $action->execute($request->validated(), $patient);

        return redirect()->route('patients.show', $patient->public_id)->with('status', 'Cadastro atualizado.');
    }
}
