<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Services\MemberSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SessionController extends Controller
{
    public function destroy(Request $request, string $id): RedirectResponse
    {
        abort_if(hash_equals($request->session()->getId(), $id), 422);
        $deleted = app(MemberSessions::class)->revoke((int) $request->user()->getAuthIdentifier(), $id);
        abort_unless($deleted, 404);

        return redirect()->route('security')->with('status', 'Sessão encerrada.');
    }
}
