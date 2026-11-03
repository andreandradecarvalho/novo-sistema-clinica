<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Actions\ResetUserPassword;
use App\Domain\Identity\Actions\UpdateUserPassword;
use App\Domain\Identity\Auth\TenantTwoFactorProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TwoFactorAuthenticationProvider::class, fn ($app) => new TenantTwoFactorProvider(
            $app->make(Google2FA::class), $app->make(Repository::class),
        ));
    }

    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', strtolower((string) $request->input('email')))->where('is_active', true)->first();

            return $user !== null && Hash::check((string) $request->input('password'), $user->password) ? $user : null;
        });
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        ResetPassword::createUrlUsing(fn ($user, string $token): string => request()->getSchemeAndHttpHost().route('password.reset', [
            'token' => $token, 'email' => $user->getEmailForPasswordReset(),
        ], false));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(app(TenantContext::class)->key('login:'.hash('sha256', strtolower((string) $request->input('email')).'|'.$request->ip()))),
            Limit::perMinute(30)->by(app(TenantContext::class)->key('login-ip:'.$request->ip())),
        ]);
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by(
            app(TenantContext::class)->key('2fa:'.$request->session()->get('login.id').'|'.$request->ip()),
        ));
    }
}
