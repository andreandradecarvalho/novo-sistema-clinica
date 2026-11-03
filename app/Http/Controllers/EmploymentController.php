<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Companies\Models\Company;
use App\Domain\Occupational\Actions\CreateEmployment;
use App\Domain\Occupational\Actions\EmploymentOptions;
use App\Domain\Occupational\Actions\EndEmployment;
use App\Domain\Occupational\Actions\ListEmployments;
use App\Domain\Occupational\Actions\VersionEmployment;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Http\Requests\EndEmploymentRequest;
use App\Http\Requests\SaveEmploymentRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class EmploymentController extends Controller
{
    public function index(Patient $patient, ListEmployments $action): View
    {
        Gate::authorize('viewAny', [EmploymentLink::class, $patient]);

        return view('employments.index', ['patient' => $patient, 'employments' => $action->execute($patient)]);
    }

    public function create(Patient $patient, EmploymentOptions $options): View
    {
        Gate::authorize('create', [EmploymentLink::class, $patient]);
        app(RegistryAudit::class)->record('employments', 'employment_companies_listed', $patient, Patient::class);

        return view('employments.companies', ['patient' => $patient, 'companies' => $options->companies()]);
    }

    public function registration(Patient $patient, Company $company, EmploymentOptions $options): View
    {
        Gate::authorize('create', [EmploymentLink::class, $patient]);
        Gate::authorize('view', $company);
        abort_unless($company->is_active, 404);
        app(RegistryAudit::class)->record('employments', 'employment_registration_viewed', $patient, Patient::class);

        return view('employments.form', ['patient' => $patient, 'company' => $company, 'employment' => new EmploymentLink, 'units' => $options->units($company)]);
    }

    public function store(SaveEmploymentRequest $request, Patient $patient, Company $company, CreateEmployment $action): RedirectResponse
    {
        Gate::authorize('view', $company);
        $action->execute($patient, $company, $request->validated());

        return redirect()->route('patients.employments.index', $patient->public_id)->with('status', 'Vínculo cadastrado.');
    }

    public function version(Patient $patient, EmploymentLink $employment, EmploymentOptions $options): View
    {
        Gate::authorize('update', [$employment, $patient]);
        $company = $options->companyFor($employment);
        app(RegistryAudit::class)->record('employments', 'employment_version_viewed', $employment, EmploymentLink::class);

        return view('employments.form', ['patient' => $patient, 'company' => $company, 'employment' => $employment, 'units' => $options->units($company)]);
    }

    public function replace(SaveEmploymentRequest $request, Patient $patient, EmploymentLink $employment, VersionEmployment $action): RedirectResponse
    {
        $action->execute($patient, $employment, $request->validated());

        return redirect()->route('patients.employments.index', $patient->public_id)->with('status', 'Nova versão registrada; o histórico anterior foi preservado.');
    }

    public function terminate(EndEmploymentRequest $request, Patient $patient, EmploymentLink $employment, EndEmployment $action): RedirectResponse
    {
        $action->execute($patient, $employment, $request->validated('termination_date'));

        return redirect()->route('patients.employments.index', $patient->public_id)->with('status', 'Desligamento registrado; o histórico foi preservado.');
    }
}
