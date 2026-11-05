<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CancelAppointment
{
    public function execute(Patient $patient, Appointment $appointment, string $reason, string $key, string $expectedStatus): Appointment
    {
        $reason = trim($reason);
        Validator::make(['reason' => $reason, 'idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $appointment, $reason, $key, $expectedStatus): Appointment {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            $appointment = $patient->appointments()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('cancel', $appointment);
            $context = $appointment->contextSnapshot();
            $keyHash = hash('sha256', 'appointment-cancel:v1|'.$actor->id.'|'.strtolower($key));
            $requestHash = hash('sha256', $reason);
            if (isset($context['cancellation'])) {
                if (($context['cancellation']['from_status'] ?? 'pre_agendado') !== $expectedStatus || ! hash_equals($context['cancellation']['key_hash'], $keyHash) || ! hash_equals($context['cancellation']['request_hash'], $requestHash)) {
                    throw ValidationException::withMessages(['appointment' => 'O cancelamento já foi registrado com outra solicitação.']);
                }
                app(RegistryAudit::class)->record('appointments', 'appointment_cancellation_replayed', $appointment, Appointment::class);

                return $appointment;
            }
            if ($appointment->status !== $expectedStatus || ! in_array($appointment->status, ['pre_agendado', 'confirmado'], true)
                || Visit::query()->where('appointment_id', $appointment->id)->lockForUpdate()->first(['id']) !== null) {
                throw ValidationException::withMessages(['appointment' => 'A reserva mudou ou já possui atendimento. Reabra a consulta.']);
            }
            $now = now('UTC')->startOfSecond();
            $context['cancellation'] = ['from_status' => $expectedStatus, 'actor_id' => (int) $actor->id, 'reason' => $reason, 'occurred_at' => $now->toIso8601String(), 'key_hash' => $keyHash, 'request_hash' => $requestHash];
            // Reviewed bulk write: exact tenant/patient/reservation/previous state, no scheduling or ownership changes.
            $changed = DB::table('appointments')->where('tenant_id', $appointment->tenant_id)->where('patient_id', $patient->id)->where('id', $appointment->id)
                ->where('status', $expectedStatus)->whereNull('canceled_at')->whereNull('deleted_at')
                ->update(['status' => 'cancelado', 'canceled_at' => $now, 'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                throw ValidationException::withMessages(['appointment' => 'A reserva mudou. Reabra a consulta.']);
            }
            $appointment->refresh();
            app(RegistryAudit::class)->record('appointments', 'appointment_canceled', $appointment, Appointment::class);

            return $appointment;
        }, 3);
    }

    public static function rules(): array
    {
        return ['expected_status' => ['required', 'in:pre_agendado,confirmado'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']];
    }
}
