<?php

declare(strict_types=1);

use App\Domain\Identity\Auth\TenantSessionHandler;
use App\Domain\Identity\Auth\TenantTwoFactorProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantCache;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantStorage;
use App\Support\LocalHomologation;
use App\Support\OrderHomologation;
use App\Support\StageThreeHomologation;
use Database\Seeders\HomologationSeeder;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Clinics;
use Tests\Support\ContextProbeJob;

beforeEach(function (): void {
    $this->withoutVite();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    $this->context = app(TenantContext::class);
    DB::beginTransaction();
    $this->seed();
    $this->a = Clinics::create();
    $this->b = Clinics::create();
    $this->context = app(TenantContext::class);
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('fails closed without context including restoration queries', function (): void {
    expect(fn () => User::query()->first())->toThrow(MissingTenant::class);
    expect(fn () => (new User)->newQueryForRestoration($this->a['user']->id)->first())->toThrow(MissingTenant::class);
});

it('scopes reads, creation and model restoration to the current clinic', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        expect(User::query()->count())->toBe(1);
        expect(User::query()->find($this->b['user']->id))->toBeNull();
        expect((new User)->newQueryForRestoration($this->b['user']->id)->first())->toBeNull();
        $user = User::query()->create(['name' => 'Nova pessoa', 'email' => 'nova@example.test', 'password' => $this->a['password']]);
        expect((int) $user->tenant_id)->toBe((int) $this->a['tenant']->id);
    });
    expect($this->context->current())->toBeNull();
});

it('rejects reassignment, foreign model mutations and bulk writes', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => $this->b['user']->update(['name' => 'Inválido']))->toThrow(LogicException::class);
        expect(fn () => User::query()->update(['tenant_id' => $this->b['tenant']->id]))->toThrow(LogicException::class);
        expect(fn () => User::query()->insert(['tenant_id' => $this->b['tenant']->id]))->toThrow(LogicException::class);
        expect(fn () => User::query()->upsert([], ['id']))->toThrow(LogicException::class);
    });
});

it('clears the context and team after exceptions', function (): void {
    try {
        $this->context->run($this->a['tenant'], fn () => throw new RuntimeException('Synthetic failure'));
    } catch (RuntimeException) {
    }
    expect($this->context->current())->toBeNull();
    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

it('rejects unknown domains and forged tenant headers', function (): void {
    $this->get('http://unknown.example.test/login')->assertNotFound();
    $this->withHeader('X-Tenant', $this->b['tenant']->public_id)->get('http://'.$this->a['host'].'/login')->assertNotFound();
    expect($this->context->current())->toBeNull();
});

it('renders login without enabling public registration', function (): void {
    $this->get('http://'.$this->a['host'].'/login')->assertOk()->assertSee('Entrar');
    $this->get('http://'.$this->a['host'].'/register')->assertNotFound();
});

it('authenticates the same email only with the password of the selected clinic', function (): void {
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->b['password']])->assertUnprocessable();
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->get('http://'.$this->a['host'].'/')->assertOk();
    $this->get('http://'.$this->b['host'].'/')->assertRedirect('/login');
});

it('rejects inactive members at login', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['is_active' => false])->save());
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertUnprocessable();
});

it('separates password reset tokens for an identical email', function (): void {
    $broker = Password::broker();
    $tokenA = $this->context->run($this->a['tenant'], fn () => $broker->createToken($this->a['user']));
    $tokenB = $this->context->run($this->b['tenant'], fn () => $broker->createToken($this->b['user']));
    $this->context->run($this->a['tenant'], function () use ($broker, $tokenA, $tokenB): void {
        expect($broker->tokenExists($this->a['user'], $tokenA))->toBeTrue();
        expect($broker->tokenExists($this->a['user'], $tokenB))->toBeFalse();
    });
    $this->context->run($this->b['tenant'], function () use ($broker, $tokenA, $tokenB): void {
        expect($broker->tokenExists($this->b['user'], $tokenB))->toBeTrue();
        expect($broker->tokenExists($this->b['user'], $tokenA))->toBeFalse();
    });
    expect(DB::table('password_reset_tokens')->where('email', $this->a['user']->email)->count())->toBe(2);
});

