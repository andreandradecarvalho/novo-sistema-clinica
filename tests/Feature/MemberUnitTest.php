<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\AssignMemberUnit;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Actions\SaveUnit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    $this->unit = $this->context->run($this->a['tenant'], function () {
        $this->a['user']->givePermissionTo(['users.view', 'users.manage', 'units.view']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->member = User::query()->create(['name' => 'Integrante sintético', 'email' => 'membro@example.test', 'password' => $this->a['password']]);

        return app(SaveUnit::class)->execute(['name' => 'Unidade sintética A', 'is_active' => true]);
    });
    $this->foreignUnit = $this->context->run($this->b['tenant'], fn () => app(SaveUnit::class)->execute(['name' => 'Unidade sintética B', 'is_active' => true]));
    $this->base = 'http://'.$this->a['host'];
    $this->url = $this->base.'/users/'.$this->member->public_id.'/unit';
    $this->postJson($this->base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->postJson($this->base.'/user/confirm-password', ['password' => $this->a['password']])->assertSuccessful();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('assigns transfers and clears units while preserving credentials and permissions', function (): void {
    $this->get($this->url)->assertOk()->assertSee('Unidade sintética A')->assertDontSee('Unidade sintética B');
    $this->put($this->url, ['unit_id' => $this->unit->id])->assertRedirect();
    $this->get($this->base.'/users/'.$this->member->public_id)->assertOk()->assertSee('Unidade sintética A')->assertSee('Alterar unidade');
    $next = $this->context->run($this->a['tenant'], fn () => app(SaveUnit::class)->execute(['name' => 'Outra unidade sintética', 'is_active' => true]));
    $this->put($this->url, ['unit_id' => $next->id])->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($next): void {
        $member = $this->member->fresh();
        expect((int) $member->unit_id)->toBe((int) $next->id);
        expect($member->password)->toBe($this->member->password);
        expect($member->roles)->toHaveCount(0);
        expect($member->can('clinical.access'))->toBeFalse();
    });
    $this->put($this->url, ['unit_id' => ''])->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => expect($this->member->fresh()->unit_id)->toBeNull());
    $events = DB::table('activity_log')->where('event', 'member_unit_changed')->orderBy('id')->get();
    expect($events)->toHaveCount(3);
    expect(json_decode($events[0]->properties, true))->toBe(['previous_unit_id' => null, 'unit_id' => (int) $this->unit->id]);
    expect(json_decode($events[1]->properties, true))->toBe(['previous_unit_id' => (int) $this->unit->id, 'unit_id' => (int) $next->id]);
    expect(json_decode($events[2]->properties, true))->toBe(['previous_unit_id' => (int) $next->id, 'unit_id' => null]);
});

it('revokes only the changed members sessions and rotates the remember token', function (): void {
    foreach ([[$this->a, $this->member, 'member-session'], [$this->a, $this->a['user'], 'actor-session'], [$this->b, $this->b['user'], 'foreign-session']] as [$clinic, $member, $id]) {
        DB::table('sessions')->insert(['id' => $id, 'tenant_id' => $clinic['tenant']->id, 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
    }
    $this->put($this->url, ['unit_id' => $this->unit->id])->assertRedirect();
    expect(DB::table('sessions')->where('id', 'member-session')->exists())->toBeFalse();
    expect(DB::table('sessions')->where('id', 'actor-session')->exists())->toBeTrue();
    expect(DB::table('sessions')->where('id', 'foreign-session')->exists())->toBeTrue();
    $this->context->run($this->a['tenant'], function (): void {
        $token = $this->member->fresh()->remember_token;
        expect($token)->toHaveLength(60);
        DB::table('sessions')->insert(['id' => 'new-member-session', 'tenant_id' => $this->a['tenant']->id, 'user_id' => $this->member->id, 'payload' => '', 'last_activity' => time()]);
        expect(app(AssignMemberUnit::class)->execute($this->member, (int) $this->unit->id))->toBeFalse();
        expect($this->member->fresh()->remember_token)->toBe($token);
        expect(DB::table('sessions')->where('id', 'new-member-session')->exists())->toBeTrue();
    });
});

it('rejects foreign bindings foreign units missing context and ownership injection', function (): void {
    $this->get($this->base.'/users/'.$this->b['user']->public_id.'/unit')->assertNotFound();
    $this->putJson($this->base.'/users/'.$this->b['user']->public_id.'/unit', ['unit_id' => $this->unit->id])->assertNotFound();
    $this->putJson($this->url, ['unit_id' => $this->foreignUnit->id])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
    $this->putJson($this->url, ['unit_id' => $this->unit->id, 'tenant_id' => $this->b['tenant']->id, 'roles' => ['clinic_admin']])->assertUnprocessable();
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => app(AssignMemberUnit::class)->execute($this->b['user'], (int) $this->unit->id))->toThrow(ModelNotFoundException::class);
        expect(fn () => $this->member->forceFill(['unit_id' => $this->foreignUnit->id])->save())->toThrow(QueryException::class);
    });
    expect(fn () => app(AssignMemberUnit::class)->execute($this->member, null))->toThrow(MissingTenant::class);
});

