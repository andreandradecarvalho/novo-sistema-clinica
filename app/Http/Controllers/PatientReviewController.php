<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Actions\ListPatientDuplicates;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\PatientAudit;
use App\Domain\Patients\Services\PatientIdentityGroup;
use App\Domain\Patients\Services\PatientMergeReview;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class PatientReviewController extends Controller
{
    public function index(Patient $patient, ListPatientDuplicates $action): View
    {
        Gate::authorize('review', $patient);

        return view('patients.duplicates', ['patient' => $patient, 'candidates' => $action->execute($patient)]);
    }

    public function compare(Patient $patient, Patient $candidate): View
    {
        Gate::authorize('review', $patient);
        Gate::authorize('review', $candidate);
        abort_if($patient->is($candidate), 404);
        app(PatientAudit::class)->record('patient_comparison_viewed', $patient);
        app(PatientAudit::class)->record('patient_comparison_viewed', $candidate);

        $employmentCounts = [];
        foreach ([$patient, $candidate] as $subject) {
            if (Gate::allows('viewAny', [EmploymentLink::class, $subject])) {
                $employmentCounts[(int) $subject->getKey()] = EmploymentLink::query()->whereIn('patient_id', app(PatientIdentityGroup::class)->memberIds($subject))->count();
            }
        }

        return view('patients.compare', ['patient' => $patient, 'candidate' => $candidate, 'employmentCounts' => $employmentCounts,
            'reviewToken' => Gate::allows('merge', [$patient, $candidate]) ? app(PatientMergeReview::class)->issue(auth()->user(), $patient, $candidate) : null]);
    }
}
