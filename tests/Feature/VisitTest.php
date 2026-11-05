<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateHealthQuestionGroup;
use App\Domain\Catalog\Actions\PublishHealthQuestion;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Clinical\Actions\CollectHealthAnswer;
use App\Domain\Companies\Models\Company;
use App\Domain\Executions\Actions\AssignExecutionRoom;
use App\Domain\Executions\Actions\CancelPendingExecution;
use App\Domain\Executions\Actions\CompleteExamExecution;
use App\Domain\Executions\Actions\ManageProfessionalExamAuthorization;
use App\Domain\Executions\Actions\PrepareExamExecution;
use App\Domain\Executions\Actions\StartExamExecution;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Identity\Models\User;
use App\Domain\Orders\Actions\CreateClinicalReceivable;
use App\Domain\Orders\Actions\CreateExamOrder;
use App\Domain\Orders\Actions\TransitionClinicalReceivable;
use App\Domain\Orders\Actions\TransitionExamOrder;
use App\Domain\Orders\Enums\OrderTransition;
use App\Domain\Orders\Enums\ReceivableTransition;
use App\Domain\Patients\Models\Patient;
use App\Domain\Professionals\Actions\SaveProfessional;
use App\Domain\Scheduling\Actions\ConfirmAppointment;
use App\Domain\Scheduling\Actions\CreateAppointment;
use App\Domain\Scheduling\Actions\SaveRoom;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Actions\CancelVisit;
use App\Domain\Visits\Actions\CheckInAppointment;
use App\Domain\Visits\Models\Visit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            $unit = Unit::query()->create(['name' => 'Unidade sintética de atendimento']);
            $other = Unit::query()->create(['name' => 'Outra unidade sintética']);
            $patient = Patient::query()->create(['name' => 'Paciente sintético']);
            $clinic['user']->givePermissionTo(['patients.view', 'units.view', 'appointments.view', 'appointments.create', 'appointments.cancel', 'appointments.confirm', 'visits.view', 'visits.check_in', 'orders.view', 'orders.manage', 'catalog.view', 'receivables.view', 'receivables.manage', 'receivables.cancel']);
            $clinic['user']->forceFill(['unit_id' => $unit->id, 'two_factor_confirmed_at' => now()])->save();
            auth()->setUser($clinic['user']->fresh());
            $exam = ExamType::query()->create(['code' => 'VISIT-SYN', 'name' => 'Exame sintético', 'price' => '12.34']);
            $order = app(CreateExamOrder::class)->execute($patient, [$exam->id], (string) Str::uuid());
            app(TransitionExamOrder::class)->execute($patient, $order, OrderTransition::Authorize, 'draft');
            $appointment = app(CreateAppointment::class)->execute($patient, (int) $unit->id, '2026-12-10', (string) Str::uuid());

            return compact('unit', 'other', 'patient', 'exam', 'order', 'appointment');
        });
    }
    $this->base = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/appointments/'.$this->dataA['appointment']->public_id;
    $this->confirmInput = ['expected_status' => 'pre_agendado', 'idempotency_key' => (string) Str::uuid()];
    $this->input = ['expected_status' => 'confirmado', 'idempotency_key' => (string) Str::uuid(), 'exam_order_id' => $this->dataA['order']->id];
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('records staff confirmation once and retains it after cancellation and retries', function (): void {
    $this->get($this->base)->assertOk()->assertSee('Registrar confirmação manual');
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->postJson($this->base.'/confirm', ['idempotency_key' => (string) Str::uuid()] + $this->confirmInput)->assertUnprocessable();
    $this->get($this->base)->assertOk()->assertSee('Confirmado')->assertSee('Abrir check-in');
    $before = DB::table('appointments')->where('id', $this->dataA['appointment']->id)->first();
    $cancel = ['reason' => 'Cancelamento sintético', 'idempotency_key' => (string) Str::uuid(), 'expected_status' => 'confirmado'];
    $this->post($this->base.'/cancel', $cancel)->assertRedirect();
    $this->post($this->base.'/cancel', $cancel)->assertRedirect();
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($before): void {
        $appointment = $this->dataA['appointment']->fresh();
        $history = $appointment->contextSnapshot();
        expect($appointment->status)->toBe('cancelado')->and($appointment->getRawOriginal('confirmed_at'))->toBe($before->confirmed_at);
        expect($history['confirmation']['source'])->toBe('staff')->and($history['confirmation']['actor_id'])->toBe((int) $this->a['user']->id);
        expect($history['cancellation']['from_status'])->toBe('confirmado');
    });
    expect(DB::table('visits')->count())->toBe(0);
    expect(DB::table('activity_log')->where('event', 'appointment_confirmed')->count())->toBe(1);
});

it('requires confirmation then opens one coherent immutable visit without executing or repricing', function (): void {
    $this->getJson($this->base.'/check-in')->assertUnprocessable();
    $this->postJson($this->base.'/check-in', $this->input)->assertUnprocessable();
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->get($this->base.'/check-in')->assertOk()->assertSee($this->dataA['order']->public_id);
    $this->post($this->base.'/check-in', $this->input)->assertRedirect();
    $visit = $this->context->run($this->a['tenant'], fn () => Visit::query()->sole());
    $url = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/visits/'.$visit->public_id;
    $this->get($url)->assertOk()->assertSee('Atendimento aberto')->assertHeader('Cache-Control', 'no-store, private');
    $this->get($this->base)->assertOk()->assertSee('Check-in realizado')->assertSee('Consultar atendimento')->assertDontSee('Cancelar reserva');
    $before = DB::table('visits')->where('id', $visit->id)->first();
    $this->post($this->base.'/check-in', ['idempotency_key' => strtoupper($this->input['idempotency_key'])] + $this->input)->assertRedirect($url);
    expect(DB::table('visits')->where('id', $visit->id)->first())->toEqual($before);
    $this->postJson($this->base.'/check-in', ['idempotency_key' => (string) Str::uuid()] + $this->input)->assertUnprocessable();
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($visit): void {
        $history = $visit->attendanceSnapshot();
        expect($visit->status)->toBe('open')->and($visit->source)->toBe('appointment');
        expect($visit->patient_id)->toBe($this->dataA['patient']->id)->and($visit->unit_id)->toBe($this->dataA['unit']->id);
        expect($visit->exam_order_id)->toBe($this->dataA['order']->id)->and($history['actor_id'])->toBe((int) $this->a['user']->id);
        expect($visit->toArray())->not->toHaveKey('context_encrypted');
        expect($visit->getRawOriginal('context_encrypted'))->not->toContain('key_hash');
        expect(fn () => $visit->forceFill(['status' => 'completed'])->save())->toThrow(LogicException::class);
        expect(fn () => $visit->delete())->toThrow(LogicException::class);
        expect($this->dataA['order']->fresh()->status)->toBe('authorized');
        expect($this->dataA['order']->items()->sole()->unit_price)->toBe('12.34');
        expect($this->dataA['order']->items()->sole()->status)->toBe('authorized');
    });
    expect(DB::table('activity_log')->where('event', 'visit_opened')->count())->toBe(1);
    expect(DB::table('activity_log')->where('event', 'visit_check_in_replayed')->count())->toBe(1);
    expect(DB::table('activity_log')->whereIn('log_name', ['appointments', 'visits'])->pluck('properties')->filter())->toBeEmpty();
});

it('blocks cancellation of a checked in reservation its order and open receivable', function (): void {
    $receipt = $this->context->run($this->a['tenant'], function () {
        auth()->setUser($this->a['user']->fresh());

        return app(CreateClinicalReceivable::class)->execute($this->dataA['patient'], $this->dataA['order'], '2026-12-10');
    });
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->post($this->base.'/check-in', $this->input)->assertRedirect();
    $this->postJson($this->base.'/cancel', ['reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid(), 'expected_status' => 'confirmado'])->assertUnprocessable();
    $orderUrl = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/orders/'.$this->dataA['order']->public_id;
    $this->postJson($orderUrl.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->assertUnprocessable();
    $this->postJson($orderUrl.'/receivables/'.$receipt->id.'/cancel', ['expected_status' => 'open', 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Sintético'])->assertUnprocessable();
    expect(DB::table('clinical_receivables')->value('status'))->toBe('open');
});

it('refuses unauthorized or incompatible orders units and stale states', function (): void {
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->postJson($this->base.'/check-in', ['exam_order_id' => $this->dataB['order']->id] + $this->input)->assertUnprocessable();
    $orderId = $this->dataA['order']->id;
    foreach (['draft', 'canceled', 'in_progress'] as $state) {
        DB::table('exam_orders')->where('id', $orderId)->update(['status' => $state]);
        $this->postJson($this->base.'/check-in', $this->input)->assertUnprocessable();
    }
    DB::table('exam_orders')->where('id', $orderId)->update(['status' => 'authorized']);
    $this->context->run($this->a['tenant'], fn () => $this->dataA['unit']->update(['is_active' => false]));
    $this->postJson($this->base.'/check-in', $this->input)->assertUnprocessable();
    $this->context->run($this->a['tenant'], fn () => $this->dataA['unit']->update(['is_active' => true]));
    DB::table('exam_order_items')->where('exam_order_id', $orderId)->update(['status' => 'canceled']);
    $this->postJson($this->base.'/check-in', $this->input)->assertUnprocessable();
    expect(DB::table('visits')->count())->toBe(0);
});

it('rechecks separate permissions unit assignment active actor and two factor on confirmation and replay', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('appointments.confirm'));
    $this->postJson($this->base.'/confirm', $this->confirmInput)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('appointments.confirm'));
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    foreach (['visits.check_in', 'visits.view', 'orders.view', 'appointments.view', 'units.view', 'patients.view'] as $permission) {
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo($permission));
        $this->postJson($this->base.'/check-in', $this->input)->assertForbidden();
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo($permission));
    }
    $this->post($this->base.'/check-in', $this->input)->assertRedirect();
    $visit = $this->context->run($this->a['tenant'], fn () => Visit::query()->sole());
    $visitUrl = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/visits/'.$visit->public_id;
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['unit_id' => $this->dataA['other']->id])->save());
    $this->getJson($visitUrl)->assertForbidden();
    $this->postJson($this->base.'/check-in', $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->forceFill(['unit_id' => $this->dataA['unit']->id, 'is_active' => false])->save();
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(CheckInAppointment::class)->execute($this->dataA['patient'], $this->dataA['appointment'], $this->dataA['order']->id, $this->input['idempotency_key'], 'confirmado'))->toThrow(AuthorizationException::class);
    });
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['is_active' => true, 'two_factor_confirmed_at' => null])->save());
    $this->postJson($this->base.'/check-in', $this->input)->assertForbidden();
});

