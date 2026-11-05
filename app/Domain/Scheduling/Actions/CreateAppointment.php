<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateAppointment
{
    public function execute(Patient $patient, int $unitId, string $date, string $idempotencyKey): Appointment
    {
        Validator::make(['unit_id' => $unitId, 'scheduled_date' => $date, 'idempotency_key' => $idempotencyKey], self::rules())->validate();

        return DB::transaction(function () use ($patient, $unitId, $date, $idempotencyKey): Appointment {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            $unit = Unit::query()->whereKey($unitId)->lockForUpdate()->first();
            if ($unit === null) {
                throw ValidationException::withMessages(['unit_id' => 'Selecione uma unidade disponível desta clínica.']);
            }
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('bookAt', [Appointment::class, $patient, $unit]);
            $keyHash = hash('sha256', 'appointment-create:v1|'.$actor->id.'|'.strtolower($idempotencyKey));
            $requestHash = hash('sha256', json_encode(['patient_id' => (int) $patient->id, 'unit_id' => $unitId, 'scheduled_date' => $date], JSON_THROW_ON_ERROR));
            $existing = Appointment::query()->where('creation_key_hash', $keyHash)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->creation_request_hash, $requestHash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Esta solicitação já foi utilizada com outro conteúdo. Abra um novo formulário.']);
                }
                Gate::forUser($actor)->authorize('view', $existing);
                $existing->contextSnapshot();
                app(RegistryAudit::class)->record('appointments', 'appointment_replayed', $existing, Appointment::class);

                return $existing;
            }
            if (! $unit->is_active) {
                throw ValidationException::withMessages(['unit_id' => 'A unidade precisa estar ativa para uma nova reserva.']);
            }
            if ($patient->appointments()->where('unit_id', $unitId)->where('scheduled_date', $date)->where('active_flag', 1)->lockForUpdate()->first(['id']) !== null) {
                throw ValidationException::withMessages(['scheduled_date' => 'Já existe reserva ativa deste paciente nesta unidade e data.']);
            }
            $appointment = new Appointment;
            $appointment->patient()->associate($patient);
            $publicId = (string) Str::ulid();
            $context = ['schema_version' => 1, 'tenant_id' => app(TenantContext::class)->id(), 'public_id' => $publicId,
                'patient_id' => (int) $patient->id, 'unit_id' => $unitId, 'scheduled_date' => $date, 'unit_name' => $unit->name, 'created_by' => (int) $actor->id];
            $appointment->forceFill(['public_id' => $publicId, 'unit_id' => $unitId, 'scheduled_date' => $date, 'scheduled_at' => null,
                'status' => 'pre_agendado', 'created_by' => $actor->id, 'creation_key_hash' => $keyHash, 'creation_request_hash' => $requestHash,
                'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR))])->save();
            app(RegistryAudit::class)->record('appointments', 'appointment_created', $appointment, Appointment::class);

            return $appointment;
        }, 3);
    }

    public static function rules(): array
    {
        return ['unit_id' => ['required', 'integer', 'min:1'], 'scheduled_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'], 'idempotency_key' => ['required', 'uuid']];
    }
}
