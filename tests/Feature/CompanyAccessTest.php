<?php

declare(strict_types=1);

use App\Domain\Companies\Actions\GrantCompanyAccess;
use App\Domain\Companies\Actions\RevokeCompanyAccess;
use App\Domain\Companies\Actions\SaveCompany;
use App\Domain\Companies\Enums\CompanyAccessScope;
use App\Domain\Companies\Models\CompanyAccess;
use App\Domain\Companies\Services\CompanyAccessAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Clinics;

beforeEach(function (): void {
    $this->withoutVite();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    DB::beginTransaction();
    $this->seed();
    $this->context = app(TenantContext::class);
    $this->a = Clinics::create();
    $this->b = Clinics::create();
    $this->company = $this->context->run($this->a['tenant'], function () {
        $this->a['user']->givePermissionTo(['companies.view', 'company_access.view', 'company_access.manage', 'users.view']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->member = User::query()->create(['name' => 'Representante sintético', 'email' => 'representante@example.test', 'password' => $this->a['password']]);

        return app(SaveCompany::class)->execute(['name' => 'Empregador sintético A', 'is_active' => true]);
    });
    $this->foreignCompany = $this->context->run($this->b['tenant'], fn () => app(SaveCompany::class)->execute(['name' => 'Empregador sintético B', 'is_active' => true]));
    $this->base = 'http://'.$this->a['host'];
    $this->url = $this->base.'/companies/'.$this->company->public_id.'/accesses';
    $this->input = ['user_id' => $this->member->id, 'scope' => 'occupational_status', 'valid_from' => '2026-11-03T09:00', 'valid_until' => '2026-11-03T18:00'];
    $this->postJson($this->base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->postJson($this->base.'/user/confirm-password', ['password' => $this->a['password']])->assertSuccessful();
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates scoped grants with clinic timezone without granting roles or clinical permissions', function (): void {
    $this->get($this->url.'/create')->assertOk()->assertSee('Representante sintético')->assertSee('America/Sao_Paulo')->assertDontSee('value="'.$this->b['user']->id.'"', false);
    $this->post($this->url, $this->input)->assertRedirect();
    $access = $this->context->run($this->a['tenant'], fn () => CompanyAccess::query()->sole());
    expect($access->valid_from->format('Y-m-d H:i:s'))->toBe('2026-11-03 12:00:00');
    expect($access->valid_until->format('Y-m-d H:i:s'))->toBe('2026-11-03 21:00:00');
    $this->get($this->url)->assertOk()->assertSee('Representante sintético')->assertSee('03/11/2026 09:00')->assertSee('Situação operacional ocupacional');
    $this->context->run($this->a['tenant'], function (): void {
        $member = $this->member->fresh();
        expect($member->roles)->toHaveCount(0);
        expect($member->permissions)->toHaveCount(0);
        expect($member->password)->toBe($this->member->password);
        expect($member->can('patients.view'))->toBeFalse();
    });
    expect(DB::table('activity_log')->where('log_name', 'company_access')->pluck('properties')->filter())->toBeEmpty();
});

it('rejects foreign company bindings users and parent injection including raw foreign keys', function (): void {
    $this->get($this->base.'/companies/'.$this->foreignCompany->public_id.'/accesses')->assertNotFound();
    $this->postJson($this->url, array_replace($this->input, ['user_id' => $this->b['user']->id]))->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->postJson($this->url, $this->input + ['tenant_id' => $this->b['tenant']->id, 'company_id' => $this->foreignCompany->id, 'permissions' => ['patients.view']])->assertUnprocessable();
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => app(GrantCompanyAccess::class)->execute($this->foreignCompany, $this->input))->toThrow(ValidationException::class);
        expect(fn () => DB::table('company_user_access')->insert(['tenant_id' => $this->a['tenant']->id, 'user_id' => $this->b['user']->id, 'company_id' => $this->company->id, 'valid_from' => now()]))->toThrow(QueryException::class);
    });
    expect(fn () => CompanyAccess::query()->count())->toThrow(MissingTenant::class);
});

it('rejects invalid scopes periods and inactive or deleted principals', function (): void {
    $this->postJson($this->url, array_replace($this->input, ['scope' => 'full_medical_record']))->assertUnprocessable();
    $this->postJson($this->url, array_replace($this->input, ['valid_until' => '2026-11-02T18:00']))->assertUnprocessable();
    $this->postJson($this->url, array_replace($this->input, ['valid_from' => '2026-02-30T09:00']))->assertUnprocessable();
    $this->context->run($this->a['tenant'], function (): void {
        $this->member->forceFill(['is_active' => false])->save();
        expect(fn () => app(GrantCompanyAccess::class)->execute($this->company, $this->input))->toThrow(ValidationException::class);
        expect(fn () => DB::table('company_user_access')->insert(['tenant_id' => $this->a['tenant']->id, 'user_id' => $this->member->id, 'company_id' => $this->company->id, 'valid_from' => '2026-11-03 12:00:00', 'valid_until' => '2026-11-02 12:00:00']))->toThrow(QueryException::class);
    });
    $this->postJson($this->url, $this->input)->assertUnprocessable();
    $this->context->run($this->a['tenant'], function (): void {
        $this->member->forceFill(['is_active' => true])->save();
        $this->member->delete();
    });
    $this->postJson($this->url, $this->input)->assertUnprocessable();
    $this->context->run($this->a['tenant'], fn () => $this->company->forceFill(['is_active' => false])->save());
    $this->get($this->url)->assertOk();
    $this->postJson($this->url, $this->input)->assertForbidden();
});

