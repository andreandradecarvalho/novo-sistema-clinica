<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireClinicalTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active, 403);
        if ($user->must_change_password) {
            return $request->expectsJson() ? response()->json(['message' => 'Altere a senha inicial.'], 403) : redirect()->route('security');
        }
        if ($user->requiresTwoFactor() && $user->two_factor_confirmed_at === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Configure a autenticação em dois fatores.'], 403)
                : redirect()->route('security');
        }

        return $next($request);
    }
}
