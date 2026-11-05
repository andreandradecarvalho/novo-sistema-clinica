<?php

declare(strict_types=1);

use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Actions\CancelAppointment;
use App\Domain\Scheduling\Actions\CreateAppointment;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            $unit = Unit::query()->create(['name' => 'Unidade sintética']);
            $other = Unit::query()->create(['name' => 'Outra unidade sintética']);
            $patient = Patient::query()->create(['name' => 'Paciente sintético']);
            $clinic['user']->givePermissionTo(['patients.view', 'units.view', 'appointments.view', 'appointments.create', 'appointments.cancel']);
            $clinic['user']->forceFill(['unit_id' => $unit->id, 'two_factor_confirmed_at' => now()])->save();

            return compact('patient', 'unit', 'other');
        });
    }
    $this->base = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/appointments';
    $this->input = ['unit_id' => $this->dataA['unit']->id, 'scheduled_date' => '2026-12-10', 'idempotency_key' => (string) Str::uuid()];
    $this->cancelInput = ['reason' => 'Motivo sintético <script>unsafe()</script>', 'idempotency_key' => (string) Str::uuid(), 'expected_status' => 'pre_agendado'];
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates a daily reservation without an order or visit and preserves encrypted unit history', function (): void {
    $this->get($this->base.'/create')->assertOk()->assertSee('Nova reserva diária')->assertDontSee('Outra unidade sintética');
    $this->post($this->base, $this->input)->assertRedirect();
    $appointment = $this->context->run($this->a['tenant'], fn () => Appointment::query()->sole());
    $this->context->run($this->a['tenant'], function () use ($appointment): void {
        expect($appointment->status)->toBe('pre_agendado')->and($appointment->scheduled_at)->toBeNull()->and($appointment->canceled_at)->toBeNull();
        expect($appointment->slot_key)->toBe(-1)->and($appointment->active_flag)->toBe(1);
        expect($appointment->created_by)->toBe($this->a['user']->id);
        expect($appointment->getRawOriginal('context_encrypted'))->not->toContain('Unidade sintética');
        expect($appointment->toArray())->not->toHaveKey('context_encrypted')->not->toHaveKey('creation_key_hash');
        $this->dataA['unit']->update(['name' => 'Unidade renomeada']);
    });
    $this->get($this->base.'/'.$appointment->public_id)->assertOk()->assertSee('Unidade sintética')->assertDontSee('Unidade renomeada')->assertSee('10/12/2026')->assertHeader('Cache-Control', 'no-store, private');
    $this->get($this->base)->assertOk()->assertSee($appointment->public_id, false);
    expect(DB::table('exam_orders')->count())->toBe(0);
    expect(DB::table('activity_log')->where('log_name', 'appointments')->pluck('properties')->filter())->toBeEmpty();
});

it('replays creation and cancellation without duplicates and frees the daily slot for a new intent', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $appointment = $this->context->run($this->a['tenant'], fn () => Appointment::query()->sole());
    $this->post($this->base, ['idempotency_key' => strtoupper($this->input['idempotency_key'])] + $this->input)->assertRedirect();
    $this->postJson($this->base, ['scheduled_date' => '2026-12-11'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
    $this->postJson($this->base, ['idempotency_key' => (string) Str::uuid()] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('scheduled_date');
    $url = $this->base.'/'.$appointment->public_id;
    $this->post($url.'/cancel', $this->cancelInput)->assertRedirect();
    $before = DB::table('appointments')->where('id', $appointment->id)->first();
    $this->post($url.'/cancel', ['reason' => ' '.$this->cancelInput['reason'].' '] + $this->cancelInput)->assertRedirect();
    expect(DB::table('appointments')->where('id', $appointment->id)->first())->toEqual($before);
    $this->postJson($url.'/cancel', ['idempotency_key' => (string) Str::uuid()] + $this->cancelInput)->assertUnprocessable();
    $this->postJson($url.'/cancel', ['reason' => 'Outro'] + $this->cancelInput)->assertUnprocessable();
    $this->get($url)->assertOk()->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false);
    $this->post($this->base, $this->input)->assertRedirect($url);
    $this->post($this->base, ['idempotency_key' => (string) Str::uuid()] + $this->input)->assertRedirect();
    expect(DB::table('appointments')->count())->toBe(2);
    expect(DB::table('appointments')->where('id', $appointment->id)->value('active_flag'))->toBeNull();
    expect(DB::table('activity_log')->where('event', 'appointment_canceled')->count())->toBe(1);
    expect(DB::table('activity_log')->where('event', 'appointment_cancellation_replayed')->count())->toBe(1);
});

it('restricts reservations to the current unit and requires explicit cross unit permission', function (): void {
    $other = ['unit_id' => $this->dataA['other']->id] + $this->input;
    $this->postJson($this->base, $other)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('appointments.all_units'));
    $this->get($this->base.'/create')->assertOk()->assertSee('Outra unidade sintética');
    $this->post($this->base, $other)->assertRedirect();
    $appointment = $this->context->run($this->a['tenant'], fn () => Appointment::query()->sole());
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('appointments.all_units'));
    $this->get($this->base)->assertOk()->assertDontSee($appointment->public_id);
    $this->getJson($this->base.'/'.$appointment->public_id)->assertForbidden();
    $this->postJson($this->base.'/'.$appointment->public_id.'/cancel', $this->cancelInput)->assertForbidden();
    $this->postJson($this->base, $other)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['unit_id' => null])->save());
    $this->get($this->base.'/create')->assertOk()->assertDontSee('Outra unidade sintética');
    $this->get($this->base)->assertOk()->assertDontSee($appointment->public_id);
});

