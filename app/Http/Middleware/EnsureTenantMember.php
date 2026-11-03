<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null) {
            abort_unless($user->is_active && (int) $user->tenant_id === app(TenantContext::class)->id(), 403);
        }
        if ($request->session()->has('login.id')) {
            $pending = User::query()->whereKey((int) $request->session()->get('login.id'))->first();
            if ($pending === null || ! $pending->is_active) {
                $request->session()->forget(['login.id', 'login.remember']);
                abort(403);
            }
        }

        return $next($request);
    }
}