it('does not consume a reset token issued by another clinic', function (): void {
    $token = $this->context->run($this->a['tenant'], fn () => Password::broker()->createToken($this->a['user']));
    $this->postJson('http://'.$this->b['host'].'/reset-password', [
        'email' => $this->b['user']->email, 'token' => $token, 'password' => $this->a['password'], 'password_confirmation' => $this->a['password'],
    ])->assertUnprocessable();
    $this->context->run($this->b['tenant'], fn () => expect(Hash::check($this->b['password'], $this->b['user']->fresh()->password))->toBeTrue());
});

it('scopes teams and rejects a foreign role object', function (): void {
    $roleA = $this->context->run($this->a['tenant'], fn () => Role::findOrCreate('manager', 'web'));
    $roleB = $this->context->run($this->b['tenant'], fn () => Role::findOrCreate('manager', 'web'));
    $this->context->run($this->a['tenant'], function () use ($roleA, $roleB): void {
        $roleA->givePermissionTo('users.view');
        $this->a['user']->assignRole($roleA);
        expect($this->a['user']->can('users.view'))->toBeTrue();
        expect(fn () => $this->a['user']->assignRole($roleB))->toThrow(LogicException::class);
    });
    $this->context->run($this->b['tenant'], fn () => expect($this->b['user']->can('users.view'))->toBeFalse());
});

it('requires permission and hides a foreign member by public id', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('users.view'));
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->get('http://'.$this->a['host'].'/users')->assertOk()->assertSee($this->a['user']->name);
    $this->get('http://'.$this->a['host'].'/users/'.$this->b['user']->public_id)->assertNotFound();
});

it('denies the member directory without permission', function (): void {
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->get('http://'.$this->a['host'].'/users')->assertForbidden();
});

it('requires clinical two factor setup and initial password change', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('clinical.access'));
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->get('http://'.$this->a['host'].'/')->assertRedirectToRoute('security');
});

it('isolates cache keys and private storage paths', function (): void {
    Storage::fake('local');
    $cache = app(TenantCache::class);
    $storage = app(TenantStorage::class);
    $this->context->run($this->a['tenant'], function () use ($cache, $storage): void {
        expect($cache->remember('schedule', 60, fn () => 'A'))->toBe('A');
        $storage->put('exports/test.txt', 'A');
        expect(fn () => $storage->get('../tenant-b/test.txt'))->toThrow(InvalidArgumentException::class);
    });
    $this->context->run($this->b['tenant'], function () use ($cache, $storage): void {
        expect($cache->remember('schedule', 60, fn () => 'B'))->toBe('B');
        expect($storage->get('exports/test.txt'))->toBeNull();
    });
});

it('blocks mutations while a clinic is read only', function (): void {
    $this->a['tenant']->update(['status' => 'read_only']);
    $this->deleteJson('http://'.$this->a['host'].'/sessions/unknown')->assertStatus(423);
    $this->get('http://'.$this->a['host'].'/login')->assertOk();
});

it('keeps horizon inaccessible to clinic members even locally', function (): void {
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->get('http://'.$this->a['host'].'/horizon')->assertForbidden();
});

it('provisions the same clinic idempotently with tenant-scoped roles', function (): void {
    $slug = 'synthetic-'.strtolower((string) Str::ulid());
    foreach ([1, 2] as $_) {
        $this->artisan('tenant:create', ['name' => 'Clínica sintética', '--slug' => $slug, '--domain' => $slug.'.example.test'])->assertSuccessful();
    }
    $tenant = Tenant::query()->where('slug', $slug)->sole();
    $this->context->run($tenant, function (): void {
        expect(Role::query()->where('tenant_id', $this->context->id())->count())->toBe(6);
        expect(Role::findByName('clinic_admin', 'web')->hasPermissionTo('clinical.access'))->toBeTrue();
    });
});