it('permits different dates and units while refusing inactive or foreign booking units', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $this->post($this->base, ['scheduled_date' => '2026-12-11', 'idempotency_key' => (string) Str::uuid()] + $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('appointments.all_units'));
    $this->post($this->base, ['unit_id' => $this->dataA['other']->id, 'idempotency_key' => (string) Str::uuid()] + $this->input)->assertRedirect();
    $this->postJson($this->base, ['unit_id' => $this->dataB['unit']->id] + $this->input)->assertUnprocessable();
    $this->context->run($this->a['tenant'], fn () => $this->dataA['unit']->update(['is_active' => false]));
    $this->postJson($this->base, ['scheduled_date' => '2026-12-12', 'idempotency_key' => (string) Str::uuid()] + $this->input)->assertUnprocessable();
    $this->post($this->base, $this->input)->assertRedirect();
    $appointment = $this->context->run($this->a['tenant'], fn () => Appointment::query()->where('unit_id', $this->dataA['unit']->id)->orderBy('id')->firstOrFail());
    $this->context->run($this->a['tenant'], fn () => $this->dataA['unit']->delete());
    $this->get($this->base.'/'.$appointment->public_id)->assertOk();
    $this->post($this->base.'/'.$appointment->public_id.'/cancel', $this->cancelInput)->assertRedirect();
});

it('validates dates UUID expected status reason and rejects protected fields', function (): void {
    foreach ([['scheduled_date' => '2026-02-30'], ['scheduled_date' => '0999-01-01'], ['idempotency_key' => 'invalid'], ['unit_id' => 0]] as $change) {
        $this->postJson($this->base, $change + $this->input)->assertUnprocessable();
    }
    foreach (['tenant_id' => $this->b['tenant']->id, 'patient_id' => $this->dataB['patient']->id, 'scheduled_at' => '2026-12-10 10:00:00', 'status' => 'confirmado', 'created_by' => 1, 'notes' => 'injected', 'creation_key_hash' => 'injected', 'context_encrypted' => 'injected', 'exam_order_id' => 1] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->post($this->base, $this->input)->assertRedirect();
    $appointment = $this->context->run($this->a['tenant'], fn () => Appointment::query()->sole());
    $url = $this->base.'/'.$appointment->public_id.'/cancel';
    foreach ([['expected_status' => 'confirmado'], ['reason' => ''], ['reason' => str_repeat('x', 1001)], ['idempotency_key' => 'invalid'], ['unit_id' => $this->dataB['unit']->id]] as $change) {
        $this->postJson($url, $change + $this->cancelInput)->assertUnprocessable();
    }
    $this->from($this->base)->post($url, ['idempotency_key' => 'invalid'] + $this->cancelInput)->assertRedirect()->assertSessionMissing('_old_input.reason');
});

it('requires current permissions active actor and second factor even on retries', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $appointment = $this->context->run($this->a['tenant'], fn () => Appointment::query()->sole());
    foreach (['appointments.create', 'appointments.view', 'units.view', 'patients.view'] as $permission) {
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo($permission));
        $this->postJson($this->base, $this->input)->assertForbidden();
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo($permission));
    }
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('appointments.cancel'));
    $this->postJson($this->base.'/'.$appointment->public_id.'/cancel', $this->cancelInput)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->forceFill(['is_active' => false])->save();
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(CreateAppointment::class)->execute($this->dataA['patient'], $this->dataA['unit']->id, $this->input['scheduled_date'], $this->input['idempotency_key']))->toThrow(AuthorizationException::class);
    });
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['is_active' => true, 'two_factor_confirmed_at' => null])->save());
    $this->postJson($this->base, $this->input)->assertForbidden();
});

