<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Orders\Services\AuthorizedOrderAmount;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CreateClinicalReceivable
{
    public function execute(Patient $patient, ExamOrder $order, string $dueDate): ClinicalReceivable
    {
        Validator::make(['due_date' => $dueDate], ['due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31']])->validate();

        return DB::transaction(function () use ($patient, $order, $dueDate): ClinicalReceivable {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            $order = $patient->orders()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [ClinicalReceivable::class, $order]);
            $amount = app(AuthorizedOrderAmount::class)->calculate($order);
            $key = 'full-order:v1:'.$order->id;
            $existing = $order->receivables()->orderBy('id')->lockForUpdate()->get();
            if ($existing->isNotEmpty()) {
                $receivable = $existing->first();
                if ($existing->count() !== 1 || $receivable->idempotency_key !== $key || $receivable->due_date->format('Y-m-d') !== $dueDate
                    || $receivable->transition_encrypted !== null || $receivable->status !== 'open' || $receivable->settled_at !== null || $receivable->external_ref !== null || $receivable->payer_type !== $order->payer_type
                    || $receivable->company_id !== $order->company_id || $receivable->amount !== $amount) {
                    $this->refuse();
                }
                app(RegistryAudit::class)->record('receivables', 'clinical_receivable_replayed', $receivable, ClinicalReceivable::class);

                return $receivable;
            }
            $receivable = new ClinicalReceivable;
            $receivable->order()->associate($order);
            $receivable->forceFill(['company_id' => $order->company_id, 'payer_type' => $order->payer_type,
                'amount' => $amount,
                'due_date' => $dueDate, 'status' => 'open', 'settled_at' => null, 'external_ref' => null, 'idempotency_key' => $key])->save();
            app(RegistryAudit::class)->record('receivables', 'clinical_receivable_created', $receivable, ClinicalReceivable::class);

            return $receivable;
        }, 3);
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['receivable' => 'O pedido ou recebível não permite este registro integral. Reabra a consulta e solicite revisão autorizada.']);
    }
}
