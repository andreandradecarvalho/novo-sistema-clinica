<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Companies\Actions\SaveCompanyUnit;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyUnit;
use App\Domain\Companies\Services\CompanyAudit;
use App\Http\Requests\SaveCompanyUnitRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class CompanyUnitController extends Controller
{
    public function create(Company $company): View
    {
        Gate::authorize('create', [CompanyUnit::class, $company]);

        return view('companies.unit-form', ['company' => $company, 'unit' => new CompanyUnit]);
    }

    public function store(SaveCompanyUnitRequest $request, Company $company, SaveCompanyUnit $action): RedirectResponse
    {
        $action->execute($company, $request->validated());

        return redirect()->route('companies.show', $company->public_id)->with('status', 'Estabelecimento cadastrado.');
    }

    public function edit(Company $company, CompanyUnit $unit): View
    {
        Gate::authorize('update', [$unit, $company]);
        app(CompanyAudit::class)->record('company_unit_edit_viewed', $unit);

        return view('companies.unit-form', ['company' => $company, 'unit' => $unit]);
    }

    public function update(SaveCompanyUnitRequest $request, Company $company, CompanyUnit $unit, SaveCompanyUnit $action): RedirectResponse
    {
        $action->execute($company, $request->validated(), $unit);

        return redirect()->route('companies.show', $company->public_id)->with('status', 'Estabelecimento atualizado.');
    }
}
