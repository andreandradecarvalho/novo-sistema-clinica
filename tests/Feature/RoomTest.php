<?php

declare(strict_types=1);

use App\Domain\Scheduling\Actions\ListRooms;
use App\Domain\Scheduling\Actions\ReadRoom;
use App\Domain\Scheduling\Actions\SaveRoom;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
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
    foreach (['a', 'b'] as $key) {
        $clinic = $this->$key;
        $this->{'data'.strtoupper($key)} = $this->context->run($clinic['tenant'], function () use ($clinic): array {
            $clinic['user']->givePermissionTo(['units.view', 'rooms.view', 'rooms.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
            auth()->setUser($clinic['user']->fresh());
            $unit = Unit::query()->create(['name' => 'Unidade sintética']);
            $other = Unit::query()->create(['name' => 'Outra unidade sintética']);
            $room = app(SaveRoom::class)->execute($unit, ['room_key' => 'ROOM-01', 'name' => 'Sala sintética', 'ordem' => 1, 'is_active' => true]);

            return compact('unit', 'other', 'room');
        });
    }
    $this->base = 'http://'.$this->a['host'].'/units/'.$this->dataA['unit']->public_id.'/rooms';
    $this->input = ['room_key' => 'room-02', 'name' => ' Sala sintética <script>alert(1)</script> ', 'ordem' => 2, 'is_active' => '1'];
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates reads edits deactivates and reactivates a room without changing its unit or credentials', function (): void {
    $this->get('http://'.$this->a['host'].'/units/'.$this->dataA['unit']->public_id)->assertOk()->assertSee('Salas da unidade');
    $this->get($this->base.'/create')->assertOk();
    $response = $this->post($this->base, $this->input);
    $room = $this->context->run($this->a['tenant'], fn () => Room::query()->where('room_key', 'ROOM-02')->sole());
    $url = $this->base.'/'.$room->id;
    $response->assertRedirect($url);
    $this->get($url)->assertOk()->assertSee(trim($this->input['name']))->assertDontSee(trim($this->input['name']), false)->assertHeader('Cache-Control', 'no-store, private');
    $this->get($url.'/edit')->assertOk();
    $this->put($url, ['name' => 'Nome atualizado', 'is_active' => '0', 'ordem' => 0] + $this->input)->assertRedirect($url);
    $this->get($url)->assertOk()->assertSee('Nome atualizado')->assertSee('Inativa');
    $this->put($url, ['name' => 'Nome atualizado', 'is_active' => '1'] + $this->input)->assertRedirect($url);
    $this->context->run($this->a['tenant'], function () use ($room): void {
        $fresh = $room->fresh();
        expect($fresh->unit_id)->toBe($this->dataA['unit']->id)->and($fresh->is_active)->toBeTrue();
        expect($fresh->pin_hash)->toBeNull()->and($fresh->toArray())->not->toHaveKey('pin_hash');
    });
    expect(DB::table('activity_log')->where('tenant_id', $this->a['tenant']->id)->where('log_name', 'rooms')->pluck('properties')->filter())->toBeEmpty();
});

it('keeps normalized keys unique across units of one clinic including inactive rooms', function (): void {
    $this->postJson($this->base, ['room_key' => ' room-01 '] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('room_key');
    $other = 'http://'.$this->a['host'].'/units/'.$this->dataA['other']->public_id.'/rooms';
    $this->postJson($other, ['room_key' => 'ROOM-01'] + $this->input)->assertUnprocessable();
    $this->put($this->base.'/'.$this->dataA['room']->id, ['room_key' => 'ROOM-01', 'is_active' => '0'] + $this->input)->assertRedirect();
    $this->postJson($other, ['room_key' => 'ROOM-01'] + $this->input)->assertUnprocessable();
    $this->context->run($this->b['tenant'], fn () => expect(Room::query()->where('room_key', 'ROOM-01')->count())->toBe(1));
});

it('validates fields and refuses ownership or PIN injection including direct action calls', function (): void {
    foreach (['room_key' => 'INVALID KEY', 'name' => str_repeat('x', 101), 'ordem' => 65536, 'is_active' => 3,
        'unit_id' => $this->dataB['unit']->id, 'tenant_id' => $this->b['tenant']->id, 'pin' => 'synthetic', 'pin_hash' => 'synthetic', 'id' => 1] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(SaveRoom::class)->execute($this->dataA['unit'], ['pin_hash' => 'synthetic'] + $this->input))->toThrow(ValidationException::class);
    });
});

it('requires an active unit to create or activate while allowing administrative deactivation', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->dataA['unit']->update(['is_active' => false]));
    $this->postJson($this->base, $this->input)->assertUnprocessable()->assertJsonValidationErrors('room');
    $url = $this->base.'/'.$this->dataA['room']->id;
    $this->putJson($url, $this->input)->assertUnprocessable();
    $this->put($url, ['is_active' => '0'] + $this->input)->assertRedirect();
    $this->putJson($url, ['is_active' => '1'] + $this->input)->assertUnprocessable();
    $this->get($url)->assertOk()->assertSee('Inativa');
});

