<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Actions\ListUnits;
use App\Domain\Tenancy\Actions\SaveUnit;
use App\Domain\Tenancy\Models\Unit;
use App\Http\Requests\SaveUnitRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class UnitController extends Controller
{
    public function index(ListUnits $action): View
    {
        Gate::authorize('viewAny', Unit::class);

        return view('units.index', ['units' => $action->execute()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Unit::class);

        return view('units.form', ['unit' => new Unit]);
    }

    public function store(SaveUnitRequest $request, SaveUnit $action): RedirectResponse
    {
        $unit = $action->execute($request->validated());

        return redirect()->route('units.show', $unit->public_id)->with('status', 'Unidade cadastrada.');
    }

    public function show(Unit $unit): View
    {
        Gate::authorize('view', $unit);
        app(RegistryAudit::class)->record('units', 'unit_viewed', $unit, Unit::class);

        return view('units.show', ['unit' => $unit]);
    }

    public function edit(Unit $unit): View
    {
        Gate::authorize('update', $unit);
        app(RegistryAudit::class)->record('units', 'unit_edit_viewed', $unit, Unit::class);

        return view('units.form', ['unit' => $unit]);
    }

    public function update(SaveUnitRequest $request, Unit $unit, SaveUnit $action): RedirectResponse
    {
        $action->execute($request->validated(), $unit);

        return redirect()->route('units.show', $unit->public_id)->with('status', 'Unidade atualizada.');
    }
}
