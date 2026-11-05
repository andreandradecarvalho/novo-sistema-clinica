<?php

declare(strict_types=1);

use App\Domain\Clinical\Actions\CollectHealthAnswer;
use App\Domain\Executions\Actions\AssignExecutionRoom;
use App\Domain\Executions\Actions\CancelPendingExecution;
use App\Domain\Executions\Actions\CompleteExamExecution;
use App\Domain\Executions\Actions\ManageProfessionalExamAuthorization;
use App\Domain\Executions\Actions\PrepareExamExecution;
use App\Domain\Executions\Actions\StartExamExecution;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Orders\Actions\CreateClinicalReceivable;
use App\Domain\Orders\Actions\CreateExamOrder;
use App\Domain\Orders\Actions\TransitionClinicalReceivable;
use App\Domain\Orders\Enums\ReceivableTransition;
use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Scheduling\Actions\CancelAppointment;
use App\Domain\Scheduling\Actions\ConfirmAppointment;
use App\Domain\Scheduling\Actions\CreateAppointment;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Actions\CancelVisit;
use App\Domain\Visits\Actions\CheckInAppointment;
use App\Domain\Visits\Models\Visit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mariadb' || config('database.connections.mariadb.host') !== 'db'
    || config('database.connections.mariadb.url') || DB::connection()->getDatabaseName() !== 'novo_sistema_clinica_testing') {
    throw new RuntimeException('Refused concurrent probe outside local testing.');
}
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$tenant = Tenant::query()->findOrFail($input['tenant_id']);
app(TenantContext::class)->run($tenant, function () use ($input): void {
    auth()->setUser(User::query()->findOrFail($input['actor_id']));
    $patient = Patient::query()->findOrFail($input['patient_id']);
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    try {
        $result = match ($input['operation']) {
            'order' => app(CreateExamOrder::class)->execute($patient, [$input['exam_id']], $input['key'])->public_id,
            'receivable' => app(CreateClinicalReceivable::class)->execute($patient, ExamOrder::query()->findOrFail($input['order_id']), '2026-12-10')->id,
            'settle', 'cancel' => app(TransitionClinicalReceivable::class)->execute($patient, ExamOrder::query()->findOrFail($input['order_id']), ClinicalReceivable::query()->findOrFail($input['receipt_id']), ReceivableTransition::from($input['operation']), 'Motivo sintético concorrente', $input['key'], 'open')->id,
            'appointment' => app(CreateAppointment::class)->execute($patient, (int) $input['unit_id'], '2026-12-10', $input['key'])->public_id,
            'appointment-cancel' => app(CancelAppointment::class)->execute($patient, Appointment::query()->findOrFail($input['appointment_id']), 'Motivo sintético concorrente', $input['key'], 'pre_agendado')->public_id,
            'appointment-confirm' => app(ConfirmAppointment::class)->execute($patient, Appointment::query()->findOrFail($input['appointment_id']), $input['key'], 'pre_agendado')->public_id,
            'check-in' => app(CheckInAppointment::class)->execute($patient, Appointment::query()->findOrFail($input['appointment_id']), (int) $input['order_id'], $input['key'], 'confirmado')->public_id,
            'visit-cancel' => app(CancelVisit::class)->execute($patient, Visit::query()->findOrFail($input['visit_id']), 'Motivo sintético concorrente', $input['key'], 'open')->public_id,
            'execution-prepare' => app(PrepareExamExecution::class)->execute($patient, Visit::query()->findOrFail($input['visit_id']), (int) $input['item_id'], $input['key'], 'open')->id,
            'execution-cancel' => app(CancelPendingExecution::class)->execute($patient, Visit::query()->findOrFail($input['visit_id']), ExamExecution::query()->findOrFail($input['execution_id']), 'Motivo sintético concorrente', $input['key'], 'pending')->id,
            'execution-room' => app(AssignExecutionRoom::class)->execute($patient, Visit::query()->findOrFail($input['visit_id']), ExamExecution::query()->findOrFail($input['execution_id']), (int) $input['room_id'], $input['key'], 'pending')->id,
            'execution-complete' => app(CompleteExamExecution::class)->execute($patient, Visit::query()->findOrFail($input['visit_id']), ExamExecution::query()->findOrFail($input['execution_id']), $input['key'], 'in_progress')->id,
            'execution-start' => app(StartExamExecution::class)->execute($patient, Visit::query()->findOrFail($input['visit_id']), ExamExecution::query()->findOrFail($input['execution_id']), $input['key'], 'pending')->id,
            'grant-revoke' => app(ManageProfessionalExamAuthorization::class)->revoke(Professional::query()->findOrFail($input['professional_id']), ProfessionalExamAuthorization::query()->findOrFail($input['grant_id']), 'Revogação sintética concorrente')->id,
            'health-collect' => app(CollectHealthAnswer::class)->execute($patient, Visit::query()->findOrFail($input['visit_id']), (int) $input['question_id'], 'Resposta sintética concorrente', $input['key'])->public_id,
            default => throw new RuntimeException('Unsupported probe operation.'),
        };
    } catch (AuthorizationException|ValidationException $exception) {
        if (! ($input['allow_denial'] ?? false)) {
            throw $exception;
        }
        fwrite(STDOUT, "DENIED\n");

        return;
    }
    fwrite(STDOUT, 'RESULT:'.$result."\n");
});
