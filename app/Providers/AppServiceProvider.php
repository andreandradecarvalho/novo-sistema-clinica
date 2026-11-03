<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Auth\TenantPasswordBrokerManager;
use App\Domain\Identity\Auth\TenantSessionHandler;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Identity\Services\SecurityAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->extend('auth.password', fn ($manager, $app) => new TenantPasswordBrokerManager($app));
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        $this->app['session']->extend('tenant-database', fn ($app) => new TenantSessionHandler(
            $app['db']->connection(config('session.connection')), config('session.table'), (int) config('session.lifetime'), $app,
        ));
        foreach ([Login::class => 'login', Logout::class => 'logout', PasswordReset::class => 'password_reset', TwoFactorAuthenticationConfirmed::class => 'two_factor_confirmed'] as $event => $name) {
            Event::listen($event, function ($event) use ($name): void {
                app(SecurityAudit::class)->record($name, $event->user);
            });
        }
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->uncompromised());
        Queue::createPayloadUsing(fn (): array => ['tenant_id' => app(TenantContext::class)->id()]);
        Queue::before(function (JobProcessing $event): void {
            $context = app(TenantContext::class);
            $context->set(null);
            $id = $event->job->payload()['tenant_id'] ?? null;
            if ($id === null) {
                throw new MissingTenant;
            }
            $tenant = Tenant::query()->whereKey($id)->whereIn('status', ['trial', 'active', 'past_due'])->first();
            if ($tenant === null) {
                throw new MissingTenant;
            }
            $context->set($tenant);
        });
        Queue::looping(fn () => app(TenantContext::class)->set(null));
        Queue::after(fn (JobProcessed $event) => app(TenantContext::class)->set(null));
        Queue::exceptionOccurred(fn (JobExceptionOccurred $event) => app(TenantContext::class)->set(null));
    }
}
