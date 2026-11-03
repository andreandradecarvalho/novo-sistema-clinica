<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Companies\Actions\ListCompanies;
use App\Domain\Companies\Actions\ListCompanyUnits;
use App\Domain\Companies\Actions\SaveCompany;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Services\CompanyAudit;
use App\Http\Requests\SaveCompanyRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class CompanyController extends Controller
{
    public function index(ListCompanies $action): View
    {
        Gate::authorize('viewAny', Company::class);

        return view('companies.index', ['companies' => $action->execute()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Company::class);

        return view('companies.form', ['company' => new Company]);
    }

    public function store(SaveCompanyRequest $request, SaveCompany $action): RedirectResponse
    {
        $company = $action->execute($request->validated());

        return redirect()->route('companies.show', $company->public_id)->with('status', 'Empresa cadastrada.');
    }

    public function show(Company $company, ListCompanyUnits $action): View
    {
        Gate::authorize('view', $company);

        return view('companies.show', ['company' => $company, 'units' => $action->execute($company)]);
    }

    public function edit(Company $company): View
    {
        Gate::authorize('update', $company);
        app(CompanyAudit::class)->record('company_edit_viewed', $company);

        return view('companies.form', ['company' => $company]);
    }

    public function update(SaveCompanyRequest $request, Company $company, SaveCompany $action): RedirectResponse
    {
        $action->execute($request->validated(), $company);

        return redirect()->route('companies.show', $company->public_id)->with('status', 'Empresa atualizada.');
    }
}