it('revokes once while preserving immutable history and exact company binding', function (): void {
    $access = $this->context->run($this->a['tenant'], fn () => app(GrantCompanyAccess::class)->execute($this->company, $this->input));
    $other = $this->context->run($this->a['tenant'], fn () => app(SaveCompany::class)->execute(['name' => 'Outro empregador A', 'is_active' => true]));
    $this->postJson($this->base.'/companies/'.$other->public_id.'/accesses/'.$access->id.'/revoke')->assertNotFound();
    $this->context->run($this->a['tenant'], function () use ($other, $access): void {
        expect(fn () => app(RevokeCompanyAccess::class)->execute($other, $access))->toThrow(ModelNotFoundException::class);
        expect(fn () => $access->forceFill(['scope' => 'released_aso'])->save())->toThrow(LogicException::class);
        expect(fn () => $access->delete())->toThrow(LogicException::class);
        $this->company->forceFill(['is_active' => false])->save();
    });
    $this->post($this->url.'/'.$access->id.'/revoke')->assertRedirect();
    $this->post($this->url.'/'.$access->id.'/revoke')->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($access): void {
        $current = $access->fresh();
        expect($current->revoked_at)->not->toBeNull();
        expect($current->scope)->toBe(CompanyAccessScope::OccupationalStatus);
        expect($current->valid_until->format('H:i'))->toBe('21:00');
        expect(fn () => $current->forceFill(['revoked_at' => null])->save())->toThrow(LogicException::class);
    });
    expect(DB::table('activity_log')->where('event', 'company_access_revoked')->count())->toBe(1);
});

it('checks inclusive temporal boundaries category permission revocation and exact company without caching', function (): void {
    $access = $this->context->run($this->a['tenant'], fn () => app(GrantCompanyAccess::class)->execute($this->company, $this->input));
    $this->context->run($this->a['tenant'], function () use ($access): void {
        $authorization = app(CompanyAccessAuthorization::class);
        $allows = fn () => $authorization->allows($this->member, $this->company, CompanyAccessScope::OccupationalStatus);
        $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00:00', 'UTC'));
        expect($allows())->toBeFalse();
        $this->member->givePermissionTo('company_portal.status.view');
        expect($allows())->toBeFalse();
        $this->member->forceFill(['two_factor_confirmed_at' => now()])->save();
        expect($allows())->toBeTrue();
        expect($authorization->allows($this->member, $this->company, CompanyAccessScope::ReleasedAso))->toBeFalse();
        expect($authorization->allows($this->member, $this->foreignCompany, CompanyAccessScope::OccupationalStatus))->toBeFalse();
        expect($authorization->allows($this->b['user'], $this->company, CompanyAccessScope::OccupationalStatus))->toBeFalse();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 11:59:59', 'UTC'));
        expect($allows())->toBeFalse();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 21:00:00', 'UTC'));
        expect($allows())->toBeTrue();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 21:00:01', 'UTC'));
        expect($allows())->toBeFalse();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00:00', 'UTC'));
        $this->member->revokePermissionTo('company_portal.status.view');
        expect($allows())->toBeFalse();
        $this->member->givePermissionTo('company_portal.status.view');
        app(RevokeCompanyAccess::class)->execute($this->company, $access);
        expect($allows())->toBeFalse();
    });
});

it('denies stale active objects after user company or two factor is disabled', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00:00', 'UTC'));
        app(GrantCompanyAccess::class)->execute($this->company, array_replace($this->input, ['valid_until' => null]));
        $this->member->givePermissionTo('company_portal.status.view');
        $this->member->forceFill(['two_factor_confirmed_at' => now()])->save();
        $allows = fn () => app(CompanyAccessAuthorization::class)->allows($this->member, $this->company, CompanyAccessScope::OccupationalStatus);
        expect($allows())->toBeTrue();
        $this->member->fresh()->forceFill(['is_active' => false])->save();
        expect($allows())->toBeFalse();
        $this->member->fresh()->forceFill(['is_active' => true, 'two_factor_confirmed_at' => null])->save();
        expect($allows())->toBeFalse();
        $this->member->fresh()->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->company->fresh()->forceFill(['is_active' => false])->save();
        expect($allows())->toBeFalse();
    });
});

it('requires separate management permissions second factor and password confirmation', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('company_access.manage'));
    $this->get($this->url)->assertOk();
    $this->postJson($this->url, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('company_access.manage');
        $this->a['user']->revokePermissionTo('company_access.view');
    });
    $this->get($this->url)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('company_access.view');
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->url)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save());
    $this->withSession(['auth.password_confirmed_at' => 0])->postJson($this->url, $this->input)->assertStatus(423);
});

it('rolls back grants and revocations when audit persistence fails', function (): void {
    $access = $this->context->run($this->a['tenant'], fn () => app(GrantCompanyAccess::class)->execute($this->company, $this->input));
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    try {
        $this->context->run($this->a['tenant'], function () use ($access): void {
            expect(fn () => app(GrantCompanyAccess::class)->execute($this->company, $this->input))->toThrow(RuntimeException::class);
            expect(CompanyAccess::query()->count())->toBe(1);
            expect(fn () => app(RevokeCompanyAccess::class)->execute($this->company, $access))->toThrow(RuntimeException::class);
            expect($access->fresh()->revoked_at)->toBeNull();
        });
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});