it('isolates tenant patient bindings direct actions immutable models and missing context', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $appointment = $this->context->run($this->a['tenant'], fn () => Appointment::query()->sole());
    $otherPatient = $this->context->run($this->a['tenant'], fn () => Patient::query()->create(['name' => 'Outro paciente sintético']));
    foreach (['http://'.$this->a['host'].'/patients/'.$this->dataB['patient']->public_id.'/appointments', 'http://'.$this->a['host'].'/patients/'.$otherPatient->public_id.'/appointments'] as $base) {
        $this->get($base.'/'.$appointment->public_id)->assertNotFound();
        $this->postJson($base.'/'.$appointment->public_id.'/cancel', $this->cancelInput)->assertNotFound();
    }
    $this->context->run($this->b['tenant'], function () use ($appointment): void {
        auth()->setUser($this->b['user']->fresh());
        expect(fn () => app(CancelAppointment::class)->execute($this->dataB['patient'], $appointment, 'Sintético', (string) Str::uuid(), 'pre_agendado'))->toThrow(ModelNotFoundException::class);
        expect(fn () => $appointment->contextSnapshot())->toThrow(LogicException::class);
    });
    $this->context->run($this->a['tenant'], function () use ($appointment): void {
        expect(fn () => $appointment->forceFill(['status' => 'cancelado'])->save())->toThrow(LogicException::class);
        expect(fn () => $appointment->delete())->toThrow(LogicException::class);
    });
    $this->context->set(null);
    expect(fn () => Appointment::query()->count())->toThrow(MissingTenant::class);
});

it('fails closed on corrupted snapshots checked in evidence or unsupported appointment states', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $row = (array) DB::table('appointments')->sole();
    $url = $this->base.'/'.$row['public_id'];
    foreach ([['context_encrypted' => 'invalid'], ['status' => 'confirmado'], ['checked_in_at' => '2026-12-10 10:00:00'], ['status' => 'cancelado', 'canceled_at' => '2026-12-10 10:00:00']] as $change) {
        DB::table('appointments')->where('id', $row['id'])->update($change);
        $this->getJson($url)->assertUnprocessable();
        $this->postJson($url.'/cancel', $this->cancelInput)->assertUnprocessable();
        DB::table('appointments')->where('id', $row['id'])->update(array_diff_key($row, array_flip(['active_flag', 'unit_scope', 'slot_key'])));
    }
});

it('rolls back reservation or cancellation together with its audit and recovers the same intent', function (bool $cancel): void {
    if ($cancel) {
        $this->post($this->base, $this->input)->assertRedirect();
    }
    $row = DB::table('appointments')->first();
    $url = $cancel ? $this->base.'/'.$row->public_id.'/cancel' : $this->base;
    $input = $cancel ? $this->cancelInput : $this->input;
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic appointment audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($url, $input))->toThrow(RuntimeException::class);
        expect(DB::table('appointments')->first())->toEqual($row);
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
    $this->post($url, $input)->assertRedirect();
})->with([false, true]);

it('enforces composite tenant FKs date check active slot uniqueness and creation hash pairing in MariaDB', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $row = (array) DB::table('appointments')->sole();
    $row = array_diff_key($row, array_flip(['id', 'active_flag', 'unit_scope', 'slot_key']));
    foreach ([[], ['unit_id' => $this->dataB['unit']->id], ['patient_id' => $this->dataB['patient']->id], ['creation_key_hash' => null, 'scheduled_date' => '2026-12-12'], ['creation_key_hash' => $row['creation_key_hash'], 'scheduled_date' => '2026-12-12'], ['scheduled_at' => '2026-12-11 10:00:00']] as $change) {
        $input = array_replace($row, ['public_id' => (string) Str::ulid(), 'creation_key_hash' => str_repeat('f', 64)], $change);
        expect(fn () => DB::table('appointments')->insert($input))->toThrow(QueryException::class);
    }
});
