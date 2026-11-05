<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Companies\Models\Company;
use App\Domain\Occupational\Actions\ListOccupationalPrograms;
use App\Domain\Occupational\Actions\ReadOccupationalProgram;
use App\Domain\Occupational\Actions\RegisterOccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use App\Http\Requests\RegisterOccupationalProgramRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class OccupationalProgramController extends Controller
{
    public function index(Company $company, ListOccupationalPrograms $action): View
    {
        return view('occupational.programs.index', ['company' => $company, 'programs' => $action->execute($company)]);
    }

    public function create(Company $company): View
    {
        Gate::authorize('create', [OccupationalProgram::class, $company]);

        app(RegistryAudit::class)->record('occupational', 'occupational_program_candidates_listed', $company, Company::class);

        return view('occupational.programs.professionals', ['company' => $company, 'professionals' => Professional::query()->select(['id', 'tenant_id', 'user_id', 'council', 'council_uf', 'council_number'])->whereHas('user', fn ($query) => $query->where('is_active', true))->with('user:id,tenant_id,name')->orderBy('id')->paginate(20)]);
    }

    public function registration(Company $company, Professional $professional): View
    {
        Gate::authorize('create', [OccupationalProgram::class, $company]);
        Gate::authorize('view', $professional);
        abort_unless((bool) $professional->user?->is_active, 404);

        app(RegistryAudit::class)->record('occupational', 'occupational_program_form_viewed', $company, Company::class);

        return view('occupational.programs.form', compact('company', 'professional'));
    }

    public function store(RegisterOccupationalProgramRequest $request, Company $company, Professional $professional, RegisterOccupationalProgram $action): RedirectResponse
    {
        $program = $action->execute($company, $professional, $request->validated());

        return redirect()->route('companies.programs.show', [$company->public_id, $program->id])->with('status', 'Referência de programa registrada.');
    }

    public function show(Company $company, OccupationalProgram $program, ReadOccupationalProgram $action): View
    {
        return view('occupational.programs.show', ['company' => $company, 'program' => $program, 'context' => $action->execute($program)]);
    }
}
