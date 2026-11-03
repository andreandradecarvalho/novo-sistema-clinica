<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Professionals\Actions\ListProfessionalCandidates;
use App\Domain\Professionals\Actions\ListProfessionals;
use App\Domain\Professionals\Actions\PrepareProfessionalRegistration;
use App\Domain\Professionals\Actions\ReadProfessional;
use App\Domain\Professionals\Actions\SaveProfessional;
use App\Domain\Professionals\Models\Professional;
use App\Http\Requests\SaveProfessionalRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class ProfessionalController extends Controller
{
    public function index(ListProfessionals $action): View
    {
        Gate::authorize('viewAny', Professional::class);

        return view('professionals.index', ['professionals' => $action->execute()]);
    }

    public function create(ListProfessionalCandidates $action): View
    {
        Gate::authorize('create', Professional::class);

        return view('professionals.candidates', ['users' => $action->execute()]);
    }

    public function registration(User $user, PrepareProfessionalRegistration $action): View
    {
        Gate::authorize('create', Professional::class);

        return view('professionals.form', ['professional' => new Professional, 'candidate' => $action->execute($user)]);
    }

    public function store(SaveProfessionalRequest $request, SaveProfessional $action): RedirectResponse
    {
        $professional = $action->execute($request->validated());

        return redirect()->route('professionals.show', $professional->id)->with('status', 'Profissional cadastrado.');
    }

    public function show(Professional $professional, ReadProfessional $action): View
    {
        Gate::authorize('view', $professional);

        return view('professionals.show', ['professional' => $action->execute($professional, 'professional_viewed')]);
    }

    public function edit(Professional $professional, ReadProfessional $action): View
    {
        Gate::authorize('update', $professional);

        return view('professionals.form', ['professional' => $action->execute($professional, 'professional_edit_viewed'), 'candidate' => null]);
    }

    public function update(SaveProfessionalRequest $request, Professional $professional, SaveProfessional $action): RedirectResponse
    {
        $action->execute($request->validated(), $professional);

        return redirect()->route('professionals.show', $professional->id)->with('status', 'Cadastro profissional atualizado.');
    }
}
