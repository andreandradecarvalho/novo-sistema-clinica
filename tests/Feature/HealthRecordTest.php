<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateHealthQuestionGroup;
use App\Domain\Catalog\Actions\PublishHealthQuestion;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Clinical\Actions\CollectHealthAnswer;
use App\Domain\Clinical\Models\HealthAnswer;
use App\Domain\Clinical\Models\HealthRecord;
use App\Domain\Orders\Actions\CreateExamOrder;
use App\Domain\Orders\Actions\TransitionExamOrder;
use App\Domain\Orders\Enums\OrderTransition;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Actions\ConfirmAppointment;
use App\Domain\Scheduling\Actions\CreateAppointment;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Actions\CancelVisit;
use App\Domain\Visits\Actions\CheckInAppointment;
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
    foreach (['a', 'b'] as $key) {
        $this->$key = Clinics::create();
        $clinic = $this->$key;
        $this->{'data'.strtoupper($key)} = $this->context->run($clinic['tenant'], function () use ($clinic): array {
            $clinic['user']->givePermissionTo(['patients.view', 'units.view', 'orders.view', 'orders.manage', 'catalog.view', 'catalog.manage', 'appointments.view', 'appointments.create', 'appointments.confirm', 'visits.view', 'visits.check_in', 'visits.cancel', 'clinical.access', 'health_records.view', 'health_records.create']);
            $unit = Unit::query()->create(['name' => 'Unidade sintética de coleta']);
            $other = Unit::query()->create(['name' => 'Outra unidade sintética']);
            $clinic['user']->forceFill(['unit_id' => $unit->id, 'two_factor_confirmed_at' => now()])->save();
            auth()->setUser($clinic['user']->fresh());
            $patient = Patient::query()->create(['name' => 'Paciente sintético de coleta']);
            $exam = ExamType::query()->create(['code' => 'COLLECT', 'name' => 'Exame sintético', 'price' => '0.00']);
            $order = app(CreateExamOrder::class)->execute($patient, [$exam->id], (string) Str::uuid());
            $order = app(TransitionExamOrder::class)->execute($patient, $order, OrderTransition::Authorize, 'draft');
            $appointment = app(CreateAppointment::class)->execute($patient, (int) $unit->id, '2026-12-12', (string) Str::uuid());
            $appointment = app(ConfirmAppointment::class)->execute($patient, $appointment, (string) Str::uuid(), 'pre_agendado');
            $visit = app(CheckInAppointment::class)->execute($patient, $appointment, (int) $order->id, (string) Str::uuid(), 'confirmado');
            $group = app(CreateHealthQuestionGroup::class)->execute(['code' => 'SYN-GROUP', 'name' => 'Grupo sintético', 'ordem' => 0]);
            $question = app(PublishHealthQuestion::class)->execute($group, ['code' => 'SYN-Q', 'label' => 'Pergunta sintética <script>synthetic()</script>', 'ordem' => 0]);

            return compact('unit', 'other', 'patient', 'order', 'appointment', 'visit', 'group', 'question');
        });
    }
    $this->base = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/visits/'.$this->dataA['visit']->public_id.'/health-records';
    $this->input = ['question_id' => $this->dataA['question']->id, 'answer' => 'Resposta sintética <script>answer()</script>', 'idempotency_key' => (string) Str::uuid()];
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->withSession(['auth.password_confirmed_at' => time()]);
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('collects one immutable encrypted answer with a server snapshot actor time and visit links', function (): void {
    $this->get($this->base.'/create?question_id='.$this->input['question_id'])->assertOk()->assertSee('versão 1')->assertSee('&lt;script&gt;synthetic()&lt;/script&gt;', false);
    $this->post($this->base, $this->input)->assertRedirect();
    $record = $this->context->run($this->a['tenant'], fn () => HealthRecord::query()->sole());
    $this->get($this->base.'/'.$record->public_id)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('&lt;script&gt;answer()&lt;/script&gt;', false)->assertDontSee('<script>answer()</script>', false);
    $this->get($this->base)->assertOk()->assertSee('Consultar resposta')->assertDontSee($this->input['answer']);
    $this->context->run($this->a['tenant'], function () use ($record): void {
        $history = $record->snapshot();
        expect($history['actor_id'])->toBe((int) $this->a['user']->id)->and($history['source'])->toBe('visit');
        expect($history['answer']['version'])->toBe(1)->and($history['answer']['answer'])->toBe($this->input['answer']);
        expect($record->patient_id)->toBe($this->dataA['patient']->id)->and($record->appointment_id)->toBe($this->dataA['appointment']->id)->and($record->visit_id)->toBe($this->dataA['visit']->id);
        expect($record->getRawOriginal('context_encrypted'))->not->toContain('request_hash');
        expect($record->toArray())->not->toHaveKey('context_encrypted');
        $answer = HealthAnswer::query()->sole();
        expect($answer->getRawOriginal('answer_encrypted'))->not->toContain('Resposta sintética')->and($answer->toArray())->not->toHaveKey('answer_encrypted');
        expect(fn () => $record->forceFill(['filled_by' => $this->b['user']->id])->save())->toThrow(LogicException::class);
        expect(fn () => $answer->forceFill(['answer_encrypted' => 'invalid'])->save())->toThrow(LogicException::class);
        expect(fn () => $record->delete())->toThrow(LogicException::class);
        expect(fn () => $answer->delete())->toThrow(LogicException::class);
        expect($this->dataA['order']->fresh()->status)->toBe('authorized')->and($this->dataA['visit']->fresh()->status)->toBe('open');
    });
    expect(DB::table('activity_log')->where('event', 'health_answer_collected')->count())->toBe(1);
    expect(DB::table('activity_log')->where('log_name', 'clinical')->pluck('properties')->filter())->toBeEmpty();
});

