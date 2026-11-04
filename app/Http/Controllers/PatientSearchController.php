<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Patients\Actions\SearchPatients;
use App\Domain\Patients\Models\Patient;
use App\Domain\Tenancy\Services\TenantContext;
use App\Http\Requests\SearchPatientsRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PatientSearchController extends Controller
{
    public function create(): View
    {
        Gate::authorize('viewAny', Patient::class);

        return view('patients.search');
    }

    public function store(SearchPatientsRequest $request, SearchPatients $action): RedirectResponse
    {
        $request->session()->put('patient_search', [
            'tenant_id' => app(TenantContext::class)->id(), 'user_id' => (int) $request->user()->getAuthIdentifier(),
            'expires_at' => now()->addMinutes(15)->getTimestamp(), 'criteria' => $action->criteria($request->validated()),
        ]);

        return redirect()->route('patients.search.results');
    }

    public function results(Request $request, SearchPatients $action): View|RedirectResponse
    {
        Gate::authorize('viewAny', Patient::class);
        $search = $request->session()->get('patient_search');
        if (! is_array($search) || ($search['tenant_id'] ?? null) !== app(TenantContext::class)->id()
            || ($search['user_id'] ?? null) !== (int) $request->user()->getAuthIdentifier()
            || ($search['expires_at'] ?? 0) <= now()->getTimestamp()) {
            $request->session()->forget('patient_search');

            return redirect()->route('patients.search.create')->with('status', 'Informe os critérios para uma nova busca.');
        }

        return view('patients.index', ['patients' => $action->execute($search['criteria']), 'searchResults' => true]);
    }
}