it('enforces foreign patient reservation visit bindings direct actions and missing context', function (): void {
    $foreign = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/appointments/'.$this->dataB['appointment']->public_id;
    $this->postJson($foreign.'/confirm', $this->confirmInput)->assertNotFound();
    $this->postJson($foreign.'/check-in', $this->input)->assertNotFound();
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->post($this->base.'/check-in', $this->input)->assertRedirect();
    $visit = $this->context->run($this->a['tenant'], fn () => Visit::query()->sole());
    $this->get('http://'.$this->b['host'].'/patients/'.$this->dataB['patient']->public_id.'/visits/'.$visit->public_id)->assertRedirect();
    $this->context->run($this->b['tenant'], function () use ($visit): void {
        auth()->setUser($this->b['user']->fresh());
        expect(fn () => app(CheckInAppointment::class)->execute($this->dataB['patient'], $this->dataA['appointment'], $this->dataB['order']->id, (string) Str::uuid(), 'confirmado'))->toThrow(ModelNotFoundException::class);
        expect(fn () => $visit->contextSnapshot())->toThrow(LogicException::class);
    });
    $this->context->set(null);
    expect(fn () => Visit::query()->count())->toThrow(MissingTenant::class);
});

it('rejects authoritative field injection malformed intent and forged confirmation state', function (): void {
    foreach ([['idempotency_key' => 'bad'], ['expected_status' => 'confirmado'], ['confirmed_at' => '2026-12-10'], ['status' => 'checked_in']] as $change) {
        $this->postJson($this->base.'/confirm', $change + $this->confirmInput)->assertUnprocessable();
    }
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    foreach (['tenant_id' => $this->b['tenant']->id, 'patient_id' => $this->dataB['patient']->id, 'unit_id' => $this->dataA['other']->id, 'status' => 'completed', 'checked_in_at' => '2026-12-10', 'created_by' => 1, 'source' => 'fake', 'context_encrypted' => 'fake'] as $field => $value) {
        $this->postJson($this->base.'/check-in', [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(DB::table('visits')->count())->toBe(0);
});

it('fails closed when attendance or confirmation evidence and relational history diverge', function (): void {
    $id = $this->dataA['appointment']->id;
    DB::table('appointments')->where('id', $id)->update(['status' => 'confirmado', 'confirmed_at' => now()]);
    $this->postJson($this->base.'/check-in', $this->input)->assertUnprocessable();
    DB::table('appointments')->where('id', $id)->update(['status' => 'pre_agendado', 'confirmed_at' => null]);
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->post($this->base.'/check-in', $this->input)->assertRedirect();
    $visit = (array) DB::table('visits')->sole();
    $url = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/visits/'.$visit['public_id'];
    foreach ([['context_encrypted' => 'corrupt'], ['checked_in_at' => '2026-12-10 10:00:00'], ['status' => 'completed']] as $change) {
        DB::table('visits')->where('id', $visit['id'])->update($change);
        $this->getJson($url)->assertUnprocessable();
        $this->postJson($this->base.'/check-in', $this->input)->assertUnprocessable();
        DB::table('visits')->where('id', $visit['id'])->update($visit);
    }
    $context = $this->context->run($this->a['tenant'], fn () => $this->dataA['appointment']->fresh()->contextSnapshot());
    $context['check_in']['visit_public_id'] = (string) Str::ulid();
    DB::table('appointments')->where('id', $id)->update(['context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR))]);
    $this->getJson($url)->assertUnprocessable();
});

it('rolls back confirmation or check in including the visit and earlier audit on persistence failure', function (bool $checkIn): void {
    if ($checkIn) {
        $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    }
    $before = DB::table('appointments')->where('id', $this->dataA['appointment']->id)->first();
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): void
        {
            if (in_array($arguments[1], ['visit_opened', 'appointment_confirmed'], true)) {
                throw new RuntimeException('Synthetic attendance audit failure');
            }
            (new RegistryAudit)->record(...$arguments);
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($this->base.($checkIn ? '/check-in' : '/confirm'), $checkIn ? $this->input : $this->confirmInput))->toThrow(RuntimeException::class);
        expect(DB::table('appointments')->where('id', $before->id)->first())->toEqual($before);
        expect(DB::table('visits')->count())->toBe(0);
        expect(DB::table('activity_log')->where('event', 'appointment_checked_in')->count())->toBe(0);
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
    $this->post($this->base.($checkIn ? '/check-in' : '/confirm'), $checkIn ? $this->input : $this->confirmInput)->assertRedirect();
})->with([false, true]);

it('requires company visibility for corporate selection check in and visit reading', function (): void {
    $order = $this->context->run($this->a['tenant'], function () {
        $this->a['user']->givePermissionTo('companies.view');
        auth()->setUser($this->a['user']->fresh());
        $company = Company::query()->create(['name' => 'Pagadora sintética']);
        $order = app(CreateExamOrder::class)->execute($this->dataA['patient'], [$this->dataA['exam']->id], (string) Str::uuid(), $company);

        return app(TransitionExamOrder::class)->execute($this->dataA['patient'], $order, OrderTransition::Authorize, 'draft');
    });
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('companies.view'));
    $this->get($this->base.'/check-in')->assertOk()->assertDontSee($order->public_id);
    $this->postJson($this->base.'/check-in', ['exam_order_id' => $order->id] + $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('companies.view'));
    $this->post($this->base.'/check-in', ['exam_order_id' => $order->id] + $this->input)->assertRedirect();
    $visit = DB::table('visits')->sole();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('companies.view'));
    $this->getJson('http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/visits/'.$visit->public_id)->assertForbidden();
});

it('enforces one visit per reservation and composite tenant foreign keys in MariaDB', function (): void {
    $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
    $this->post($this->base.'/check-in', $this->input)->assertRedirect();
    $row = (array) DB::table('visits')->sole();
    unset($row['id']);
    foreach ([[], ['appointment_id' => $this->dataB['appointment']->id], ['appointment_id' => null, 'exam_order_id' => $this->dataB['order']->id], ['appointment_id' => null, 'unit_id' => $this->dataB['unit']->id]] as $change) {
        $input = array_replace($row, ['public_id' => (string) Str::ulid()], $change);
        expect(fn () => DB::table('visits')->insert($input))->toThrow(QueryException::class);
    }
});

describe('visit cancellation before execution', function (): void {
    beforeEach(function (): void {
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('visits.cancel'));
        $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
        $this->post($this->base.'/check-in', $this->input)->assertRedirect();
        $this->visit = $this->context->run($this->a['tenant'], fn () => Visit::query()->sole());
        $this->visitUrl = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/visits/'.$this->visit->public_id;
        $this->cancelInput = ['expected_status' => 'open', 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Cancelamento sintético <script>alert(1)</script>'];
    });

    it('preserves arrival order and finance while canceling once with encrypted evidence', function (): void {
        $appointment = DB::table('appointments')->where('id', $this->dataA['appointment']->id)->first();
        $order = DB::table('exam_orders')->where('id', $this->dataA['order']->id)->first();
        $this->get($this->visitUrl)->assertOk()->assertSee('Cancelar atendimento');
        $this->post($this->visitUrl.'/cancel', $this->cancelInput)->assertRedirect($this->visitUrl);
        $first = DB::table('visits')->where('id', $this->visit->id)->first();
        $this->post($this->visitUrl.'/cancel', ['idempotency_key' => strtoupper($this->cancelInput['idempotency_key'])] + $this->cancelInput)->assertRedirect($this->visitUrl);
        expect(DB::table('visits')->where('id', $this->visit->id)->first())->toEqual($first);
        $this->postJson($this->visitUrl.'/cancel', ['reason' => 'Outro motivo'] + $this->cancelInput)->assertUnprocessable();
        $this->postJson($this->visitUrl.'/cancel', ['idempotency_key' => (string) Str::uuid()] + $this->cancelInput)->assertUnprocessable();
        $this->post($this->base.'/check-in', $this->input)->assertRedirect($this->visitUrl)->assertSessionHas('status', 'Check-in recuperado. O atendimento permanece cancelado.');
        $this->get($this->visitUrl)->assertOk()->assertSee('Atendimento cancelado')->assertSee($this->cancelInput['reason'])->assertDontSee($this->cancelInput['reason'], false)->assertDontSee('Cancelar atendimento');
        expect(DB::table('appointments')->where('id', $appointment->id)->first())->toEqual($appointment);
        expect(DB::table('exam_orders')->where('id', $order->id)->first())->toEqual($order);
        $this->context->run($this->a['tenant'], function (): void {
            $visit = $this->visit->fresh();
            $history = $visit->attendanceSnapshot();
            expect($visit->status)->toBe('canceled')->and($visit->completed_at)->toBeNull();
            expect($history['cancellation']['actor_id'])->toBe((int) $this->a['user']->id)->and($history['cancellation']['reason'])->toBe($this->cancelInput['reason']);
            expect($visit->getRawOriginal('context_encrypted'))->not->toContain($this->cancelInput['reason']);
            expect($visit->toArray())->not->toHaveKey('context_encrypted');
        });
        $this->postJson($this->base.'/cancel', ['expected_status' => 'confirmado'] + $this->cancelInput)->assertUnprocessable();
        $orderUrl = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/orders/'.$order->public_id;
        $this->postJson($orderUrl.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->assertUnprocessable();
        expect(DB::table('activity_log')->where('event', 'visit_canceled')->count())->toBe(1);
        expect(DB::table('activity_log')->where('event', 'visit_cancellation_replayed')->count())->toBe(1);
        expect(DB::table('activity_log')->where('log_name', 'visits')->pluck('properties')->filter())->toBeEmpty();
    });

    it('requires current permission unit scope active actor and tenant even for replay', function (): void {
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('visits.cancel'));
        $this->get($this->visitUrl)->assertOk()->assertDontSee('Cancelar atendimento');
        $this->postJson($this->visitUrl.'/cancel', $this->cancelInput)->assertForbidden();
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('visits.cancel'));
        $this->post($this->visitUrl.'/cancel', $this->cancelInput)->assertRedirect();
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['unit_id' => $this->dataA['other']->id])->save());
        $this->postJson($this->visitUrl.'/cancel', $this->cancelInput)->assertForbidden();
        $this->context->run($this->a['tenant'], function (): void {
            $this->a['user']->forceFill(['unit_id' => $this->dataA['unit']->id, 'is_active' => false])->save();
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(CancelVisit::class)->execute($this->dataA['patient'], $this->visit, $this->cancelInput['reason'], $this->cancelInput['idempotency_key'], 'open'))->toThrow(AuthorizationException::class);
        });
        $this->context->run($this->b['tenant'], function (): void {
            auth()->setUser($this->b['user']->fresh());
            expect(fn () => app(CancelVisit::class)->execute($this->dataB['patient'], $this->visit, 'Sintético', (string) Str::uuid(), 'open'))->toThrow(ModelNotFoundException::class);
        });
    });

    it('rejects foreign bindings another actor intent revoked rights and missing second factor', function (): void {
        $foreign = 'http://'.$this->a['host'].'/patients/'.$this->dataB['patient']->public_id.'/visits/'.$this->visit->public_id;
        $this->postJson($foreign.'/cancel', $this->cancelInput)->assertNotFound();
        $this->post($this->visitUrl.'/cancel', $this->cancelInput)->assertRedirect();
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('visits.cancel'));
        $this->postJson($this->visitUrl.'/cancel', $this->cancelInput)->assertForbidden();
        $this->context->run($this->a['tenant'], function (): void {
            $this->a['user']->givePermissionTo('visits.cancel');
            $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(CancelVisit::class)->execute($this->dataA['patient'], $this->visit, $this->cancelInput['reason'], $this->cancelInput['idempotency_key'], 'open'))->toThrow(AuthorizationException::class);
            $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
            $other = User::query()->create(['name' => 'Outro integrante sintético', 'email' => 'other-'.Str::uuid().'@example.test', 'password' => Str::random(40)]);
            $other->givePermissionTo(['patients.view', 'units.view', 'appointments.view', 'orders.view', 'visits.view', 'visits.cancel']);
            $other->forceFill(['unit_id' => $this->dataA['unit']->id, 'two_factor_confirmed_at' => now()])->save();
            auth()->setUser($other->fresh());
            expect(fn () => app(CancelVisit::class)->execute($this->dataA['patient'], $this->visit, $this->cancelInput['reason'], $this->cancelInput['idempotency_key'], 'open'))->toThrow(ValidationException::class);
        });
    });

    it('rejects changed order item states and injected visit attributes', function (): void {
        foreach (['reason' => '', 'idempotency_key' => 'invalid', 'expected_status' => 'in_progress', 'unit_id' => $this->dataA['other']->id, 'status' => 'canceled', 'context_encrypted' => 'invalid'] as $field => $value) {
            $this->postJson($this->visitUrl.'/cancel', [$field => $value] + $this->cancelInput)->assertUnprocessable();
        }
        foreach (['in_progress', 'completed', 'canceled'] as $status) {
            DB::table('exam_order_items')->where('exam_order_id', $this->dataA['order']->id)->update(['status' => $status]);
            $this->postJson($this->visitUrl.'/cancel', $this->cancelInput)->assertUnprocessable();
        }
        DB::table('exam_order_items')->where('exam_order_id', $this->dataA['order']->id)->update(['status' => 'authorized']);
        DB::table('exam_orders')->where('id', $this->dataA['order']->id)->update(['status' => 'in_progress']);
        $this->postJson($this->visitUrl.'/cancel', $this->cancelInput)->assertUnprocessable();
        expect(DB::table('visits')->value('status'))->toBe('open');
    });

    it('fails closed on inconsistent cancellation evidence or status', function (): void {
        $this->post($this->visitUrl.'/cancel', $this->cancelInput)->assertRedirect();
        $original = DB::table('visits')->where('id', $this->visit->id)->value('context_encrypted');
        $context = json_decode(Crypt::decryptString($original), true, 512, JSON_THROW_ON_ERROR);
        foreach (['reason' => '', 'request_hash' => str_repeat('0', 64), 'actor_id' => 0, 'occurred_at' => '2000-01-01T00:00:00+00:00', 'from_status' => 'in_progress'] as $field => $value) {
            $corrupt = $context;
            $corrupt['cancellation'][$field] = $value;
            DB::table('visits')->where('id', $this->visit->id)->update(['context_encrypted' => Crypt::encryptString(json_encode($corrupt, JSON_THROW_ON_ERROR))]);
            $this->getJson($this->visitUrl)->assertUnprocessable();
            $this->postJson($this->visitUrl.'/cancel', $this->cancelInput)->assertUnprocessable();
        }
        DB::table('visits')->where('id', $this->visit->id)->update(['context_encrypted' => $original, 'status' => 'open']);
        $this->getJson($this->visitUrl)->assertUnprocessable();
    });

    it('rolls back cancellation if audit fails', function (): void {
        $before = DB::table('visits')->where('id', $this->visit->id)->first();
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
                expect(fn () => app(CancelVisit::class)->execute($this->dataA['patient'], $this->visit, $this->cancelInput['reason'], $this->cancelInput['idempotency_key'], 'open'))->toThrow(RuntimeException::class);
            });
        } finally {
            app()->forgetInstance(RegistryAudit::class);
        }
        expect(DB::table('visits')->where('id', $this->visit->id)->first())->toEqual($before);
        $this->post($this->visitUrl.'/cancel', $this->cancelInput)->assertRedirect();
    });
});

