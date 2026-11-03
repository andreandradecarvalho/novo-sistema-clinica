<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\AssignMemberUnit;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use App\Http\Requests\AssignMemberUnitRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class MemberUnitController extends Controller
{
    public function edit(User $user): View
    {
        Gate::authorize('assignUnit', $user);
        app(RegistryAudit::class)->record('users', 'member_unit_edit_viewed', $user, User::class);

        return view('users.unit', ['subject' => $user->load('unit'), 'units' => Unit::query()->select(['id', 'name'])->where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(AssignMemberUnitRequest $request, User $user, AssignMemberUnit $action): RedirectResponse
    {
        $unitId = $request->validated('unit_id');
        $changed = $action->execute($user, $unitId === null ? null : (int) $unitId);
        if ($changed && (int) $request->user()->getAuthIdentifier() === (int) $user->getKey()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Unidade atualizada. Entre novamente.');
        }

        return redirect()->route('users.show', $user->public_id)->with('status', $changed ? 'Unidade atualizada e sessões do integrante encerradas.' : 'A unidade permanece a mesma.');
    }
}