it('replays only the same intent and preserves old versions after publication and visit cancellation', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $before = DB::table('health_records')->sole();
    $answerBefore = DB::table('health_answers')->sole();
    $this->post($this->base, ['idempotency_key' => strtoupper($this->input['idempotency_key'])] + $this->input)->assertRedirect();
    foreach (['answer' => 'Resposta diferente', 'question_id' => $this->dataB['question']->id] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable();
    }
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        app(PublishHealthQuestion::class)->execute($this->dataA['group'], ['label' => 'Enunciado sintético revisado'], $this->dataA['question']);
        app(CancelVisit::class)->execute($this->dataA['patient'], $this->dataA['visit'], 'Cancelamento sintético', (string) Str::uuid(), 'open');
    });
    $this->get($this->base.'/'.$before->public_id)->assertOk()->assertSee('versão 1')->assertDontSee('Enunciado sintético revisado');
    $this->post($this->base, $this->input)->assertRedirect();
    $this->postJson($this->base, ['idempotency_key' => (string) Str::uuid()] + $this->input)->assertUnprocessable();
    $this->getJson($this->base.'/create')->assertUnprocessable();
    expect(DB::table('health_records')->sole())->toEqual($before)->and(DB::table('health_answers')->sole())->toEqual($answerBefore);
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('health_records.create'));
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('clinical.access'));
    $this->getJson($this->base.'/'.$before->public_id)->assertForbidden();
});

it('allows explicit historical versions and new submissions without overwriting prior answers', function (): void {
    $version = $this->context->run($this->a['tenant'], function () {
        auth()->setUser($this->a['user']->fresh());

        return app(PublishHealthQuestion::class)->execute($this->dataA['group'], ['label' => 'Versão dois sintética'], $this->dataA['question']);
    });
    $this->post($this->base, $this->input)->assertRedirect();
    $this->post($this->base, ['question_id' => $version->id, 'answer' => 'Nova resposta sintética', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
    $this->context->run($this->a['tenant'], function (): void {
        $records = HealthRecord::query()->orderBy('id')->get();
        expect($records)->toHaveCount(2)->and($records[0]->snapshot()['answer']['version'])->toBe(1)->and($records[1]->snapshot()['answer']['version'])->toBe(2);
    });
});

it('requires separate clinical permissions active actor second factor and exact unit even on replay', function (): void {
    foreach (['clinical.access', 'health_records.view', 'health_records.create', 'catalog.view', 'visits.view'] as $permission) {
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo($permission));
        $this->postJson($this->base, $this->input)->assertForbidden();
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo($permission));
    }
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['unit_id' => $this->dataA['other']->id])->save());
    $this->getJson($this->base)->assertForbidden();
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        foreach ([['unit_id' => $this->dataA['unit']->id, 'is_active' => false], ['is_active' => true, 'two_factor_confirmed_at' => null]] as $change) {
            $this->a['user']->forceFill($change)->save();
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(CollectHealthAnswer::class)->execute($this->dataA['patient'], $this->dataA['visit'], (int) $this->input['question_id'], $this->input['answer'], $this->input['idempotency_key']))->toThrow(AuthorizationException::class);
        }
    });
});