it('completes TOTP enrollment and consumes recovery codes only once', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('clinical.access'));
    $base = 'http://'.$this->a['host'];
    $credentials = ['email' => $this->a['user']->email, 'password' => $this->a['password']];
    $this->postJson($base.'/login', $credentials)->assertOk();
    $this->postJson($base.'/user/confirm-password', ['password' => $this->a['password']])->assertSuccessful();
    $this->postJson($base.'/user/two-factor-authentication')->assertOk();
    [$secret, $recovery] = $this->context->run($this->a['tenant'], function (): array {
        $user = $this->a['user']->fresh();

        return [decrypt($user->two_factor_secret), $user->recoveryCodes()[0]];
    });
    $code = (new Google2FA)->getCurrentOtp($secret);
    $this->postJson($base.'/user/confirmed-two-factor-authentication', ['code' => $code])->assertOk();
    $this->get($base.'/')->assertOk();
    $this->get($base.'/security')->assertOk()->assertSee('Segundo fator confirmado');
    $this->postJson($base.'/logout')->assertSuccessful();
    $this->postJson($base.'/login', $credentials)->assertOk()->assertJson(['two_factor' => true]);
    $this->get($base.'/')->assertRedirect('/login');
    $this->postJson($base.'/two-factor-challenge', ['recovery_code' => 'invalid'])->assertUnprocessable();
    $this->postJson($base.'/two-factor-challenge', ['recovery_code' => $recovery])->assertSuccessful();
    $this->get($base.'/')->assertOk();
    $this->postJson($base.'/logout')->assertSuccessful();
    $this->postJson($base.'/login', $credentials)->assertOk()->assertJson(['two_factor' => true]);
    $this->postJson($base.'/two-factor-challenge', ['recovery_code' => $recovery])->assertUnprocessable();
});

it('rejects an inactive user during a pending two factor challenge', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill([
        'two_factor_secret' => encrypt((new Google2FA)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['synthetic-recovery'])),
        'two_factor_confirmed_at' => now(),
    ])->save());
    $base = 'http://'.$this->a['host'];
    $this->postJson($base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertJson(['two_factor' => true]);
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['is_active' => false])->save());
    $this->postJson($base.'/two-factor-challenge', ['recovery_code' => 'synthetic-recovery'])->assertForbidden();
});

it('separates identical TOTP codes by tenant and prevents replay in the same tenant', function (): void {
    $engine = new Google2FA;
    $secret = $engine->generateSecretKey();
    $code = $engine->getCurrentOtp($secret);
    $provider = app(TwoFactorAuthenticationProvider::class);
    $this->context->run($this->a['tenant'], function () use ($provider, $secret, $code): void {
        expect($provider->verify($secret, $code))->toBeTrue();
        expect($provider->verify($secret, $code))->toBeFalse();
    });
    $this->context->run($this->b['tenant'], fn () => expect($provider->verify($secret, $code))->toBeTrue());
});

it('rejects a TOTP code claimed by a competing request after the cache read', function (): void {
    $engine = Mockery::mock(Google2FA::class)->makePartial();
    $cache = app(Repository::class);
    $secret = $engine->generateSecretKey();
    $code = $engine->getCurrentOtp($secret);
    $this->context->run($this->a['tenant'], function () use ($engine, $cache, $secret, $code): void {
        $key = $this->context->key('2fa.used:'.hash('sha256', $secret.'|'.$code));
        $engine->shouldReceive('verifyKeyNewer')->once()->with($secret, $code, null)
            ->andReturnUsing(function () use ($cache, $key): int {
                // Another request wins after this request observed an unused code.
                $cache->add($key, 100, 90);

                return 100;
            });
        expect(new TenantTwoFactorProvider($engine, $cache)->verify($secret, $code))->toBeFalse();
    });
});

it('retains TOTP replay protection through the full accepted clock drift window', function (): void {
    $engine = Mockery::mock(Google2FA::class)->makePartial();
    $counter = (new Google2FA)->getTimestamp();
    $engine->shouldReceive('getTimestamp')->andReturn($counter, $counter + 2);
    $secret = $engine->generateSecretKey();
    $code = $engine->oathTotp($secret, $counter + 1);
    $provider = new TenantTwoFactorProvider($engine, app(Repository::class));
    try {
        $this->context->run($this->a['tenant'], function () use ($provider, $secret, $code): void {
            expect($provider->verify($secret, $code))->toBeTrue();
            $this->travel(61)->seconds();
            expect($provider->verify($secret, $code))->toBeFalse();
        });
    } finally {
        $this->travelBack();
    }
});

