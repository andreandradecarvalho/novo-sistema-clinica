<?php

declare(strict_types=1);

namespace App\Domain\Visits\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CheckInAppointment
{
    public function execute(Patient $patient, Appointment $appointment, int $orderId, string $key, string $expectedStatus): Visit
    {
        Validator::make(['exam_order_id' => $orderId, 'idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $appointment, $orderId, $key): Visit {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            $appointment = $patient->appointments()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $order = $patient->orders()->whereKey($orderId)->lockForUpdate()->first();
            if ($order === null) {
                $this->refuse();
            }
            $unit = Unit::query()->withTrashed()->whereKey($appointment->unit_id)->lockForUpdate()->first();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('checkIn', [Visit::class, $appointment]);
            Gate::forUser($actor)->authorize('view', $order);
            $context = $appointment->contextSnapshot();
            $hash = hash('sha256', 'appointment-check-in:v1|'.$actor->id.'|'.strtolower($key));
            $existing = Visit::query()->where('appointment_id', $appointment->id)->lockForUpdate()->first();
            if ($existing !== null) {
                $history = $existing->attendanceSnapshot();
                if (! hash_equals($history['key_hash'], $hash) || (int) $existing->exam_order_id !== $orderId) {
                    $this->refuse();
                }
                Gate::forUser($actor)->authorize('view', $existing);
                app(RegistryAudit::class)->record('visits', 'visit_check_in_replayed', $existing, Visit::class);

                return $existing;
            }
            $orderContext = $order->contextSnapshot();
            if ($appointment->status !== 'confirmado' || isset($context['check_in']) || $unit === null || ! $unit->is_active || $unit->trashed()
                || $order->status !== 'authorized' || $order->authorized_at === null || ! isset($orderContext['authorization']) || isset($orderContext['cancellation'])) {
                $this->refuse();
            }
            $items = $order->items()->select(['id', 'tenant_id', 'exam_order_id', 'status'])->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty() || $items->contains(fn ($item) => $item->status !== 'authorized')) {
                $this->refuse();
            }
            $now = now('UTC')->startOfSecond();
            $visit = new Visit;
            $visit->patient()->associate($patient);
            $visit->appointment()->associate($appointment);
            $visit->order()->associate($order);
            $id = (string) Str::ulid();
            $evidence = ['schema_version' => 1, 'tenant_id' => (int) $appointment->tenant_id, 'public_id' => $id, 'patient_id' => (int) $patient->id,
                'appointment_id' => (int) $appointment->id, 'exam_order_id' => $orderId, 'unit_id' => (int) $appointment->unit_id,
                'actor_id' => (int) $actor->id, 'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash];
            $visit->forceFill(['public_id' => $id, 'unit_id' => $appointment->unit_id, 'status' => 'open', 'source' => 'appointment',
                'checked_in_at' => $now, 'created_by' => $actor->id, 'context_encrypted' => Crypt::encryptString(json_encode($evidence, JSON_THROW_ON_ERROR))])->save();
            $context['check_in'] = ['visit_public_id' => $id, 'exam_order_id' => $orderId, 'actor_id' => (int) $actor->id, 'occurred_at' => $now->toIso8601String(), 'key_hash' => $hash];
            // Same transaction as the visit and audit; exact tenant/patient/reservation/previous state.
            $changed = DB::table('appointments')->where('tenant_id', $appointment->tenant_id)->where('patient_id', $patient->id)->where('id', $appointment->id)
                ->where('status', 'confirmado')->whereNull('checked_in_at')->whereNull('canceled_at')->whereNull('deleted_at')
                ->update(['status' => 'checked_in', 'checked_in_at' => $now, 'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                $this->refuse();
            }
            $appointment->refresh();
            app(RegistryAudit::class)->record('appointments', 'appointment_checked_in', $appointment, Appointment::class);
            app(RegistryAudit::class)->record('visits', 'visit_opened', $visit, Visit::class);

            return $visit;
        }, 3);
    }

    public static function rules(): array
    {
        return ['exam_order_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid'], 'expected_status' => ['required', 'in:confirmado']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['visit' => 'A reserva ou pedido não permite este check-in. Reabra a consulta.']);
    }
}
