<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\Models\Domain;
use App\Domain\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $context->set(null);
        $domain = Domain::query()->with('tenant')->where('domain', strtolower($request->getHost()))->first();
        abort_unless($domain?->tenant !== null, 404);
        $tenant = $domain->tenant;
        if ($request->hasHeader('X-Tenant')) {
            abort_unless(hash_equals((string) $tenant->public_id, (string) $request->header('X-Tenant')), 404);
        }
        abort_if($tenant->status === 'suspended', 403, 'Clínica indisponível.');
        abort_if($tenant->status === 'read_only' && ! $request->isMethodSafe() && ! $request->is('login', 'logout', 'two-factor-challenge', 'forgot-password', 'reset-password', 'user/password', 'user/confirm-password', 'user/two-factor-authentication', 'user/confirmed-two-factor-authentication', 'user/two-factor-recovery-codes'), 423);
        $context->set($tenant);
        try {
            return $next($request);
        } finally {
            $context->set(null);
        }
    }
}