it('refuses TOTP authentication when replay protection has no cache', function (): void {
    $engine = new Google2FA;
    $secret = $engine->generateSecretKey();
    $provider = new TenantTwoFactorProvider($engine);
    expect($provider->verify($secret, $engine->getCurrentOtp($secret)))->toBeFalse();
});

it('isolates persisted session payloads by clinic even with an identical session id', function (): void {
    $handler = new TenantSessionHandler(DB::connection(), 'sessions', 120, app());
    $id = Str::random(40);
    $this->context->run($this->a['tenant'], fn () => $handler->write($id, 'synthetic-A'));
    $other = new TenantSessionHandler(DB::connection(), 'sessions', 120, app());
    $this->context->run($this->b['tenant'], function () use ($other, $id): void {
        expect($other->read($id))->toBe('');
        $other->write($id, 'synthetic-B');
    });
    $this->context->run($this->a['tenant'], fn () => expect($handler->read($id))->toBe('synthetic-A'));
    expect(fn () => $handler->read($id))->toThrow(MissingTenant::class);
});

it('revokes only another session owned by the authenticated member', function (): void {
    $base = 'http://'.$this->a['host'];
    $this->postJson($base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->postJson($base.'/user/confirm-password', ['password' => $this->a['password']])->assertSuccessful();
    foreach ([$this->a, $this->b] as $clinic) {
        DB::table('sessions')->insert(['tenant_id' => $clinic['tenant']->id, 'id' => 'synthetic-'.$clinic['tenant']->id, 'user_id' => $clinic['user']->id, 'payload' => '', 'last_activity' => time()]);
    }
    $this->delete($base.'/sessions/synthetic-'.$this->b['tenant']->id)->assertNotFound();
    $this->delete($base.'/sessions/synthetic-'.$this->a['tenant']->id)->assertRedirectToRoute('security');
    expect(DB::table('sessions')->where('tenant_id', $this->b['tenant']->id)->count())->toBe(1);
});

it('resets a password successfully only in the token clinic and records no credential in the audit', function (): void {
    $this->mock(UncompromisedVerifier::class)->shouldReceive('verify')->andReturnTrue();
    $token = $this->context->run($this->a['tenant'], fn () => Password::broker()->createToken($this->a['user']));
    $newPassword = 'Aa1!'.Str::random(24);
    $this->postJson('http://'.$this->a['host'].'/reset-password', [
        'email' => $this->a['user']->email, 'token' => $token, 'password' => $newPassword, 'password_confirmation' => $newPassword,
    ])->assertSuccessful();
    $this->context->run($this->a['tenant'], fn () => expect(Hash::check($newPassword, $this->a['user']->fresh()->password))->toBeTrue());
    $this->context->run($this->b['tenant'], fn () => expect(Hash::check($this->b['password'], $this->b['user']->fresh()->password))->toBeTrue());
    $audit = DB::table('activity_log')->where('tenant_id', $this->a['tenant']->id)->where('event', 'password_reset')->sole();
    expect($audit->properties)->toBeNull();
    expect($audit->description)->toBe('password_reset');
});

it('throttles failed login attempts per clinic without blocking the other clinic', function (): void {
    foreach (range(1, 5) as $_) {
        $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => 'invalid'])->assertUnprocessable();
    }
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertTooManyRequests();
    $this->postJson('http://'.$this->b['host'].'/login', ['email' => $this->b['user']->email, 'password' => $this->b['password']])->assertOk();
});

it('authorizes only the current member channel within the current clinic', function (): void {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => Str::random(24),
        'broadcasting.connections.reverb.secret' => Str::random(32), 'broadcasting.connections.reverb.app_id' => 'synthetic']);
    require base_path('routes/channels.php');
    $base = 'http://'.$this->a['host'];
    $this->postJson($base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->postJson($base.'/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-tenant.'.$this->a['tenant']->id.'.users.'.$this->a['user']->id])->assertOk()->assertJsonStructure(['auth']);
    $this->postJson($base.'/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-tenant.'.$this->b['tenant']->id.'.users.'.$this->b['user']->id])->assertForbidden();
});

it('does not serialize user models or bypass tenant scopes in queued application jobs', function (): void {
    expect(ContextProbeJob::class)->not->toUseTrait(SerializesModels::class);
    expect('App\Domain')->not->toUse('Illuminate\Http');
    expect('App\Http\Controllers')->not->toUse(DB::class);
});