describe('pending examination attempts', function (): void {
    beforeEach(function (): void {
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo(['executions.view', 'executions.prepare', 'executions.cancel', 'visits.cancel']));
        $this->post($this->base.'/confirm', $this->confirmInput)->assertRedirect();
        $this->post($this->base.'/check-in', $this->input)->assertRedirect();
        $this->visit = $this->context->run($this->a['tenant'], fn () => Visit::query()->sole());
        $this->visitUrl = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/visits/'.$this->visit->public_id;
        $this->executionBase = $this->visitUrl.'/executions';
        $itemId = $this->context->run($this->a['tenant'], fn () => $this->dataA['order']->items()->sole()->id);
        $this->prepareInput = ['expected_status' => 'open', 'exam_order_item_id' => $itemId, 'idempotency_key' => (string) Str::uuid()];
        $this->cancelInput = ['expected_status' => 'pending', 'reason' => 'Motivo sintético <script>alert(1)</script>', 'idempotency_key' => (string) Str::uuid()];
        $this->prepareAttempt = function () {
            $this->post($this->executionBase, $this->prepareInput)->assertRedirect($this->executionBase);

            return $this->context->run($this->a['tenant'], fn () => ExamExecution::query()->sole());
        };
    });

    it('prepares one pending attempt and replays without executing repricing or changing the order', function (): void {
        $beforeVisit = DB::table('visits')->where('id', $this->visit->id)->first();
        $beforeOrder = DB::table('exam_orders')->where('id', $this->dataA['order']->id)->first();
        $this->get($this->visitUrl)->assertOk()->assertSee('Tentativas dos exames');
        $attempt = ($this->prepareAttempt)();
        $this->post($this->executionBase, $this->prepareInput)->assertRedirect();
        $this->postJson($this->executionBase, ['idempotency_key' => (string) Str::uuid()] + $this->prepareInput)->assertUnprocessable();
        $this->get($this->executionBase)->assertOk()->assertSee('Pendente de atribuição')->assertSee('Exame sintético')->assertHeader('Cache-Control', 'no-store, private');
        $this->context->run($this->a['tenant'], function () use ($attempt): void {
            $history = $attempt->preparationSnapshot();
            expect($attempt->status)->toBe('pending')->and($attempt->attempt_no)->toBe(1)->and($attempt->source)->toBe('order');
            expect($attempt->started_at)->toBeNull()->and($attempt->finished_at)->toBeNull()->and($attempt->performed_by)->toBeNull()->and($attempt->room_id)->toBeNull();
            expect($history['actor_id'])->toBe((int) $this->a['user']->id)->and($attempt->toArray())->not->toHaveKey('context_encrypted');
            expect($attempt->getRawOriginal('context_encrypted'))->not->toContain('key_hash');
            expect(fn () => $attempt->forceFill(['status' => 'completed'])->save())->toThrow(LogicException::class);
            expect(fn () => $attempt->delete())->toThrow(LogicException::class);
        });
        expect(DB::table('visits')->where('id', $this->visit->id)->first())->toEqual($beforeVisit);
        expect(DB::table('exam_orders')->where('id', $this->dataA['order']->id)->first())->toEqual($beforeOrder);
        expect(DB::table('activity_log')->where('event', 'execution_prepared')->count())->toBe(1);
        expect(DB::table('activity_log')->where('event', 'execution_preparation_replayed')->count())->toBe(1);
    });

    it('cancels before start preserving encrypted reason and creates a new numbered attempt', function (): void {
        $attempt = ($this->prepareAttempt)();
        $url = $this->executionBase.'/'.$attempt->id.'/cancel';
        $this->post($url, $this->cancelInput)->assertRedirect();
        $first = DB::table('exam_executions')->where('id', $attempt->id)->first();
        $this->post($url, $this->cancelInput)->assertRedirect();
        expect(DB::table('exam_executions')->where('id', $attempt->id)->first())->toEqual($first);
        $this->postJson($url, ['reason' => 'Outro motivo'] + $this->cancelInput)->assertUnprocessable();
        $this->postJson($url, ['idempotency_key' => (string) Str::uuid()] + $this->cancelInput)->assertUnprocessable();
        $this->post($this->executionBase, $this->prepareInput)->assertRedirect();
        $this->get($this->executionBase)->assertOk()->assertSee($this->cancelInput['reason'])->assertDontSee($this->cancelInput['reason'], false)->assertSee('Cancelada antes da execução');
        $this->context->run($this->a['tenant'], function () use ($attempt): void {
            $fresh = $attempt->fresh();
            expect($fresh->preparationSnapshot()['cancellation']['reason'])->toBe($this->cancelInput['reason']);
            expect($fresh->getRawOriginal('reason_encrypted'))->not->toContain($this->cancelInput['reason']);
            expect($fresh->started_at)->toBeNull()->and($fresh->finished_at)->toBeNull()->and($fresh->performed_by)->toBeNull();
        });
        $this->post($this->executionBase, ['idempotency_key' => (string) Str::uuid()] + $this->prepareInput)->assertRedirect();
        expect(DB::table('exam_executions')->where('visit_id', $this->visit->id)->orderBy('attempt_no')->pluck('status')->all())->toBe(['canceled', 'pending']);
        expect(DB::table('exam_executions')->where('visit_id', $this->visit->id)->orderBy('attempt_no')->pluck('attempt_no')->all())->toBe([1, 2]);
    });

    it('blocks visit cancellation with pending attempts but recovers history after all are canceled', function (): void {
        $attempt = ($this->prepareAttempt)();
        $visitCancel = ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()];
        $this->postJson($this->visitUrl.'/cancel', $visitCancel)->assertUnprocessable();
        $this->post($this->executionBase.'/'.$attempt->id.'/cancel', $this->cancelInput)->assertRedirect();
        $this->post($this->visitUrl.'/cancel', $visitCancel)->assertRedirect();
        $this->post($this->executionBase, $this->prepareInput)->assertRedirect();
        $this->postJson($this->executionBase, ['idempotency_key' => (string) Str::uuid()] + $this->prepareInput)->assertUnprocessable();
        $this->post($this->executionBase.'/'.$attempt->id.'/cancel', $this->cancelInput)->assertRedirect();
        $this->get($this->executionBase)->assertOk()->assertDontSee('Preparar tentativa');
        expect(DB::table('visits')->where('id', $this->visit->id)->value('status'))->toBe('canceled');
    });

    it('rejects foreign items incompatible order states and authoritative payloads', function (): void {
        $foreignItem = $this->context->run($this->b['tenant'], fn () => $this->dataB['order']->items()->sole()->id);
        $this->postJson($this->executionBase, ['exam_order_item_id' => $foreignItem] + $this->prepareInput)->assertUnprocessable();
        foreach (['expected_status' => 'in_progress', 'idempotency_key' => 'invalid', 'performed_by' => 1, 'room_id' => 1, 'attempt_no' => 7, 'tenant_id' => $this->b['tenant']->id, 'context_encrypted' => 'synthetic', 'started_at' => '2026-11-05'] as $field => $value) {
            $this->postJson($this->executionBase, [$field => $value] + $this->prepareInput)->assertUnprocessable();
        }
        DB::table('exam_order_items')->where('id', $this->prepareInput['exam_order_item_id'])->update(['status' => 'in_progress']);
        $this->postJson($this->executionBase, $this->prepareInput)->assertUnprocessable();
        DB::table('exam_order_items')->where('id', $this->prepareInput['exam_order_item_id'])->update(['status' => 'authorized']);
        DB::table('exam_orders')->where('id', $this->dataA['order']->id)->update(['status' => 'in_progress']);
        $this->postJson($this->executionBase, $this->prepareInput)->assertUnprocessable();
    });

    it('enforces separate permissions current unit and fresh actor on writes and replay', function (): void {
        $attempt = ($this->prepareAttempt)();
        $url = $this->executionBase.'/'.$attempt->id.'/cancel';
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('executions.prepare'));
        $this->postJson($this->executionBase, $this->prepareInput)->assertForbidden();
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('executions.cancel'));
        $this->postJson($url, $this->cancelInput)->assertForbidden();
        $this->get($this->executionBase)->assertOk()->assertDontSee('Cancelar tentativa');
        $this->context->run($this->a['tenant'], function (): void {
            $this->a['user']->givePermissionTo(['executions.prepare', 'executions.cancel']);
            $this->a['user']->forceFill(['unit_id' => $this->dataA['other']->id])->save();
        });
        $this->getJson($this->executionBase)->assertForbidden();
        $this->postJson($url, $this->cancelInput)->assertForbidden();
        $this->context->run($this->a['tenant'], function () use ($attempt): void {
            $this->a['user']->forceFill(['unit_id' => $this->dataA['unit']->id, 'is_active' => false])->save();
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(PrepareExamExecution::class)->execute($this->dataA['patient'], $this->visit, $this->prepareInput['exam_order_item_id'], $this->prepareInput['idempotency_key'], 'open'))->toThrow(AuthorizationException::class);
            $this->a['user']->forceFill(['is_active' => true, 'two_factor_confirmed_at' => null])->save();
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(CancelPendingExecution::class)->execute($this->dataA['patient'], $this->visit, $attempt, 'Sintético', (string) Str::uuid(), 'pending'))->toThrow(AuthorizationException::class);
        });
    });

    it('scopes bindings and direct actions to the tenant patient and visit', function (): void {
        $attempt = ($this->prepareAttempt)();
        $foreign = 'http://'.$this->a['host'].'/patients/'.$this->dataB['patient']->public_id.'/visits/'.$this->visit->public_id.'/executions';
        $this->getJson($foreign)->assertNotFound();
        $this->postJson($foreign, $this->prepareInput)->assertNotFound();
        $this->postJson($foreign.'/'.$attempt->id.'/cancel', $this->cancelInput)->assertNotFound();
        $this->context->run($this->b['tenant'], function () use ($attempt): void {
            auth()->setUser($this->b['user']->fresh());
            expect(fn () => app(CancelPendingExecution::class)->execute($this->dataB['patient'], $this->visit, $attempt, 'Sintético', (string) Str::uuid(), 'pending'))->toThrow(ModelNotFoundException::class);
            expect(fn () => $attempt->contextSnapshot())->toThrow(LogicException::class);
        });
        expect(fn () => $attempt->contextSnapshot())->toThrow(MissingTenant::class);
    });

    it('fails closed on corrupt evidence cancellation or started state', function (): void {
        $attempt = ($this->prepareAttempt)();
        $original = DB::table('exam_executions')->where('id', $attempt->id)->first();
        DB::table('exam_order_items')->where('id', $this->prepareInput['exam_order_item_id'])->update(['status' => 'in_progress']);
        $this->postJson($this->executionBase.'/'.$attempt->id.'/cancel', $this->cancelInput)->assertUnprocessable();
        DB::table('exam_order_items')->where('id', $this->prepareInput['exam_order_item_id'])->update(['status' => 'authorized']);
        foreach (['context_encrypted' => 'invalid', 'status' => 'canceled', 'started_at' => '2026-11-05 10:00:00'] as $field => $value) {
            DB::table('exam_executions')->where('id', $attempt->id)->update([$field => $value]);
            $this->getJson($this->executionBase)->assertUnprocessable();
            $this->postJson($this->visitUrl.'/cancel', ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
            DB::table('exam_executions')->where('id', $attempt->id)->update((array) $original);
        }
        $this->post($this->executionBase.'/'.$attempt->id.'/cancel', $this->cancelInput)->assertRedirect();
        DB::table('exam_executions')->where('id', $attempt->id)->update(['reason_encrypted' => Crypt::encryptString('Motivo trocado')]);
        $this->getJson($this->executionBase)->assertUnprocessable();
        $this->postJson($this->executionBase.'/'.$attempt->id.'/cancel', $this->cancelInput)->assertUnprocessable();
    });

    it('allows an item in another visit only after its pending attempt was canceled', function (): void {
        $attempt = ($this->prepareAttempt)();
        $otherVisit = $this->context->run($this->a['tenant'], function () {
            auth()->setUser($this->a['user']->fresh());
            $appointment = app(CreateAppointment::class)->execute($this->dataA['patient'], (int) $this->dataA['unit']->id, '2026-12-11', (string) Str::uuid());
            app(ConfirmAppointment::class)->execute($this->dataA['patient'], $appointment, (string) Str::uuid(), 'pre_agendado');

            return app(CheckInAppointment::class)->execute($this->dataA['patient'], $appointment->fresh(), (int) $this->dataA['order']->id, (string) Str::uuid(), 'confirmado');
        });
        $otherBase = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/visits/'.$otherVisit->public_id.'/executions';
        $input = ['idempotency_key' => (string) Str::uuid()] + $this->prepareInput;
        $this->postJson($otherBase, $input)->assertUnprocessable();
        $this->post($this->executionBase.'/'.$attempt->id.'/cancel', $this->cancelInput)->assertRedirect();
        $this->post($otherBase, $input)->assertRedirect();
        $this->postJson($this->executionBase, ['idempotency_key' => (string) Str::uuid()] + $this->prepareInput)->assertUnprocessable();
        expect(DB::table('exam_executions')->whereIn('visit_id', [$this->visit->id, $otherVisit->id])->where('status', 'pending')->count())->toBe(1);
    });

    it('enforces attempt uniqueness creator and item foreign keys within the tenant', function (): void {
        $attempt = ($this->prepareAttempt)();
        $foreignItem = $this->context->run($this->b['tenant'], fn () => $this->dataB['order']->items()->sole()->id);
        foreach (['exam_order_item_id' => $foreignItem, 'exam_type_id' => $this->dataB['exam']->id, 'created_by' => $this->b['user']->id] as $field => $value) {
            expect(fn () => DB::table('exam_executions')->where('id', $attempt->id)->update([$field => $value]))->toThrow(QueryException::class);
        }
        $row = (array) DB::table('exam_executions')->where('id', $attempt->id)->first();
        unset($row['id']);
        expect(fn () => DB::table('exam_executions')->insert($row))->toThrow(QueryException::class);
        $row['creation_key_hash'] = null;
        $row['creation_request_hash'] = null;
        expect(fn () => DB::table('exam_executions')->insert($row))->toThrow(QueryException::class);
        expect(fn () => DB::table('exam_executions')->where('id', $attempt->id)->update(['attempt_no' => 0]))->toThrow(QueryException::class);
        expect(fn () => DB::table('exam_executions')->where('id', $attempt->id)->update(['creation_request_hash' => null]))->toThrow(QueryException::class);
    });

    it('rolls back preparation and cancellation with audit failures', function (): void {
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
                expect(fn () => app(PrepareExamExecution::class)->execute($this->dataA['patient'], $this->visit, $this->prepareInput['exam_order_item_id'], $this->prepareInput['idempotency_key'], 'open'))->toThrow(RuntimeException::class);
            });
        } finally {
            app()->forgetInstance(RegistryAudit::class);
        }
        expect(DB::table('exam_executions')->where('visit_id', $this->visit->id)->count())->toBe(0);
        $attempt = ($this->prepareAttempt)();
        $before = DB::table('exam_executions')->where('id', $attempt->id)->first();
        app()->instance(RegistryAudit::class, new class
        {
            public function record(...$arguments): never
            {
                throw new RuntimeException('Synthetic audit failure.');
            }
        });
        try {
            $this->context->run($this->a['tenant'], function () use ($attempt): void {
                auth()->setUser($this->a['user']->fresh());
                expect(fn () => app(CancelPendingExecution::class)->execute($this->dataA['patient'], $this->visit, $attempt, 'Sintético', (string) Str::uuid(), 'pending'))->toThrow(RuntimeException::class);
            });
        } finally {
            app()->forgetInstance(RegistryAudit::class);
        }
        expect(DB::table('exam_executions')->where('id', $attempt->id)->first())->toEqual($before);
    });
    describe('room assignment', function (): void {
        beforeEach(function (): void {
            $this->assignmentRooms = $this->context->run($this->a['tenant'], function (): array {
                $this->a['user']->givePermissionTo(['rooms.view', 'rooms.manage', 'executions.assign_room']);
                auth()->setUser($this->a['user']->fresh());
                $save = app(SaveRoom::class);
                $own = $save->execute($this->dataA['unit'], ['room_key' => 'ASSIGN-OWN', 'name' => 'Sala histórica <script>synthetic()</script>', 'ordem' => 0, 'is_active' => true]);
                $other = $save->execute($this->dataA['other'], ['room_key' => 'ASSIGN-OTHER', 'name' => 'Sala de outra unidade', 'ordem' => 0, 'is_active' => true]);
                $inactive = $save->execute($this->dataA['unit'], ['room_key' => 'ASSIGN-OFF', 'name' => 'Sala inativa', 'ordem' => 0, 'is_active' => false]);

                return compact('own', 'other', 'inactive');
            });
            $this->assignmentAttempt = ($this->prepareAttempt)();
            $this->roomPath = $this->executionBase.'/'.$this->assignmentAttempt->id.'/room';
            $this->roomInput = ['expected_status' => 'pending', 'room_id' => $this->assignmentRooms['own']->id, 'idempotency_key' => (string) Str::uuid()];
        });

        it('assigns once from active rooms in the exact unit and retains an encrypted historical name', function (): void {
            $this->get($this->roomPath)->assertOk()->assertSee('ASSIGN-OWN')->assertDontSee('ASSIGN-OTHER')->assertDontSee('ASSIGN-OFF')->assertHeader('Cache-Control', 'no-store, private');
            $this->post($this->roomPath, $this->roomInput)->assertRedirect($this->executionBase);
            $first = DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first();
            $this->post($this->roomPath, ['idempotency_key' => strtoupper($this->roomInput['idempotency_key'])] + $this->roomInput)->assertRedirect();
            expect(DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first())->toEqual($first);
            $this->postJson($this->roomPath, ['idempotency_key' => (string) Str::uuid()] + $this->roomInput)->assertUnprocessable();
            $this->postJson($this->roomPath, ['room_id' => $this->assignmentRooms['other']->id] + $this->roomInput)->assertUnprocessable();
            $this->context->run($this->a['tenant'], function (): void {
                $attempt = $this->assignmentAttempt->fresh();
                $history = $attempt->preparationSnapshot()['room_assignment'];
                expect($attempt->status)->toBe('pending')->and($attempt->room_id)->toBe($this->assignmentRooms['own']->id);
                expect($attempt->performed_by)->toBeNull()->and($attempt->started_at)->toBeNull()->and($attempt->finished_at)->toBeNull();
                expect($history['actor_id'])->toBe((int) $this->a['user']->id)->and($history['unit_id'])->toBe((int) $this->dataA['unit']->id);
                expect($attempt->getRawOriginal('context_encrypted'))->not->toContain($this->assignmentRooms['own']->name);
                auth()->setUser($this->a['user']->fresh());
                app(SaveRoom::class)->execute($this->dataA['unit'], ['room_key' => 'ASSIGN-RENAMED', 'name' => 'Nome atual', 'ordem' => 0, 'is_active' => false], $this->assignmentRooms['own']);
                $this->dataA['unit']->update(['is_active' => false]);
            });
            $this->post($this->roomPath, $this->roomInput)->assertRedirect();
            $this->get($this->executionBase)->assertOk()->assertSee('Sala histórica <script>synthetic()</script>')->assertDontSee('<script>synthetic()</script>', false)->assertSee('ASSIGN-OWN');
            expect(DB::table('activity_log')->where('event', 'execution_room_assigned')->count())->toBe(1);
            expect(DB::table('activity_log')->where('event', 'execution_room_assignment_replayed')->count())->toBe(2);
        });

        it('preserves assignment on pending cancellation and historical replay after visit cancellation', function (): void {
            $this->post($this->roomPath, $this->roomInput)->assertRedirect();
            $this->post($this->executionBase.'/'.$this->assignmentAttempt->id.'/cancel', $this->cancelInput)->assertRedirect();
            $this->post($this->visitUrl.'/cancel', ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
            $this->post($this->roomPath, $this->roomInput)->assertRedirect();
            $this->get($this->executionBase)->assertOk()->assertSee('ASSIGN-OWN')->assertSee('Cancelada antes da execução');
            $this->context->run($this->a['tenant'], function (): void {
                $attempt = $this->assignmentAttempt->fresh();
                expect($attempt->status)->toBe('canceled')->and($attempt->room_id)->toBe($this->assignmentRooms['own']->id);
                expect($attempt->preparationSnapshot())->toHaveKeys(['room_assignment', 'cancellation']);
                expect($this->dataA['order']->fresh()->status)->toBe('authorized');
            });
        });

        it('refuses foreign inactive and other-unit rooms stale items and authoritative input', function (): void {
            foreach (['room_id' => $this->assignmentRooms['other']->id, 'expected_status' => 'in_progress', 'idempotency_key' => 'invalid', 'unit_id' => $this->dataA['other']->id, 'performed_by' => 1, 'started_at' => '2026-11-05', 'context_encrypted' => 'invalid'] as $field => $value) {
                $this->postJson($this->roomPath, [$field => $value] + $this->roomInput)->assertUnprocessable();
            }
            $this->postJson($this->roomPath, ['room_id' => $this->assignmentRooms['inactive']->id] + $this->roomInput)->assertUnprocessable();
            $foreignRoom = $this->context->run($this->b['tenant'], function () {
                $this->b['user']->givePermissionTo(['rooms.view', 'rooms.manage']);
                auth()->setUser($this->b['user']->fresh());

                return app(SaveRoom::class)->execute($this->dataB['unit'], ['room_key' => 'FOREIGN', 'name' => 'Sala externa sintética', 'ordem' => 0, 'is_active' => true]);
            });
            $this->postJson($this->roomPath, ['room_id' => $foreignRoom->id] + $this->roomInput)->assertUnprocessable();
            DB::table('exam_order_items')->where('id', $this->prepareInput['exam_order_item_id'])->update(['status' => 'in_progress']);
            $this->postJson($this->roomPath, $this->roomInput)->assertUnprocessable();
            DB::table('exam_order_items')->where('id', $this->prepareInput['exam_order_item_id'])->update(['status' => 'authorized']);
            $this->context->run($this->a['tenant'], fn () => $this->dataA['unit']->update(['is_active' => false]));
            $this->postJson($this->roomPath, $this->roomInput)->assertUnprocessable();
            $this->getJson($this->roomPath)->assertUnprocessable();
        });

        it('requires current assignment and room-read permissions even on replay', function (): void {
            foreach (['executions.assign_room', 'rooms.view', 'executions.view'] as $permission) {
                $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo($permission));
                $this->postJson($this->roomPath, $this->roomInput)->assertForbidden();
                $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo($permission));
            }
            $this->post($this->roomPath, $this->roomInput)->assertRedirect();
            $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('executions.assign_room'));
            $this->postJson($this->roomPath, $this->roomInput)->assertForbidden();
            $this->context->run($this->a['tenant'], function (): void {
                $this->a['user']->givePermissionTo('executions.assign_room');
                $this->a['user']->forceFill(['unit_id' => $this->dataA['other']->id])->save();
            });
            $this->postJson($this->roomPath, $this->roomInput)->assertForbidden();
        });

        it('enforces direct-action actor status second factor tenant and parent bindings', function (): void {
            $foreignPath = 'http://'.$this->a['host'].'/patients/'.$this->dataB['patient']->public_id.'/visits/'.$this->visit->public_id.'/executions/'.$this->assignmentAttempt->id.'/room';
            $this->postJson($foreignPath, $this->roomInput)->assertNotFound();
            $this->context->run($this->a['tenant'], function (): void {
                foreach ([['is_active' => false], ['is_active' => true, 'two_factor_confirmed_at' => null]] as $change) {
                    $this->a['user']->forceFill($change)->save();
                    auth()->setUser($this->a['user']->fresh());
                    expect(fn () => app(AssignExecutionRoom::class)->execute($this->dataA['patient'], $this->visit, $this->assignmentAttempt, (int) $this->roomInput['room_id'], $this->roomInput['idempotency_key'], 'pending'))->toThrow(AuthorizationException::class);
                }
            });
            $this->context->run($this->b['tenant'], function (): void {
                auth()->setUser($this->b['user']->fresh());
                expect(fn () => app(AssignExecutionRoom::class)->execute($this->dataB['patient'], $this->visit, $this->assignmentAttempt, (int) $this->roomInput['room_id'], $this->roomInput['idempotency_key'], 'pending'))->toThrow(ModelNotFoundException::class);
            });
        });

        it('rejects mismatched room ownership and corrupt assignment evidence', function (): void {
            $this->post($this->roomPath, $this->roomInput)->assertRedirect();
            $row = DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first();
            $original = json_decode(Crypt::decryptString($row->context_encrypted), true, 512, JSON_THROW_ON_ERROR);
            foreach (['unit_id' => (int) $this->dataA['other']->id, 'room_id' => (int) $this->assignmentRooms['other']->id, 'actor_id' => 0, 'key_hash' => 'invalid', 'occurred_at' => '2000-01-01T00:00:00+00:00'] as $field => $value) {
                $context = $original;
                $context['room_assignment'][$field] = $value;
                DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->update(['context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR))]);
                $this->getJson($this->executionBase)->assertUnprocessable();
                $this->postJson($this->roomPath, $this->roomInput)->assertUnprocessable();
                $this->postJson($this->executionBase.'/'.$this->assignmentAttempt->id.'/cancel', $this->cancelInput)->assertUnprocessable();
            }
            DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->update(['context_encrypted' => $row->context_encrypted, 'room_id' => $this->assignmentRooms['other']->id]);
            $this->getJson($this->executionBase)->assertUnprocessable();
        });

        it('rolls back assignment with audit failure and can retry the same intent', function (): void {
            $before = DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first();
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
                    expect(fn () => app(AssignExecutionRoom::class)->execute($this->dataA['patient'], $this->visit, $this->assignmentAttempt, (int) $this->roomInput['room_id'], $this->roomInput['idempotency_key'], 'pending'))->toThrow(RuntimeException::class);
                });
            } finally {
                app()->forgetInstance(RegistryAudit::class);
            }
            expect(DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first())->toEqual($before);
            $this->post($this->roomPath, $this->roomInput)->assertRedirect();
        });
        describe('real execution start', function (): void {
            beforeEach(function (): void {
                [$this->performer, $this->grant] = $this->context->run($this->a['tenant'], function (): array {
                    $this->a['user']->givePermissionTo(['clinical.access', 'executions.perform', 'professionals.view', 'professionals.manage', 'executions.authorizations.view', 'executions.authorizations.manage', 'receivables.settle']);
                    auth()->setUser($this->a['user']->fresh());
                    $professional = app(SaveProfessional::class)->execute(['user_id' => $this->a['user']->id, 'council' => 'SYN', 'council_uf' => 'ES', 'council_number' => 'SYN-START']);
                    $grant = app(ManageProfessionalExamAuthorization::class)->grant($professional, $this->dataA['exam'], 'Concessão sintética');

                    return [$professional, $grant];
                });
                $this->post($this->roomPath, $this->roomInput)->assertRedirect();
                $this->withSession(['auth.password_confirmed_at' => time()]);
                $this->startPath = $this->executionBase.'/'.$this->assignmentAttempt->id.'/start';
                $this->startInput = ['expected_status' => 'pending', 'idempotency_key' => (string) Str::uuid()];
            });

            it('starts atomically as the authorized professional and replays after revocation without rewriting history', function (): void {
                $this->get($this->executionBase)->assertOk()->assertSee('Assumir e iniciar exame');
                $this->post($this->startPath, $this->startInput)->assertRedirect($this->executionBase);
                $before = DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first();
                $this->context->run($this->a['tenant'], function (): void {
                    $attempt = $this->assignmentAttempt->fresh();
                    $history = $attempt->preparationSnapshot();
                    expect($attempt->status)->toBe('in_progress')->and($attempt->performed_by)->toBe($this->performer->id)->and($attempt->finished_at)->toBeNull();
                    expect($history['start']['authorization_id'])->toBe((int) $this->grant->id);
                    expect($this->visit->fresh()->status)->toBe('in_progress')->and($this->dataA['order']->fresh()->status)->toBe('in_progress');
                    expect($this->dataA['order']->items()->sole()->status)->toBe('in_progress')->and($this->dataA['order']->items()->sole()->unit_price)->toBe('12.34');
                    auth()->setUser($this->a['user']->fresh());
                    app(ManageProfessionalExamAuthorization::class)->revoke($this->performer, $this->grant, 'Revogação posterior sintética');
                });
                $this->get($this->executionBase)->assertOk()->assertSee('Em execução')->assertDontSee('Cancelar tentativa');
                $this->get($this->visitUrl)->assertOk()->assertSee('Atendimento em execução')->assertDontSee('Cancelar atendimento');
                $this->post($this->startPath, ['idempotency_key' => strtoupper($this->startInput['idempotency_key'])] + $this->startInput)->assertRedirect();
                expect(DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first())->toEqual($before);
                $this->postJson($this->startPath, ['idempotency_key' => (string) Str::uuid()] + $this->startInput)->assertUnprocessable();
                $this->postJson($this->executionBase.'/'.$this->assignmentAttempt->id.'/cancel', $this->cancelInput)->assertUnprocessable();
                $this->postJson($this->visitUrl.'/cancel', ['expected_status' => 'open', 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Sintético'])->assertUnprocessable();
                expect(DB::table('activity_log')->where('event', 'execution_started')->count())->toBe(1);
                expect(DB::table('activity_log')->where('event', 'execution_start_replayed')->count())->toBe(1);
            });

            it('requires an active specific grant actor permissions and exact unit before starting', function (): void {
                foreach (['clinical.access', 'executions.perform', 'executions.view', 'visits.view'] as $permission) {
                    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo($permission));
                    $this->postJson($this->startPath, $this->startInput)->assertForbidden();
                    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo($permission));
                }
                $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['unit_id' => $this->dataA['other']->id])->save());
                $this->postJson($this->startPath, $this->startInput)->assertForbidden();
                $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['unit_id' => $this->dataA['unit']->id])->save());
                $this->context->run($this->a['tenant'], function (): void {
                    auth()->setUser($this->a['user']->fresh());
                    app(ManageProfessionalExamAuthorization::class)->revoke($this->performer, $this->grant, 'Revogada antes do início');
                });
                $this->postJson($this->startPath, $this->startInput)->assertForbidden();
                expect(DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->value('status'))->toBe('pending');
            });

            it('refuses inactive rooms injected authority missing room evidence and cross-patient bindings', function (): void {
                foreach (['performed_by' => $this->performer->id, 'authorization_id' => $this->grant->id, 'tenant_id' => $this->b['tenant']->id, 'started_at' => '2026-11-05', 'expected_status' => 'in_progress', 'idempotency_key' => 'invalid'] as $field => $value) {
                    $this->postJson($this->startPath, [$field => $value] + $this->startInput)->assertUnprocessable();
                }
                $this->postJson(str_replace($this->dataA['patient']->public_id, $this->dataB['patient']->public_id, $this->startPath), $this->startInput)->assertNotFound();
                $authorizedAt = DB::table('exam_orders')->where('id', $this->dataA['order']->id)->value('authorized_at');
                DB::table('exam_orders')->where('id', $this->dataA['order']->id)->update(['authorized_at' => now('UTC')->addDay()]);
                $this->postJson($this->startPath, $this->startInput)->assertUnprocessable();
                DB::table('exam_orders')->where('id', $this->dataA['order']->id)->update(['authorized_at' => $authorizedAt]);
                DB::table('rooms')->where('id', $this->assignmentRooms['own']->id)->update(['is_active' => false]);
                $this->postJson($this->startPath, $this->startInput)->assertUnprocessable();
                DB::table('rooms')->where('id', $this->assignmentRooms['own']->id)->update(['is_active' => true]);
                $row = DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first();
                $context = json_decode(Crypt::decryptString($row->context_encrypted), true, 512, JSON_THROW_ON_ERROR);
                unset($context['room_assignment']);
                DB::table('exam_executions')->where('id', $row->id)->update(['room_id' => null, 'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR))]);
                $this->postJson($this->startPath, $this->startInput)->assertUnprocessable();
            });

            it('preserves billing after start and rejects corrupted start evidence in every reader', function (): void {
                $this->post($this->startPath, $this->startInput)->assertRedirect();
                $this->context->run($this->a['tenant'], function (): void {
                    auth()->setUser($this->a['user']->fresh());
                    $receipt = app(CreateClinicalReceivable::class)->execute($this->dataA['patient'], $this->dataA['order'], '2026-12-10');
                    expect($receipt->amount)->toBe('12.34');
                    app(TransitionClinicalReceivable::class)->execute($this->dataA['patient'], $this->dataA['order'], $receipt, ReceivableTransition::Settle, 'Baixa sintética após início', (string) Str::uuid(), 'open');
                    expect($receipt->fresh()->status)->toBe('paid');
                });
                $row = DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first();
                $original = json_decode(Crypt::decryptString($row->context_encrypted), true, 512, JSON_THROW_ON_ERROR);
                foreach (['actor_id' => 0, 'professional_id' => 0, 'authorization_id' => 0, 'occurred_at' => '2000-01-01T00:00:00+00:00', 'key_hash' => 'invalid'] as $field => $value) {
                    $context = $original;
                    $context['start'][$field] = $value;
                    DB::table('exam_executions')->where('id', $row->id)->update(['context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR))]);
                    $this->getJson($this->executionBase)->assertUnprocessable();
                    $this->getJson($this->visitUrl)->assertUnprocessable();
                    $this->postJson($this->startPath, $this->startInput)->assertUnprocessable();
                }
                DB::table('exam_executions')->where('id', $row->id)->update(['context_encrypted' => $row->context_encrypted]);
                DB::table('exam_order_items')->where('id', $this->prepareInput['exam_order_item_id'])->update(['status' => 'authorized']);
                $this->getJson($this->visitUrl)->assertUnprocessable();
                $this->getJson($this->executionBase)->assertUnprocessable();
            });

            it('continues remaining items after the first start and preserves the first parent events', function (): void {
                $this->context->run($this->a['tenant'], function (): void {
                    auth()->setUser($this->a['user']->fresh());
                    $secondExam = ExamType::query()->create(['code' => 'SECOND-START', 'name' => 'Segundo exame sintético', 'price' => '5.00']);
                    app(ManageProfessionalExamAuthorization::class)->grant($this->performer, $secondExam, 'Concessão sintética do segundo tipo');
                    $order = app(CreateExamOrder::class)->execute($this->dataA['patient'], [$this->dataA['exam']->id, $secondExam->id], (string) Str::uuid());
                    $order = app(TransitionExamOrder::class)->execute($this->dataA['patient'], $order, OrderTransition::Authorize, 'draft');
                    $appointment = app(CreateAppointment::class)->execute($this->dataA['patient'], (int) $this->dataA['unit']->id, '2026-12-11', (string) Str::uuid());
                    $appointment = app(ConfirmAppointment::class)->execute($this->dataA['patient'], $appointment, (string) Str::uuid(), 'pre_agendado');
                    $visit = app(CheckInAppointment::class)->execute($this->dataA['patient'], $appointment, (int) $order->id, (string) Str::uuid(), 'confirmado');
                    $firstEvent = null;
                    foreach ($order->items()->orderBy('id')->get() as $index => $item) {
                        $attempt = app(PrepareExamExecution::class)->execute($this->dataA['patient'], $visit, (int) $item->id, (string) Str::uuid(), $index === 0 ? 'open' : 'in_progress');
                        app(AssignExecutionRoom::class)->execute($this->dataA['patient'], $visit, $attempt, (int) $this->assignmentRooms['own']->id, (string) Str::uuid(), 'pending');
                        $attempt = app(StartExamExecution::class)->execute($this->dataA['patient'], $visit, $attempt, (string) Str::uuid(), 'pending');
                        $event = $attempt->preparationSnapshot()['start'];
                        $firstEvent ??= $event;
                        expect($visit->fresh()->attendanceSnapshot()['start'])->toBe($firstEvent);
                        expect($order->fresh()->contextSnapshot()['start'])->toBe($firstEvent);
                    }
                    $receipt = app(CreateClinicalReceivable::class)->execute($this->dataA['patient'], $order, '2026-12-11');
                    expect($receipt->amount)->toBe('17.34');
                });
            });

            it('rolls back all four clinical states if audit fails', function (): void {
                $tables = ['exam_executions' => $this->assignmentAttempt->id, 'visits' => $this->visit->id, 'exam_orders' => $this->dataA['order']->id, 'exam_order_items' => $this->prepareInput['exam_order_item_id']];
                $before = [];
                foreach ($tables as $table => $id) {
                    $before[$table] = DB::table($table)->where('id', $id)->first();
                }
                app()->instance(RegistryAudit::class, new class
                {
                    public function record(...$arguments): never
                    {
                        throw new RuntimeException('Synthetic start audit failure.');
                    }
                });
                try {
                    $this->context->run($this->a['tenant'], function (): void {
                        auth()->setUser($this->a['user']->fresh());
                        expect(fn () => app(StartExamExecution::class)->execute($this->dataA['patient'], $this->visit, $this->assignmentAttempt, $this->startInput['idempotency_key'], 'pending'))->toThrow(RuntimeException::class);
                    });
                } finally {
                    app()->forgetInstance(RegistryAudit::class);
                }
                foreach ($tables as $table => $id) {
                    expect(DB::table($table)->where('id', $id)->first())->toEqual($before[$table]);
                }
                $this->post($this->startPath, $this->startInput)->assertRedirect();
            });
            describe('execution completion', function (): void {
                beforeEach(function (): void {
                    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('executions.complete'));
                    $this->post($this->startPath, $this->startInput)->assertRedirect();
                    $this->completePath = $this->executionBase.'/'.$this->assignmentAttempt->id.'/complete';
                    $this->completeInput = ['expected_status' => 'in_progress', 'idempotency_key' => (string) Str::uuid()];
                });

                it('completes the attempt item and final order atomically while preserving start visit answers and billing', function (): void {
                    $this->get($this->executionBase)->assertOk()->assertSee('Concluir execução');
                    $healthRecord = $this->context->run($this->a['tenant'], function () {
                        $this->a['user']->givePermissionTo(['catalog.manage', 'health_records.view', 'health_records.create']);
                        auth()->setUser($this->a['user']->fresh());
                        $group = app(CreateHealthQuestionGroup::class)->execute(['code' => 'COMPLETE-SYN', 'name' => 'Grupo sintético', 'ordem' => 0]);
                        $question = app(PublishHealthQuestion::class)->execute($group, ['code' => 'COMPLETE-Q', 'label' => 'Pergunta sintética', 'ordem' => 0]);

                        return app(CollectHealthAnswer::class)->execute($this->dataA['patient'], $this->visit, (int) $question->id, 'Resposta sintética antes da conclusão', (string) Str::uuid());
                    });
                    $beforeVisit = DB::table('visits')->where('id', $this->visit->id)->first();
                    $this->post($this->completePath, $this->completeInput)->assertRedirect($this->executionBase);
                    $this->context->run($this->a['tenant'], function (): void {
                        $attempt = $this->assignmentAttempt->fresh();
                        $history = $attempt->preparationSnapshot();
                        expect($attempt->status)->toBe('completed')->and($attempt->finished_at)->not->toBeNull();
                        expect($history['completion']['actor_id'])->toBe((int) $this->a['user']->id)->and($history['completion']['authorization_id'])->toBe((int) $this->grant->id);
                        expect($history['start']['authorization_id'])->toBe((int) $this->grant->id);
                        $order = $this->dataA['order']->fresh();
                        expect($order->status)->toBe('completed')->and($order->items()->sole()->status)->toBe('completed');
                        expect($order->contextSnapshot()['completion']['execution_id'])->toBe((int) $attempt->id);
                        auth()->setUser($this->a['user']->fresh());
                        $receipt = app(CreateClinicalReceivable::class)->execute($this->dataA['patient'], $order, '2026-12-10');
                        expect($receipt->amount)->toBe('12.34');
                        app(TransitionClinicalReceivable::class)->execute($this->dataA['patient'], $order, $receipt, ReceivableTransition::Settle, 'Baixa sintética após conclusão', (string) Str::uuid(), 'open');
                        expect($receipt->fresh()->status)->toBe('paid');
                    });
                    expect(DB::table('visits')->where('id', $this->visit->id)->first())->toEqual($beforeVisit);
                    $clinicalBase = $this->visitUrl.'/health-records';
                    $this->get($clinicalBase.'/'.$healthRecord->public_id)->assertOk()->assertSee('Resposta sintética antes da conclusão');
                    $this->context->run($this->a['tenant'], function () use ($healthRecord): void {
                        auth()->setUser($this->a['user']->fresh());
                        $questionId = $healthRecord->snapshot()['answer']['question_id'];
                        $newRecord = app(CollectHealthAnswer::class)->execute($this->dataA['patient'], $this->visit, $questionId, 'Resposta sintética após exames', (string) Str::uuid());
                        expect($newRecord->snapshot()['answer']['answer'])->toBe('Resposta sintética após exames');
                    });
                    $this->get($this->executionBase)->assertOk()->assertSee('Execução concluída')->assertDontSee('Concluir execução');
                    $this->get($this->visitUrl)->assertOk()->assertSee('Atendimento em execução');
                    $orderPath = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/orders/'.$this->dataA['order']->public_id;
                    $this->get($orderPath)->assertOk()->assertSee('Exames concluídos');
                    $this->postJson($this->executionBase.'/'.$this->assignmentAttempt->id.'/cancel', $this->cancelInput)->assertUnprocessable();
                    expect(DB::table('activity_log')->where('event', 'execution_completed')->count())->toBe(1);
                    expect(DB::table('activity_log')->where('event', 'exam_order_completed')->count())->toBe(1);
                });

                it('replays start and completion after revocation but denies new clinical acts and conflicting keys', function (): void {
                    $this->post($this->completePath, $this->completeInput)->assertRedirect();
                    $before = DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first();
                    $this->context->run($this->a['tenant'], function (): void {
                        auth()->setUser($this->a['user']->fresh());
                        app(ManageProfessionalExamAuthorization::class)->revoke($this->performer, $this->grant, 'Revogação posterior à conclusão');
                    });
                    $this->post($this->startPath, $this->startInput)->assertRedirect();
                    $this->post($this->completePath, ['idempotency_key' => strtoupper($this->completeInput['idempotency_key'])] + $this->completeInput)->assertRedirect();
                    expect(DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first())->toEqual($before);
                    $this->postJson($this->completePath, ['idempotency_key' => (string) Str::uuid()] + $this->completeInput)->assertUnprocessable();
                    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('executions.complete'));
                    $this->postJson($this->completePath, $this->completeInput)->assertForbidden();
                });

                it('requires current clinical completion permission own executor and a current specific grant', function (): void {
                    foreach (['executions.complete', 'executions.perform', 'clinical.access', 'visits.view'] as $permission) {
                        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo($permission));
                        $this->postJson($this->completePath, $this->completeInput)->assertForbidden();
                        $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo($permission));
                    }
                    $this->context->run($this->a['tenant'], function (): void {
                        auth()->setUser($this->a['user']->fresh());
                        app(ManageProfessionalExamAuthorization::class)->revoke($this->performer, $this->grant, 'Revogada durante execução');
                    });
                    $this->postJson($this->completePath, $this->completeInput)->assertForbidden();
                    expect(DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->value('status'))->toBe('in_progress');
                    $this->context->run($this->a['tenant'], function (): void {
                        auth()->setUser($this->a['user']->fresh());
                        app(ManageProfessionalExamAuthorization::class)->grant($this->performer, $this->dataA['exam'], 'Nova concessão sintética');
                    });
                    $this->post($this->completePath, $this->completeInput)->assertRedirect();
                    $this->context->run($this->a['tenant'], function (): void {
                        $history = $this->assignmentAttempt->fresh()->preparationSnapshot();
                        expect($history['completion']['authorization_id'])->not->toBe($history['start']['authorization_id']);
                    });
                });

                it('rejects authoritative payloads foreign parents and corrupt finish or aggregate evidence', function (): void {
                    foreach (['performed_by' => 1, 'finished_at' => '2026-11-05', 'context_encrypted' => 'invalid', 'expected_status' => 'pending', 'idempotency_key' => 'invalid'] as $field => $value) {
                        $this->postJson($this->completePath, [$field => $value] + $this->completeInput)->assertUnprocessable();
                    }
                    $this->postJson(str_replace($this->dataA['patient']->public_id, $this->dataB['patient']->public_id, $this->completePath), $this->completeInput)->assertNotFound();
                    $this->context->run($this->a['tenant'], function (): void {
                        auth()->setUser($this->a['user']->fresh());
                        $otherExam = ExamType::query()->create(['code' => 'STALE-COMPLETE', 'name' => 'Outro tipo sintético', 'price' => '0.00']);
                        $stale = $this->assignmentAttempt->fresh();
                        $stale->exam_type_id = $otherExam->id;
                        expect(fn () => app(CompleteExamExecution::class)->execute($this->dataA['patient'], $this->visit, $stale, $this->completeInput['idempotency_key'], 'in_progress'))->toThrow(ValidationException::class);
                    });
                    $this->post($this->completePath, $this->completeInput)->assertRedirect();
                    $row = DB::table('exam_executions')->where('id', $this->assignmentAttempt->id)->first();
                    $original = json_decode(Crypt::decryptString($row->context_encrypted), true, 512, JSON_THROW_ON_ERROR);
                    foreach (['actor_id' => 0, 'authorization_id' => 0, 'occurred_at' => '2000-01-01T00:00:00+00:00', 'key_hash' => 'invalid'] as $field => $value) {
                        $context = $original;
                        $context['completion'][$field] = $value;
                        DB::table('exam_executions')->where('id', $row->id)->update(['context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR))]);
                        $this->getJson($this->executionBase)->assertUnprocessable();
                        $this->getJson($this->visitUrl)->assertUnprocessable();
                        $this->postJson($this->completePath, $this->completeInput)->assertUnprocessable();
                    }
                    DB::table('exam_executions')->where('id', $row->id)->update(['context_encrypted' => $row->context_encrypted]);
                    $orderRow = DB::table('exam_orders')->where('id', $this->dataA['order']->id)->first();
                    $context = json_decode(Crypt::decryptString($orderRow->context_encrypted), true, 512, JSON_THROW_ON_ERROR);
                    $context['completion']['item_ids'] = [];
                    DB::table('exam_orders')->where('id', $orderRow->id)->update(['context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR))]);
                    $this->getJson($this->visitUrl)->assertUnprocessable();
                });

                it('keeps a multi-visit order in progress until every item is complete and preserves its first start', function (): void {
                    $this->context->run($this->a['tenant'], function (): void {
                        auth()->setUser($this->a['user']->fresh());
                        $secondExam = ExamType::query()->create(['code' => 'COMPLETE-SECOND', 'name' => 'Segundo exame sintético', 'price' => '5.00']);
                        app(ManageProfessionalExamAuthorization::class)->grant($this->performer, $secondExam, 'Concessão sintética');
                        $order = app(CreateExamOrder::class)->execute($this->dataA['patient'], [$this->dataA['exam']->id, $secondExam->id], (string) Str::uuid());
                        $order = app(TransitionExamOrder::class)->execute($this->dataA['patient'], $order, OrderTransition::Authorize, 'draft');
                        $visits = [];
                        foreach (['2026-12-11', '2026-12-12'] as $date) {
                            $appointment = app(CreateAppointment::class)->execute($this->dataA['patient'], (int) $this->dataA['unit']->id, $date, (string) Str::uuid());
                            $appointment = app(ConfirmAppointment::class)->execute($this->dataA['patient'], $appointment, (string) Str::uuid(), 'pre_agendado');
                            $visits[] = app(CheckInAppointment::class)->execute($this->dataA['patient'], $appointment, (int) $order->id, (string) Str::uuid(), 'confirmado');
                        }
                        $firstStart = null;
                        foreach ($order->items()->orderBy('id')->get() as $index => $item) {
                            $visit = $visits[$index];
                            $attempt = app(PrepareExamExecution::class)->execute($this->dataA['patient'], $visit, (int) $item->id, (string) Str::uuid(), 'open');
                            app(AssignExecutionRoom::class)->execute($this->dataA['patient'], $visit, $attempt, (int) $this->assignmentRooms['own']->id, (string) Str::uuid(), 'pending');
                            $attempt = app(StartExamExecution::class)->execute($this->dataA['patient'], $visit, $attempt, (string) Str::uuid(), 'pending');
                            $firstStart ??= $attempt->preparationSnapshot()['start'];
                            $attempt = app(CompleteExamExecution::class)->execute($this->dataA['patient'], $visit, $attempt, (string) Str::uuid(), 'in_progress');
                            expect($attempt->preparationSnapshot()['completion']['execution_id'])->toBe((int) $attempt->id);
                            $order = $order->fresh();
                            expect($order->status)->toBe($index === 0 ? 'in_progress' : 'completed');
                            expect($order->contextSnapshot()['start'])->toBe($firstStart);
                            expect($visit->fresh()->attendanceSnapshot()['start']['execution_id'])->toBe((int) $attempt->id);
                        }
                        expect($order->contextSnapshot()['completion']['item_ids'])->toHaveCount(2);
                        $receipt = app(CreateClinicalReceivable::class)->execute($this->dataA['patient'], $order, '2026-12-12');
                        expect($receipt->amount)->toBe('17.34');
                    });
                });

                it('denies a different professional inactive actors missing second factor and another unit in direct actions', function (): void {
                    $this->context->run($this->a['tenant'], function (): void {
                        $actor = User::query()->create(['name' => 'Outro executor sintético', 'email' => 'other-complete@example.test', 'password' => Str::random(32)]);
                        $actor->forceFill(['unit_id' => $this->dataA['unit']->id, 'two_factor_confirmed_at' => now()])->save();
                        $actor->givePermissionTo($this->a['user']->getAllPermissions()->pluck('name')->all());
                        app(SaveProfessional::class)->execute(['user_id' => $actor->id]);
                        auth()->setUser($actor->fresh());
                        expect(fn () => app(CompleteExamExecution::class)->execute($this->dataA['patient'], $this->visit, $this->assignmentAttempt, $this->completeInput['idempotency_key'], 'in_progress'))->toThrow(AuthorizationException::class);
                        foreach ([['is_active' => false], ['is_active' => true, 'two_factor_confirmed_at' => null], ['two_factor_confirmed_at' => now(), 'unit_id' => $this->dataA['other']->id]] as $change) {
                            $this->a['user']->forceFill($change)->save();
                            auth()->setUser($this->a['user']->fresh());
                            expect(fn () => app(CompleteExamExecution::class)->execute($this->dataA['patient'], $this->visit, $this->assignmentAttempt, $this->completeInput['idempotency_key'], 'in_progress'))->toThrow(AuthorizationException::class);
                        }
                    });
                });

                it('rolls back completion item and order if audit fails', function (): void {
                    $tables = ['exam_executions' => $this->assignmentAttempt->id, 'exam_order_items' => $this->prepareInput['exam_order_item_id'], 'exam_orders' => $this->dataA['order']->id];
                    $before = [];
                    foreach ($tables as $table => $id) {
                        $before[$table] = DB::table($table)->where('id', $id)->first();
                    }
                    app()->instance(RegistryAudit::class, new class
                    {
                        public function record(...$arguments): never
                        {
                            throw new RuntimeException('Synthetic completion audit failure.');
                        }
                    });
                    try {
                        $this->context->run($this->a['tenant'], function (): void {
                            auth()->setUser($this->a['user']->fresh());
                            expect(fn () => app(CompleteExamExecution::class)->execute($this->dataA['patient'], $this->visit, $this->assignmentAttempt, $this->completeInput['idempotency_key'], 'in_progress'))->toThrow(RuntimeException::class);
                        });
                    } finally {
                        app()->forgetInstance(RegistryAudit::class);
                    }
                    foreach ($tables as $table => $id) {
                        expect(DB::table($table)->where('id', $id)->first())->toEqual($before[$table]);
                    }
                    $this->post($this->completePath, $this->completeInput)->assertRedirect();
                });
            });
        });
    });

});
