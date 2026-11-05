<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Orders\Models\ExamOrderItem;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CreateExamOrder
{
    public function execute(Patient $patient, array $examIds, string $idempotencyKey, ?Company $payer = null): ExamOrder
    {
        Validator::make(['exam_ids' => $examIds, 'idempotency_key' => $idempotencyKey], [
            'exam_ids' => ['required', 'array', 'list', 'min:1', 'max:20'], 'exam_ids.*' => ['required', 'integer', 'min:1', 'distinct'], 'idempotency_key' => ['required', 'uuid'],
        ])->validate();
        $examIds = array_map(intval(...), $examIds);
        sort($examIds, SORT_NUMERIC);
        $requestHash = hash('sha256', json_encode(['version' => 1, 'patient_id' => (int) $patient->id, 'company_id' => $payer === null ? null : (int) $payer->id, 'exam_ids' => $examIds], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($patient, $examIds, $payer, $idempotencyKey, $requestHash): ExamOrder {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            if ($payer !== null) {
                $payer = Company::query()->whereKey($payer->getKey())->lockForUpdate()->firstOrFail();
            }
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            if ($payer !== null) {
                Gate::forUser($actor)->authorize('createForCompany', [ExamOrder::class, $patient, $payer]);
                if (! $payer->is_active) {
                    throw ValidationException::withMessages(['payer' => 'A empresa pagadora precisa estar ativa.']);
                }
            } else {
                Gate::forUser($actor)->authorize('create', [ExamOrder::class, $patient]);
            }
            $keyHash = hash('sha256', 'exam-order-create:v1|'.$actor->id.'|'.strtolower($idempotencyKey));
            $existing = ExamOrder::query()->where('creation_key_hash', $keyHash)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->creation_request_hash, $requestHash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Esta solicitação já foi utilizada com outro conteúdo. Abra um novo formulário.']);
                }
                Gate::forUser($actor)->authorize('view', $existing);
                app(RegistryAudit::class)->record('orders', 'exam_order_replayed', $existing, ExamOrder::class);

                return $existing;
            }
            $exams = ExamType::query()->select(['id', 'tenant_id', 'code', 'name', 'price'])->whereIn('id', $examIds)
                ->where('scope', 'exame')->where('is_active', true)->whereNotNull('price')->where('price', '>=', 0)->orderBy('id')->lockForUpdate()->get();
            if ($exams->count() !== count($examIds)) {
                throw ValidationException::withMessages(['exam_ids' => 'Selecione somente exames ativos com preço definido no catálogo desta clínica.']);
            }
            $order = new ExamOrder;
            $order->patient()->associate($patient);
            $payerType = $payer === null ? 'patient' : 'company';
            $context = ['schema_version' => 1, 'patient_id' => (int) $patient->id, 'payer_name' => $payer === null ? $patient->name : $payer->name];
            if ($payer !== null) {
                $context += ['payer_type' => 'company', 'company_id' => (int) $payer->id, 'payer_cnpj' => $payer->cnpj];
            }
            $order->forceFill(['payer_type' => $payerType, 'company_id' => $payer?->id, 'status' => 'draft',
                'creation_key_hash' => $keyHash, 'creation_request_hash' => $requestHash,
                'pricing_snapshot' => ['schema_version' => 1, 'source' => 'catalog', 'payer_type' => $payerType],
                'context_encrypted' => $context, 'authorized_at' => null])->save();
            foreach ($exams as $exam) {
                $item = new ExamOrderItem;
                $item->order()->associate($order);
                $item->forceFill(['exam_type_id' => $exam->id, 'quantity' => 1, 'unit_price' => $exam->price, 'discount_amount' => '0.00', 'status' => 'draft', 'description_snapshot' => $exam->name])->save();
            }
            app(RegistryAudit::class)->record('orders', 'exam_order_created', $order, ExamOrder::class);

            return $order;
        }, 3);
    }
}
