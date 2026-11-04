<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Patients\Actions\ListPatientConsents;
use App\Domain\Patients\Actions\ReadPatientConsentEvidence;
use App\Domain\Patients\Actions\RecordPatientConsent;
use App\Domain\Patients\Enums\ConsentDecision;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientConsent;
use App\Http\Requests\RecordPatientConsentRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PatientConsentController extends Controller
{
    public function index(Patient $patient, ListPatientConsents $action): View
    {
        return view('patients.consents.index', ['patient' => $patient, 'consents' => $action->execute($patient)]);
    }

    public function create(Patient $patient): View
    {
        Gate::authorize('create', [PatientConsent::class, $patient]);

        return view('patients.consents.form', ['patient' => $patient, 'decisions' => ConsentDecision::cases()]);
    }

    public function store(RecordPatientConsentRequest $request, Patient $patient, RecordPatientConsent $action): RedirectResponse
    {
        $consent = $action->execute($patient, $request->validated());

        return redirect()->route('patients.consents.show', [$patient->public_id, $consent->id])->with('status', 'Manifestação registrada. O histórico anterior foi preservado.');
    }

    public function show(Patient $patient, PatientConsent $consent, ReadPatientConsentEvidence $action): View
    {
        return view('patients.consents.show', ['patient' => $patient, 'consent' => $consent, 'evidence' => $action->execute($consent)]);
    }
}