it('rejects inactive deleted and nonexistent units and inactive members', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->unit->forceFill(['is_active' => false])->save());
    $this->get($this->url)->assertOk()->assertDontSee('Unidade sintética A');
    $this->putJson($this->url, ['unit_id' => $this->unit->id])->assertUnprocessable();
    $this->putJson($this->url, ['unit_id' => 0])->assertUnprocessable();
    $this->context->run($this->a['tenant'], fn () => $this->unit->delete());
    $this->putJson($this->url, ['unit_id' => $this->unit->id])->assertUnprocessable();
    $this->context->run($this->a['tenant'], fn () => $this->member->forceFill(['is_active' => false])->save());
    $this->get($this->url)->assertForbidden();
    $this->putJson($this->url, ['unit_id' => null])->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => expect(fn () => app(AssignMemberUnit::class)->execute($this->member, null))->toThrow(ValidationException::class));
});

it('requires explicit selection management permission second factor and recent password', function (): void {
    $this->putJson($this->url, [])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('users.manage'));
    $this->get($this->url)->assertForbidden();
    $this->putJson($this->url, ['unit_id' => $this->unit->id])->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('users.manage');
        $this->a['user']->revokePermissionTo('units.view');
    });
    $this->get($this->url)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('units.view');
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->url)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save());
    $this->withSession(['auth.password_confirmed_at' => 0])->putJson($this->url, ['unit_id' => $this->unit->id])->assertStatus(423);
});

it('logs out the actor when changing their own unit', function (): void {
    $this->put($this->base.'/users/'.$this->a['user']->public_id.'/unit', ['unit_id' => $this->unit->id])->assertRedirect($this->base.'/login');
    $this->assertGuest();
    $this->context->run($this->a['tenant'], fn () => expect((int) $this->a['user']->fresh()->unit_id)->toBe((int) $this->unit->id));
});

it('rolls back the assignment token and session revocation if audit persistence fails', function (): void {
    DB::table('sessions')->insert(['id' => 'rollback-session', 'tenant_id' => $this->a['tenant']->id, 'user_id' => $this->member->id, 'payload' => '', 'last_activity' => time()]);
    app()->instance(RegistryAudit::class, new class
    {
        public function recordMemberUnitChange(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    try {
        $this->context->run($this->a['tenant'], function (): void {
            expect(fn () => app(AssignMemberUnit::class)->execute($this->member, (int) $this->unit->id))->toThrow(RuntimeException::class);
            $member = $this->member->fresh();
            expect($member->unit_id)->toBeNull();
            expect($member->remember_token)->toBe($this->member->remember_token);
        });
        expect(DB::table('sessions')->where('id', 'rollback-session')->exists())->toBeTrue();
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});
