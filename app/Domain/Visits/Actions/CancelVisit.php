<?php

declare(strict_types=1);

namespace App\Domain\Visits\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CancelVisit
{
    public function execute(Patient $patient, Visit $visit, string $reason, string $key, string $expectedStatus): Visit
    {
        $reason = trim($reason);
        Validator::make(['reason' => $reason, 'idempotency_key' => $key, 'expected_status' => $expectedStatus], self::rules())->validate();

        return DB::transaction(function () use ($patient, $visit, $reason, $key): Visit {
            // Serialize with check-in and order/financial transitions through the same patient lock.
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            $visit = $patient->visits()->whereKey($visit->getKey())->firstOrFail();
            $appointment = $patient->appointments()->whereKey($visit->appointment_id)->lockForUpdate()->firstOrFail();
            $order = $patient->orders()->whereKey($visit->exam_order_id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $visit = $patient->visits()->whereKey($visit->getKey())->lockForUpdate()->firstOrFail();
            $visit->setRelation('appointment', $appointment)->setRelation('order', $order);
            Gate::forUser($actor)->authorize('cancel', $visit);
            $context = $visit->attendanceSnapshot();
            $keyHash = hash('sha256', 'visit-cancel:v1|'.$actor->id.'|'.strtolower($key));
            $requestHash = hash('sha256', $reason);
            foreach ($visit->executions()->orderBy('id')->lockForUpdate()->get() as $execution) {
                $execution->setRelation('visit', $visit)->preparationSnapshot();
                if ($execution->status !== 'canceled') {
                    $this->refuse();
                }
            }
            if (isset($context['cancellation'])) {
                if (! hash_equals($context['cancellation']['key_hash'], $keyHash) || ! hash_equals($context['cancellation']['request_hash'], $requestHash)) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('visits', 'visit_cancellation_replayed', $visit, Visit::class);

                return $visit;
            }
            $orderContext = $order->contextSnapshot();
            $items = $order->items()->select(['id', 'tenant_id', 'exam_order_id', 'status'])->orderBy('id')->lockForUpdate()->get();
            // Only coherent, canceled-before-start attempts can coexist with this administrative visit cancellation.
            if ($visit->status !== 'open' || $order->status !== 'authorized' || $order->authorized_at === null
                || ! isset($orderContext['authorization']) || isset($orderContext['cancellation'])
                || $items->isEmpty() || $items->contains(fn ($item) => $item->status !== 'authorized')) {
                $this->refuse();
            }
            $now = now('UTC')->startOfSecond();
            if ($now->lessThan($visit->checked_in_at)) {
                $this->refuse();
            }
            $context['cancellation'] = ['from_status' => 'open', 'actor_id' => (int) $actor->id, 'reason' => $reason,
                'occurred_at' => $now->toIso8601String(), 'key_hash' => $keyHash, 'request_hash' => $requestHash];
            // Reviewed exact tenant/patient/visit/previous-state write; attendance, ownership and finance remain historical.
            $changed = DB::table('visits')->where('tenant_id', $visit->tenant_id)->where('patient_id', $patient->id)->where('id', $visit->id)
                ->where('status', 'open')->whereNull('completed_at')
                ->update(['status' => 'canceled', 'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            if ($changed !== 1) {
                $this->refuse();
            }
            $visit->refresh();
            app(RegistryAudit::class)->record('visits', 'visit_canceled', $visit, Visit::class);

            return $visit;
        }, 3);
    }

    public static function rules(): array
    {
        return ['expected_status' => ['required', 'in:open'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['visit' => 'O atendimento mudou ou não permite este cancelamento. Reabra a consulta.']);
    }
}