it('scopes routes reads and actions to the exact unit and tenant and fails without context', function (): void {
    $this->getJson($this->base.'/'.$this->dataB['room']->id)->assertNotFound();
    $this->putJson($this->base.'/'.$this->dataB['room']->id, $this->input)->assertNotFound();
    $foreign = 'http://'.$this->a['host'].'/units/'.$this->dataB['unit']->public_id.'/rooms';
    $this->getJson($foreign)->assertNotFound();
    $this->postJson($foreign, $this->input)->assertNotFound();
    $wrongUnit = 'http://'.$this->a['host'].'/units/'.$this->dataA['other']->public_id.'/rooms/'.$this->dataA['room']->id;
    $this->getJson($wrongUnit)->assertNotFound();
    $this->putJson($wrongUnit, $this->input)->assertNotFound();
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(SaveRoom::class)->execute($this->dataA['other'], $this->input, $this->dataA['room']))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(ReadRoom::class)->execute($this->dataA['unit'], $this->dataB['room']))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(SaveRoom::class)->execute($this->dataB['unit'], $this->input))->toThrow(ModelNotFoundException::class);
    });
    expect(fn () => Room::query()->first())->toThrow(MissingTenant::class);
    expect(fn () => app(ListRooms::class)->execute($this->dataA['unit']))->toThrow(MissingTenant::class);
});

it('rechecks management reading active actor and second factor permissions', function (): void {
    $url = $this->base.'/'.$this->dataA['room']->id;
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('rooms.manage'));
    $this->get($url)->assertOk()->assertDontSee('Editar sala');
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->putJson($url, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('rooms.manage');
        $this->a['user']->revokePermissionTo('rooms.view');
    });
    $this->getJson($this->base)->assertForbidden();
    $this->putJson($url, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('rooms.view');
        foreach ([['is_active' => false], ['is_active' => true, 'two_factor_confirmed_at' => null]] as $change) {
            $this->a['user']->forceFill($change)->save();
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(SaveRoom::class)->execute($this->dataA['unit'], $this->input, $this->dataA['room']))->toThrow(AuthorizationException::class);
        }
    });
});

it('paginates and orders rooms only within the selected unit', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        foreach (range(1, 21) as $n) {
            app(SaveRoom::class)->execute($this->dataA['unit'], ['room_key' => 'PAGE-'.$n, 'name' => 'Sala paginada', 'ordem' => $n, 'is_active' => true]);
        }
        app(SaveRoom::class)->execute($this->dataA['other'], ['room_key' => 'OTHER', 'name' => 'Sala fora da unidade', 'ordem' => 0, 'is_active' => true]);
        $list = app(ListRooms::class)->execute($this->dataA['unit']);
        expect($list->total())->toBe(22)->and($list->count())->toBe(20);
        expect($list->getCollection()->pluck('unit_id')->unique()->all())->toBe([$this->dataA['unit']->id]);
        expect($list->getCollection()->pluck('ordem')->all())->toBe($list->getCollection()->pluck('ordem')->sort()->values()->all());
    });
    $this->get($this->base)->assertOk()->assertDontSee('Sala fora da unidade')->assertSee('page=2', false);
});

it('retains room history and enforces tenant foreign keys and model ownership', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        $room = $this->dataA['room']->fresh();
        expect(fn () => $room->forceFill(['unit_id' => $this->dataA['other']->id])->save())->toThrow(LogicException::class);
        expect(fn () => $room->fresh()->forceFill(['pin_hash' => 'synthetic'])->save())->toThrow(LogicException::class);
        expect(fn () => $room->fresh()->delete())->toThrow(LogicException::class);
    });
    expect(fn () => DB::table('rooms')->where('id', $this->dataA['room']->id)->update(['unit_id' => $this->dataB['unit']->id]))->toThrow(QueryException::class);
    $this->context->run($this->a['tenant'], fn () => $this->dataA['unit']->delete());
    $this->getJson($this->base)->assertNotFound();
    $this->putJson($this->base.'/'.$this->dataA['room']->id, $this->input)->assertNotFound();
    expect(DB::table('rooms')->where('id', $this->dataA['room']->id)->exists())->toBeTrue();
});

it('rolls back both creates and edits when audit persistence fails', function (): void {
    $before = DB::table('rooms')->where('id', $this->dataA['room']->id)->first();
    app()->instance(RegistryAudit::class, new class
    {
        public function record(...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure.');
        }
    });
    try {
        $this->context->run($this->a['tenant'], function (): void {
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(SaveRoom::class)->execute($this->dataA['unit'], $this->input))->toThrow(RuntimeException::class);
            expect(fn () => app(SaveRoom::class)->execute($this->dataA['unit'], $this->input, $this->dataA['room']))->toThrow(RuntimeException::class);
        });
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
    expect(DB::table('rooms')->where('id', $this->dataA['room']->id)->first())->toEqual($before);
    expect(DB::table('rooms')->where('tenant_id', $this->a['tenant']->id)->count())->toBe(1);
});
