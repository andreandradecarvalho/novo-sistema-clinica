<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Professionals\Actions\SaveProfessional;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Tenancy\Actions\SaveUnit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\QueryException;
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
    foreach ([$this->a, $this->b] as $clinic) {
        $this->context->run($clinic['tenant'], function () use ($clinic): void {
            $clinic['user']->givePermissionTo(['units.view', 'units.manage', 'professionals.view', 'professionals.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    $this->base = 'http://'.$this->a['host'];
    $this->postJson($this->base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates edits and inactivates clinical units without deleting their identity', function (): void {
    $this->post($this->base.'/units', ['name' => 'Unidade sintética', 'city' => 'Cidade teste', 'state' => 'mg', 'is_active' => 1])->assertRedirect();
    $unit = $this->context->run($this->a['tenant'], fn () => Unit::query()->sole());
    expect($unit->state)->toBe('MG');
    $this->get($this->base.'/units/'.$unit->public_id)->assertOk()->assertSee('Unidade sintética');
    $this->get($this->base.'/units/'.$unit->public_id.'/edit')->assertOk()->assertSee('Editar unidade clínica');
    $this->put($this->base.'/units/'.$unit->public_id, ['name' => 'Unidade corrigida', 'is_active' => 0])->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($unit): void {
        expect($unit->fresh()->is_active)->toBeFalse();
        expect($unit->fresh()->public_id)->toBe($unit->public_id);
    });
    $events = DB::table('activity_log')->where('log_name', 'units')->get();
    expect($events->pluck('event')->all())->toBe(['unit_created', 'unit_viewed', 'unit_edit_viewed', 'unit_updated']);
    expect($events->pluck('properties')->filter())->toBeEmpty();
});

it('isolates clinical unit routes and validates ownership UF and database length', function (): void {
    $unit = $this->context->run($this->b['tenant'], fn () => app(SaveUnit::class)->execute(['name' => 'Unidade B', 'is_active' => true]));
    $this->get($this->base.'/units')->assertOk()->assertDontSee('Unidade B');
    $this->get($this->base.'/units/'.$unit->public_id)->assertNotFound();
    $this->putJson($this->base.'/units/'.$unit->public_id, ['name' => 'Inválido', 'is_active' => 1])->assertNotFound();
    $this->postJson($this->base.'/units', ['name' => 'Inválido', 'is_active' => 1, 'tenant_id' => $this->b['tenant']->id])->assertUnprocessable();
    $this->postJson($this->base.'/units', ['name' => str_repeat('x', 151), 'state' => 'ZZ', 'is_active' => 1])->assertUnprocessable()->assertJsonValidationErrors(['name', 'state']);
    $this->get($this->base.'/units/create')->assertOk()->assertSee('Nova unidade clínica');
    expect(fn () => Unit::query()->count())->toThrow(MissingTenant::class);
});

it('registers a professional for a local user without granting roles or clinical permissions', function (): void {
    $this->get($this->base.'/professionals/create')->assertOk()->assertSee('Integrantes disponíveis');
    $this->get($this->base.'/professionals/register/'.$this->a['user']->public_id)->assertOk()->assertSee('Registro profissional');
    $this->post($this->base.'/professionals', ['user_id' => $this->a['user']->id, 'council' => 'crm', 'council_uf' => 'sp', 'council_number' => 'SINT-123', 'specialty' => 'Especialidade sintética'])->assertRedirect();
    $professional = $this->context->run($this->a['tenant'], fn () => Professional::query()->sole());
    expect($professional->council)->toBe('CRM');
    expect($professional->council_uf)->toBe('SP');
    $this->context->run($this->a['tenant'], function (): void {
        $user = $this->a['user']->fresh();
        expect($user->roles)->toHaveCount(0);
        expect($user->can('clinical.access'))->toBeFalse();
        expect($user->password)->toBe($this->a['user']->password);
    });
    $this->get($this->base.'/professionals/'.$professional->id)->assertOk()->assertSee('SINT-123');
    $this->get($this->base.'/professionals/'.$professional->id.'/edit')->assertOk()->assertSee('Editar profissional');
    $this->put($this->base.'/professionals/'.$professional->id, ['specialty' => 'Especialidade corrigida'])->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => expect($professional->fresh()->specialty)->toBe('Especialidade corrigida'));
    $this->postJson($this->base.'/professionals', ['user_id' => $this->a['user']->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->get($this->base.'/professionals/register/'.$this->a['user']->public_id)->assertNotFound();
    $this->get($this->base.'/professionals/create')->assertOk()->assertSee('Nenhum integrante disponível');
    $audit = DB::table('activity_log')->where('log_name', 'professionals')->get();
    expect($audit->pluck('properties')->filter())->toBeEmpty();
});

it('rejects a user from another clinic in professional selection writes and foreign keys', function (): void {
    $this->get($this->base.'/professionals/register/'.$this->b['user']->public_id)->assertNotFound();
    $this->postJson($this->base.'/professionals', ['user_id' => $this->b['user']->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => app(SaveProfessional::class)->execute(['user_id' => $this->b['user']->id]))->toThrow(ValidationException::class);
        expect(fn () => DB::table('professionals')->insert(['tenant_id' => $this->a['tenant']->id, 'user_id' => $this->b['user']->id]))->toThrow(QueryException::class);
    });
    expect(fn () => Professional::query()->count())->toThrow(MissingTenant::class);
});

it('keeps professional ownership immutable and denies foreign professional bindings', function (): void {
    $professionalA = $this->context->run($this->a['tenant'], fn () => app(SaveProfessional::class)->execute(['user_id' => $this->a['user']->id]));
    $professionalB = $this->context->run($this->b['tenant'], fn () => app(SaveProfessional::class)->execute(['user_id' => $this->b['user']->id]));
    $this->get($this->base.'/professionals/'.$professionalB->id)->assertNotFound();
    $this->get($this->base.'/professionals/'.$professionalB->id.'/edit')->assertNotFound();
    $this->putJson($this->base.'/professionals/'.$professionalB->id, ['specialty' => 'Inválido'])->assertNotFound();
    $this->putJson($this->base.'/professionals/'.$professionalA->id, ['user_id' => $this->b['user']->id])->assertUnprocessable();
    $this->context->run($this->a['tenant'], function () use ($professionalA): void {
        expect(fn () => app(SaveProfessional::class)->execute(['user_id' => $this->b['user']->id], $professionalA))->toThrow(LogicException::class);
        expect(fn () => $professionalA->forceFill(['user_id' => $this->b['user']->id])->save())->toThrow(LogicException::class);
    });
});

it('excludes inactive users from professional registration and validates the council field group', function (): void {
    $inactive = $this->context->run($this->a['tenant'], function (): User {
        $user = User::query()->create(['name' => 'Integrante inativo sintético', 'email' => 'inativo@example.test', 'password' => $this->a['password']]);
        $user->forceFill(['is_active' => false])->save();

        return $user;
    });
    $this->get($this->base.'/professionals/create')->assertOk()->assertDontSee('Integrante inativo sintético');
    $this->get($this->base.'/professionals/register/'.$inactive->public_id)->assertNotFound();
    $this->postJson($this->base.'/professionals', ['user_id' => $inactive->id])->assertUnprocessable();
    $this->postJson($this->base.'/professionals', ['user_id' => $this->a['user']->id, 'council' => 'CRM'])->assertUnprocessable()->assertJsonValidationErrors(['council_uf', 'council_number']);
    $this->postJson($this->base.'/professionals', ['user_id' => $this->a['user']->id, 'council' => 'CRM', 'council_uf' => 'ZZ', 'council_number' => 'SINT-123'])->assertUnprocessable()->assertJsonValidationErrors('council_uf');
    $this->postJson($this->base.'/professionals', ['user_id' => $this->a['user']->id, 'roles' => ['doctor']])->assertUnprocessable();
});

it('enforces directory view manage and second factor permissions independently', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->revokePermissionTo(['units.manage', 'professionals.manage']);
    });
    $this->get($this->base.'/units')->assertOk();
    $this->get($this->base.'/professionals')->assertOk();
    $this->postJson($this->base.'/units', ['name' => 'Inválido', 'is_active' => 1])->assertForbidden();
    $this->postJson($this->base.'/professionals', ['user_id' => $this->a['user']->id])->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo(['units.view', 'professionals.view']));
    $this->get($this->base.'/units')->assertForbidden();
    $this->get($this->base.'/professionals')->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['units.view', 'professionals.view']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->base.'/units')->assertForbidden();
    $this->get($this->base.'/professionals')->assertForbidden();
});
