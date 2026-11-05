<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateHealthQuestionGroup;
use App\Domain\Catalog\Actions\PublishHealthQuestion;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Actions\AssignExecutionRoom;
use App\Domain\Executions\Actions\ManageProfessionalExamAuthorization;
use App\Domain\Executions\Actions\PrepareExamExecution;
use App\Domain\Executions\Actions\StartExamExecution;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Orders\Actions\CreateClinicalReceivable;
use App\Domain\Orders\Actions\CreateExamOrder;
use App\Domain\Orders\Actions\TransitionExamOrder;
use App\Domain\Orders\Enums\OrderTransition;
use App\Domain\Patients\Models\Patient;
use App\Domain\Professionals\Actions\SaveProfessional;
use App\Domain\Scheduling\Actions\ConfirmAppointment;
use App\Domain\Scheduling\Actions\CreateAppointment;
use App\Domain\Scheduling\Actions\SaveRoom;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Actions\CheckInAppointment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Tests\Support\Clinics;

it('serializes two real PHP processes into one durable financial or order result', function (string $operation): void {
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    $this->seed();
    $clinic = Clinics::create();
    $context = app(TenantContext::class);
    $processes = [];
    try {
        [$patient, $exam] = $context->run($clinic['tenant'], function () use ($clinic): array {
            $clinic['user']->givePermissionTo(['patients.view', 'orders.view', 'orders.manage', 'catalog.view', 'receivables.view', 'receivables.manage', 'receivables.settle', 'receivables.cancel', 'units.view', 'appointments.view', 'appointments.create', 'appointments.cancel', 'appointments.confirm', 'visits.view', 'visits.check_in', 'visits.cancel', 'executions.view', 'executions.prepare', 'executions.cancel', 'executions.assign_room', 'rooms.view', 'rooms.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();

            return [Patient::query()->create(['name' => 'Paciente sintético concorrente']), ExamType::query()->create(['code' => 'CONCURRENT', 'name' => 'Exame sintético', 'price' => '12.34'])];
        });
        $order = in_array($operation, ['receivable', 'settle', 'cancel', 'check-in', 'visit-cancel', 'execution-prepare', 'execution-cancel', 'execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'complete-revoke', 'health-collect'], true) ? $context->run($clinic['tenant'], function () use ($clinic, $patient, $exam) {
            auth()->setUser($clinic['user']->fresh());
            $order = app(CreateExamOrder::class)->execute($patient, [$exam->id], (string) Str::uuid());

            return app(TransitionExamOrder::class)->execute($patient, $order, OrderTransition::Authorize, 'draft');
        }) : null;
        $receipt = in_array($operation, ['settle', 'cancel'], true) ? $context->run($clinic['tenant'], function () use ($clinic, $patient, $order) {
            auth()->setUser($clinic['user']->fresh());

            return app(CreateClinicalReceivable::class)->execute($patient, $order, '2026-12-10');
        }) : null;
        $unit = in_array($operation, ['appointment', 'appointment-cancel', 'appointment-confirm', 'check-in', 'visit-cancel', 'execution-prepare', 'execution-cancel', 'execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'complete-revoke', 'health-collect'], true) ? $context->run($clinic['tenant'], function () use ($clinic) {
            $unit = Unit::query()->create(['name' => 'Unidade sintética concorrente']);
            $clinic['user']->forceFill(['unit_id' => $unit->id])->save();

            return $unit;
        }) : null;
        $appointment = in_array($operation, ['appointment-cancel', 'appointment-confirm', 'check-in', 'visit-cancel', 'execution-prepare', 'execution-cancel', 'execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'complete-revoke', 'health-collect'], true) ? $context->run($clinic['tenant'], function () use ($clinic, $patient, $unit) {
            auth()->setUser($clinic['user']->fresh());

            return app(CreateAppointment::class)->execute($patient, (int) $unit->id, '2026-12-10', (string) Str::uuid());
        }) : null;
        if (in_array($operation, ['check-in', 'visit-cancel', 'execution-prepare', 'execution-cancel', 'execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'complete-revoke', 'health-collect'], true)) {
            $context->run($clinic['tenant'], function () use ($clinic, $patient, $appointment): void {
                auth()->setUser($clinic['user']->fresh());
                app(ConfirmAppointment::class)->execute($patient, $appointment, (string) Str::uuid(), 'pre_agendado');
            });
        }
        $visit = in_array($operation, ['visit-cancel', 'execution-prepare', 'execution-cancel', 'execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'complete-revoke', 'health-collect'], true) ? $context->run($clinic['tenant'], function () use ($clinic, $patient, $appointment, $order) {
            auth()->setUser($clinic['user']->fresh());

            return app(CheckInAppointment::class)->execute($patient, $appointment->fresh(), (int) $order->id, (string) Str::uuid(), 'confirmado');
        }) : null;
        $execution = in_array($operation, ['execution-cancel', 'execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'complete-revoke'], true) ? $context->run($clinic['tenant'], function () use ($clinic, $patient, $visit, $order) {
            auth()->setUser($clinic['user']->fresh());

            return app(PrepareExamExecution::class)->execute($patient, $visit, (int) $order->items()->sole()->id, (string) Str::uuid(), 'open');
        }) : null;
        $room = in_array($operation, ['execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'complete-revoke'], true) ? $context->run($clinic['tenant'], function () use ($clinic, $unit) {
            auth()->setUser($clinic['user']->fresh());

            return app(SaveRoom::class)->execute($unit, ['room_key' => 'CONCURRENT', 'name' => 'Sala sintética concorrente', 'ordem' => 0, 'is_active' => true]);
        }) : null;
        $professional = null;
        $grant = null;
        if (in_array($operation, ['execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'complete-revoke'], true)) {
            [$professional, $grant] = $context->run($clinic['tenant'], function () use ($clinic, $exam, $patient, $visit, $execution, $room, $operation): array {
                $clinic['user']->givePermissionTo(['clinical.access', 'executions.perform', 'executions.complete', 'professionals.view', 'professionals.manage', 'executions.authorizations.view', 'executions.authorizations.manage']);
                auth()->setUser($clinic['user']->fresh());
                $professional = app(SaveProfessional::class)->execute(['user_id' => $clinic['user']->id, 'council' => 'SYN', 'council_uf' => 'ES', 'council_number' => 'SYN-RACE']);
                $grant = app(ManageProfessionalExamAuthorization::class)->grant($professional, $exam, 'Concessão sintética concorrente');
                app(AssignExecutionRoom::class)->execute($patient, $visit, $execution, (int) $room->id, (string) Str::uuid(), 'pending');
                if (in_array($operation, ['execution-complete', 'complete-revoke'], true)) {
                    app(StartExamExecution::class)->execute($patient, $visit, $execution, (string) Str::uuid(), 'pending');
                }

                return [$professional, $grant];
            });
        }
        $question = $operation === 'health-collect' ? $context->run($clinic['tenant'], function () use ($clinic) {
            $clinic['user']->givePermissionTo(['catalog.manage', 'clinical.access', 'health_records.view', 'health_records.create']);
            auth()->setUser($clinic['user']->fresh());
            $group = app(CreateHealthQuestionGroup::class)->execute(['code' => 'SYN-RACE', 'name' => 'Grupo sintético concorrente', 'ordem' => 0]);

            return app(PublishHealthQuestion::class)->execute($group, ['code' => 'SYN-RACE', 'label' => 'Pergunta sintética concorrente', 'ordem' => 0]);
        }) : null;
        $itemId = $order === null ? null : $context->run($clinic['tenant'], fn () => $order->items()->sole()->id);
        $input = json_encode(['operation' => $operation, 'tenant_id' => (int) $clinic['tenant']->id, 'actor_id' => (int) $clinic['user']->id, 'patient_id' => (int) $patient->id, 'exam_id' => (int) $exam->id, 'key' => (string) Str::uuid(), 'order_id' => $order?->id, 'receipt_id' => $receipt?->id, 'unit_id' => $unit?->id, 'appointment_id' => $appointment?->id, 'visit_id' => $visit?->id, 'item_id' => $itemId, 'execution_id' => $execution?->id, 'room_id' => $room?->id, 'professional_id' => $professional?->id, 'grant_id' => $grant?->id, 'question_id' => $question?->id], JSON_THROW_ON_ERROR);
        DB::beginTransaction();
        DB::table('patients')->where('tenant_id', $clinic['tenant']->id)->where('id', $patient->id)->lockForUpdate()->first(['id']);
        foreach (range(1, 2) as $number) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/ConcurrentOrderProbe.php')], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mariadb', 'DB_HOST' => 'db', 'DB_DATABASE' => 'novo_sistema_clinica_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array',
            ]);
            $processInput = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            if (in_array($operation, ['start-cancel', 'start-revoke'], true)) {
                $processInput['operation'] = $number === 1 ? 'execution-start' : ($operation === 'start-cancel' ? 'execution-cancel' : 'grant-revoke');
                $processInput['allow_denial'] = true;
            }
            if ($operation === 'complete-revoke') {
                $processInput['operation'] = $number === 1 ? 'execution-complete' : 'grant-revoke';
                $processInput['allow_denial'] = true;
            }
            $process->setInput(json_encode($processInput, JSON_THROW_ON_ERROR))->setTimeout(20)->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 10;
        do {
            $ready = count(array_filter($processes, fn (Process $process): bool => str_contains($process->getOutput(), 'READY')));
            if ($ready === 2) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        expect($ready)->toBe(2);
        DB::commit();
        $ids = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue();
            preg_match('/RESULT:([0-9A-HJKMNP-TV-Z]+)/', $process->getOutput(), $matches);
            $ids[] = $matches[1] ?? null;
        }
        if ($operation === 'complete-revoke') {
            expect($ids[1])->not->toBeNull();
            expect(DB::table('professional_exam_authorizations')->where('tenant_id', $clinic['tenant']->id)->sole()->revoked_at)->not->toBeNull();
            $status = $ids[0] === null ? 'in_progress' : 'completed';
            expect(DB::table('exam_executions')->where('id', $execution->id)->value('status'))->toBe($status);
            expect(DB::table('exam_order_items')->where('id', $itemId)->value('status'))->toBe($status);
            expect(DB::table('exam_orders')->where('id', $order->id)->value('status'))->toBe($status);
            expect(DB::table('visits')->where('id', $visit->id)->value('status'))->toBe('in_progress');
            expect(DB::table('activity_log')->where('tenant_id', $clinic['tenant']->id)->where('event', 'execution_completed')->count())->toBe($ids[0] === null ? 0 : 1);
            $context->run($clinic['tenant'], fn () => ExamExecution::query()->sole()->preparationSnapshot());

            return;
        }
        if (in_array($operation, ['start-cancel', 'start-revoke'], true)) {
            $row = DB::table('exam_executions')->where('tenant_id', $clinic['tenant']->id)->sole();
            if ($operation === 'start-cancel') {
                expect(count(array_filter($ids)))->toBe(1);
                expect($row->status)->toBeIn(['in_progress', 'canceled']);
            } else {
                expect($ids[1])->not->toBeNull();
                expect(DB::table('professional_exam_authorizations')->where('tenant_id', $clinic['tenant']->id)->sole()->revoked_at)->not->toBeNull();
                expect($row->status)->toBe($ids[0] === null ? 'pending' : 'in_progress');
            }
            $started = $row->status === 'in_progress';
            expect(DB::table('visits')->where('id', $visit->id)->value('status'))->toBe($started ? 'in_progress' : 'open');
            expect(DB::table('exam_orders')->where('id', $order->id)->value('status'))->toBe($started ? 'in_progress' : 'authorized');
            expect(DB::table('exam_order_items')->where('id', $itemId)->value('status'))->toBe($started ? 'in_progress' : 'authorized');
            expect(DB::table('activity_log')->where('tenant_id', $clinic['tenant']->id)->where('event', 'execution_started')->count())->toBe($started ? 1 : 0);
            if ($started) {
                $context->run($clinic['tenant'], fn () => ExamExecution::query()->sole()->preparationSnapshot());
            }

            return;
        }
        expect($ids[0])->not->toBeNull()->and($ids[1])->toBe($ids[0]);
        if ($operation === 'health-collect') {
            expect(DB::table('health_records')->where('tenant_id', $clinic['tenant']->id)->count())->toBe(1);
            expect(DB::table('health_answers')->where('tenant_id', $clinic['tenant']->id)->count())->toBe(1);
        }
        expect(DB::table('exam_orders')->where('tenant_id', $clinic['tenant']->id)->count())->toBe(str_starts_with($operation, 'appointment') ? 0 : 1);
        expect(DB::table('exam_order_items')->where('tenant_id', $clinic['tenant']->id)->count())->toBe(str_starts_with($operation, 'appointment') ? 0 : 1);
        expect(DB::table('activity_log')->where('tenant_id', $clinic['tenant']->id)->where('event', 'exam_order_created')->count())->toBe(str_starts_with($operation, 'appointment') ? 0 : 1);
        if (str_starts_with($operation, 'appointment') || in_array($operation, ['check-in', 'visit-cancel', 'execution-prepare', 'execution-cancel', 'execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'health-collect'], true)) {
            expect(DB::table('appointments')->where('tenant_id', $clinic['tenant']->id)->count())->toBe(1);
            $event = match ($operation) {
                'appointment' => 'appointment_created', 'appointment-cancel' => 'appointment_canceled', 'appointment-confirm' => 'appointment_confirmed', 'check-in' => 'visit_opened', 'visit-cancel' => 'visit_canceled', 'execution-prepare' => 'execution_prepared', 'execution-cancel' => 'execution_canceled', 'execution-room' => 'execution_room_assigned', 'execution-start' => 'execution_started', 'health-collect' => 'health_answer_collected', 'execution-complete' => 'execution_completed',
            };
            $replay = match ($operation) {
                'appointment' => 'appointment_replayed', 'appointment-cancel' => 'appointment_cancellation_replayed', 'appointment-confirm' => 'appointment_confirmation_replayed', 'check-in' => 'visit_check_in_replayed', 'visit-cancel' => 'visit_cancellation_replayed', 'execution-prepare' => 'execution_preparation_replayed', 'execution-cancel' => 'execution_cancellation_replayed', 'execution-room' => 'execution_room_assignment_replayed', 'execution-start' => 'execution_start_replayed', 'health-collect' => 'health_collection_replayed', 'execution-complete' => 'execution_completion_replayed',
            };
            expect(DB::table('visits')->where('tenant_id', $clinic['tenant']->id)->count())->toBe(in_array($operation, ['check-in', 'visit-cancel', 'execution-prepare', 'execution-cancel', 'execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'execution-complete', 'health-collect'], true) ? 1 : 0);
            expect(DB::table('activity_log')->where('tenant_id', $clinic['tenant']->id)->where('event', $event)->count())->toBe(1);
            expect(DB::table('activity_log')->where('tenant_id', $clinic['tenant']->id)->where('event', $replay)->count())->toBe(1);
        } elseif (in_array($operation, ['settle', 'cancel'], true)) {
            $status = $operation === 'settle' ? 'paid' : 'canceled';
            expect(DB::table('clinical_receivables')->where('tenant_id', $clinic['tenant']->id)->sole()->status)->toBe($status);
            expect(DB::table('activity_log')->where('tenant_id', $clinic['tenant']->id)->where('event', 'clinical_receivable_'.$status)->count())->toBe(1);
            expect(DB::table('activity_log')->where('tenant_id', $clinic['tenant']->id)->where('event', 'clinical_receivable_transition_replayed')->count())->toBe(1);
        } else {
            $event = $operation === 'receivable' ? 'clinical_receivable' : 'exam_order';
            expect(DB::table('activity_log')->where('tenant_id', $clinic['tenant']->id)->where('event', $event.'_replayed')->count())->toBe(1);
            expect(DB::table('activity_log')->where('tenant_id', $clinic['tenant']->id)->where('event', $event.'_created')->count())->toBe(1);
        }
        expect(DB::table('clinical_receivables')->where('tenant_id', $clinic['tenant']->id)->count())->toBe(in_array($operation, ['receivable', 'settle', 'cancel'], true) ? 1 : 0);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        // Exact synthetic tenant created by this test; no global cleanup or production target.
        DB::transaction(function () use ($clinic): void {
            foreach (['health_answers', 'health_records', 'health_questions', 'health_question_groups', 'exam_executions', 'professional_exam_authorizations', 'professionals', 'visits', 'appointments', 'clinical_receivables', 'exam_order_items', 'exam_orders', 'activity_log', 'patients', 'exam_types', 'model_has_permissions', 'model_has_roles', 'sessions', 'users', 'rooms', 'units', 'domains'] as $table) {
                DB::table($table)->where('tenant_id', $clinic['tenant']->id)->delete();
            }
            DB::table('tenants')->where('id', $clinic['tenant']->id)->delete();
        });
        $context->set(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
})->with(['order', 'receivable', 'settle', 'cancel', 'appointment', 'appointment-cancel', 'appointment-confirm', 'check-in', 'visit-cancel', 'execution-prepare', 'execution-cancel', 'execution-room', 'execution-start', 'start-cancel', 'start-revoke', 'health-collect', 'execution-complete', 'complete-revoke']);
