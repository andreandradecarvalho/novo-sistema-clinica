<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\RequireClinicalTwoFactor;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth', 'clinical.2fa']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [ResolveTenant::class], append: [EnsureTenantMember::class]);
        $middleware->alias(['clinical.2fa' => RequireClinicalTwoFactor::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['code', 'recovery_code', 'token', 'two_factor_secret', 'cpf', 'name', 'birth_date', 'email', 'mobile_phone', 'cnpj', 'city', 'council', 'council_number', 'specialty', 'registration_number', 'job_title', 'department', 'starts_on', 'termination_date', 'reason', 'review_token']);
        $exceptions->render(fn (MissingTenant $exception, Request $request) => $request->expectsJson()
            ? response()->json(['message' => 'Clínica indisponível.'], 404)
            : response()->view('errors.404', [], 404));
    })->create();
