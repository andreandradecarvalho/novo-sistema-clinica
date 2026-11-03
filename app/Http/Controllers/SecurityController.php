<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Services\MemberSessions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SecurityController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('auth.security', ['sessions' => app(MemberSessions::class)->list((int) $request->user()->getAuthIdentifier())]);
    }
}
