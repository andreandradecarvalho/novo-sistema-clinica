<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ConfirmAppointment
{
    public function execute(Patient $patient, Appointment $appointment, string $key, string $expectedStatus): Appointment
    {
        Validator::make(['idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $appointment, $key): Appointment {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            $appointment = $patient->appointments()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('confirm', $appointment);
            $context = $appointment->contextSnapshot();
            $hash = hash('sha256', 'appointment-confirm:v1|'.$actor->id.'|'.strtolower($key));
            if (isset($context['confirmation'])) {
                if (! hash_equals($context['confirmation']['key_hash'], $hash)) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('appointments', 'appointment_confirmation_replayed', $appointment, Appointment::class);

                return $appointment;
            }
            if ($appointment->status !== 'pre_agendado') {
                $this->refuse();
            }
            $now = now('UTC')->startOfSecond();
            $context['confirmation'] = ['source' => 'staff', 'actor_id' => (int) $actor->id, 'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash];
            // Exact tenant/patient/reservation/state; only transition evidence and dates change.
            $changed = DB::table('appointments')->where('tenant_id', $appointment->tenant_id)->where('patient_id', $patient->id)->where('id', $appointment->id)
                ->where('status', 'pre_agendado')->whereNull('confirmed_at')->whereNull('checked_in_at')->whereNull('canceled_at')->whereNull('deleted_at')
                ->update(['status' => 'confirmado', 'confirmed_at' => $now, 'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                $this->refuse();
            }
            $appointment->refresh();
            app(RegistryAudit::class)->record('appointments', 'appointment_confirmed', $appointment, Appointment::class);

            return $appointment;
        }, 3);
    }

    public static function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'expected_status' => ['required', 'in:pre_agendado']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['appointment' => 'A reserva mudou ou não permite esta confirmação. Reabra a consulta.']);
    }
}