it('rejects foreign questions patient visit and record bindings and absence of context', function (): void {
    $this->postJson($this->base, ['question_id' => $this->dataB['question']->id] + $this->input)->assertNotFound();
    $this->get($this->base.'/create?question_id='.$this->dataB['question']->id)->assertNotFound();
    $foreign = $this->context->run($this->b['tenant'], function () {
        auth()->setUser($this->b['user']->fresh());

        return app(CollectHealthAnswer::class)->execute($this->dataB['patient'], $this->dataB['visit'], (int) $this->dataB['question']->id, 'Resposta externa sintética', (string) Str::uuid());
    });
    $this->get($this->base.'/'.$foreign->public_id)->assertNotFound();
    $this->postJson(str_replace($this->dataA['patient']->public_id, $this->dataB['patient']->public_id, $this->base), $this->input)->assertNotFound();
    $this->context->set(null);
    expect(fn () => HealthRecord::query()->count())->toThrow(MissingTenant::class);
    expect(fn () => $foreign->snapshot())->toThrow(MissingTenant::class);
    $this->context->run($this->a['tenant'], function () use ($foreign): void {
        expect(fn () => $foreign->snapshot())->toThrow(LogicException::class);
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(CollectHealthAnswer::class)->execute($this->dataB['patient'], $this->dataB['visit'], (int) $this->input['question_id'], 'Sintético', (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
    });
});

it('rejects authoritative input unsupported types blanks oversized answers and does not flash health data', function (): void {
    foreach (['patient_id' => $this->dataB['patient']->id, 'filled_by' => $this->b['user']->id, 'question_label_snapshot' => 'Forjado', 'answer_encrypted' => 'Forjado', 'answer' => '  ', 'idempotency_key' => 'invalid'] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable();
    }
    $this->postJson($this->base, ['answer' => str_repeat('a', 10001)] + $this->input)->assertUnprocessable();
    $this->post($this->base, ['question_id' => 0] + $this->input)->assertRedirect()->assertSessionHasErrors('question_id');
    expect(session()->getOldInput())->not->toHaveKey('answer');
    DB::table('health_questions')->where('id', $this->input['question_id'])->update(['type' => 'boolean']);
    $this->postJson($this->base, $this->input)->assertUnprocessable();
    expect(DB::table('health_records')->count())->toBe(0);
});

it('fails closed on substituted ciphertext snapshots owner time and parent evidence', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $record = DB::table('health_records')->sole();
    $answer = DB::table('health_answers')->sole();
    $otherRecord = $this->context->run($this->a['tenant'], function () {
        auth()->setUser($this->a['user']->fresh());

        return app(CollectHealthAnswer::class)->execute($this->dataA['patient'], $this->dataA['visit'], (int) $this->input['question_id'], 'Outra resposta sintética', (string) Str::uuid());
    });
    DB::table('health_answers')->where('id', $answer->id)->update(['answer_encrypted' => $this->context->run($this->a['tenant'], fn () => $otherRecord->answers()->sole()->getRawOriginal('answer_encrypted'))]);
    $this->getJson($this->base.'/'.$record->public_id)->assertUnprocessable();
    DB::table('health_answers')->where('id', $answer->id)->update((array) $answer);
    DB::table('health_records')->where('id', $record->id)->update(['context_encrypted' => $otherRecord->getRawOriginal('context_encrypted')]);
    $this->getJson($this->base.'/'.$record->public_id)->assertUnprocessable();
    DB::table('health_records')->where('id', $record->id)->update((array) $record);
    $otherPatient = $this->context->run($this->a['tenant'], fn () => Patient::query()->create(['name' => 'Outra pessoa sintética']));
    DB::table('health_records')->where('id', $record->id)->update(['patient_id' => $otherPatient->id]);
    $this->getJson($this->base.'/'.$record->public_id)->assertUnprocessable();
    DB::table('health_records')->where('id', $record->id)->update((array) $record);
    foreach (['answer_encrypted' => 'invalid', 'question_label_snapshot' => 'Alterado'] as $field => $value) {
        DB::table('health_answers')->where('id', $answer->id)->update([$field => $value]);
        $this->getJson($this->base.'/'.$record->public_id)->assertUnprocessable();
        DB::table('health_answers')->where('id', $answer->id)->update((array) $answer);
    }
    foreach (['context_encrypted' => 'invalid', 'filled_at' => '2000-01-01 00:00:00'] as $field => $value) {
        DB::table('health_records')->where('id', $record->id)->update([$field => $value]);
        $this->getJson($this->base.'/'.$record->public_id)->assertUnprocessable();
        DB::table('health_records')->where('id', $record->id)->update((array) $record);
    }
    $this->context->run($this->a['tenant'], function () use ($record): void {
        $model = HealthRecord::query()->findOrFail($record->id);
        $model->tenant_id = $this->b['tenant']->id;
        expect(fn () => $model->snapshot())->toThrow(LogicException::class);
    });
    expect(fn () => DB::table('health_records')->where('id', $record->id)->update(['patient_id' => $this->dataB['patient']->id]))->toThrow(QueryException::class);
    foreach (['visit_id' => $this->dataB['visit']->id, 'appointment_id' => $this->dataB['appointment']->id, 'filled_by' => $this->b['user']->id] as $field => $id) {
        expect(fn () => DB::table('health_records')->where('id', $record->id)->update([$field => $id]))->toThrow(QueryException::class);
    }
    expect(fn () => DB::table('health_answers')->where('id', $answer->id)->update(['question_id' => $this->dataB['question']->id]))->toThrow(QueryException::class);
});

it('rolls back record answer and intention when audit fails and can retry', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(...$arguments): never
        {
            throw new RuntimeException('Synthetic clinical audit failure.');
        }
    });
    try {
        $this->context->run($this->a['tenant'], function (): void {
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(CollectHealthAnswer::class)->execute($this->dataA['patient'], $this->dataA['visit'], (int) $this->input['question_id'], $this->input['answer'], $this->input['idempotency_key']))->toThrow(RuntimeException::class);
        });
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
    expect(DB::table('health_records')->count())->toBe(0)->and(DB::table('health_answers')->count())->toBe(0);
    $this->post($this->base, $this->input)->assertRedirect();
});
