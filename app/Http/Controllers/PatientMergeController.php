<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Patients\Actions\MergePatients;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientMerge;
use App\Domain\Patients\Services\PatientAudit;
use App\Http\Requests\MergePatientsRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PatientMergeController extends Controller
{
    public function store(MergePatientsRequest $request, Patient $patient, Patient $candidate, MergePatients $action): RedirectResponse
    {
        $merge = $action->execute($request->user(), $patient, $candidate, $request->validated());
        $survivor = Patient::query()->whereKey($merge->survivor_patient_id)->firstOrFail();

        return redirect()->route('patients.merges', $survivor->public_id)->with('status', 'Merge cadastral registrado. O cadastro sobrevivente foi mantido e a origem arquivada.');
    }

    public function index(Patient $patient): View
    {
        Gate::authorize('review', $patient);
        app(PatientAudit::class)->record('patient_merges_viewed', $patient);

        return view('patients.merges', ['patient' => $patient, 'merges' => PatientMerge::query()
            ->select(['id', 'tenant_id', 'survivor_patient_id', 'merged_patient_id', 'merged_by', 'reason', 'created_at'])
            ->where('survivor_patient_id', $patient->getKey())->with('mergedPatient:id,tenant_id,name,birth_date,email,mobile_phone,deleted_at')
            ->orderByDesc('id')->paginate(20)]);
    }
}