it('requires an initial password change and atomically revokes the other sessions', function (): void {
    $this->mock(UncompromisedVerifier::class)->shouldReceive('verify')->andReturnTrue();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['must_change_password' => true])->save());
    $base = 'http://'.$this->a['host'];
    $this->postJson($base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->get($base.'/')->assertRedirectToRoute('security');
    foreach ([$this->a, $this->b] as $clinic) {
        DB::table('sessions')->insert(['tenant_id' => $clinic['tenant']->id, 'id' => 'other-'.$clinic['tenant']->id, 'user_id' => $clinic['user']->id, 'payload' => '', 'last_activity' => time()]);
    }
    $newPassword = 'Aa1!'.Str::random(24);
    $input = ['current_password' => $this->a['password'], 'password' => $newPassword, 'password_confirmation' => $newPassword];
    $this->putJson($base.'/user/password', ['current_password' => 'invalid'] + $input)->assertUnprocessable();
    $this->putJson($base.'/user/password', $input)->assertOk();
    $this->context->run($this->a['tenant'], function () use ($newPassword): void {
        $user = $this->a['user']->fresh();
        expect($user->must_change_password)->toBeFalse();
        expect(Hash::check($newPassword, $user->password))->toBeTrue();
    });
    expect(DB::table('sessions')->where('tenant_id', $this->a['tenant']->id)->count())->toBe(0);
    expect(DB::table('sessions')->where('tenant_id', $this->b['tenant']->id)->count())->toBe(1);
    $this->get($base.'/')->assertOk();
});

it('refuses homologation fixtures outside the isolated staging environment', function (): void {
    expect(fn () => app(HomologationSeeder::class)->run())->toThrow(RuntimeException::class);
});

it('refuses the live homologation check outside its isolated environment', function (bool $registries, bool $stage3, bool $orders): void {
    $this->artisan('homologation:check', ['--registries' => $registries, '--stage3' => $stage3, '--orders' => $orders])
        ->expectsOutputToContain('This command requires the isolated local homologation database.')
        ->assertFailed();
})->with([[false, false, false], [true, false, false], [false, true, false], [true, true, false], [false, false, true], [true, false, true], [false, true, true], [true, true, true]]);

it('refuses homologation migrations outside the isolated environment', function (): void {
    $this->artisan('homologation:migrate')
        ->expectsOutputToContain('This command requires the isolated local homologation database.')
        ->assertFailed();
});

it('rejects connection overrides before running homologation fixtures', function (string $setting, mixed $value): void {
    $previousEnvironment = app()->environment();
    $previousDatabase = config('database');
    try {
        app()->instance('env', 'staging');
        config([
            'database.default' => 'mariadb',
            'database.connections.mariadb.host' => 'db',
            'database.connections.mariadb.database' => 'novo_sistema_clinica_homologacao',
            $setting => $value,
        ]);
        expect(fn () => app(LocalHomologation::class)->ensure())->toThrow(RuntimeException::class);
        $this->artisan('homologation:migrate')
            ->expectsOutputToContain('This command requires the isolated local homologation database.')
            ->assertFailed();
    } finally {
        app()->instance('env', $previousEnvironment);
        config(['database' => $previousDatabase]);
    }
})->with([
    'URL overrides the configured host' => ['database.connections.mariadb.url', 'mysql://invalid.example/another_database'],
    'another default connection' => ['database.default', 'mysql'],
    'previously resolved connection targets another database' => ['database.connections.mariadb.url', null],
]);

it('refuses stage three synthetic grants and cleanup outside isolated staging', function (): void {
    $unexpected = fn () => throw new LogicException('No HTTP or fixture callback may run here.');
    expect(fn () => new StageThreeHomologation($unexpected, $unexpected, $unexpected)->run([]))->toThrow(RuntimeException::class);
});

it('refuses order synthetic fixtures and cleanup outside isolated staging before HTTP', function (): void {
    $unexpected = fn () => throw new LogicException('No HTTP or fixture callback may run here.');
    expect(fn () => new OrderHomologation($unexpected, $unexpected, $unexpected)->run([]))->toThrow(RuntimeException::class);
});
