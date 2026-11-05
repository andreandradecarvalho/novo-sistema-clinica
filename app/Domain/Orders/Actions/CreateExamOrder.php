<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Catalog\Models\ExamType;
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
    public function execute(Patient $patient, array $examIds): ExamOrder
    {
        Validator::make(['exam_ids' => $examIds], ['exam_ids' => ['required', 'array', 'list', 'min:1', 'max:20'], 'exam_ids.*' => ['required', 'integer', 'min:1', 'distinct']])->validate();

        return DB::transaction(function () use ($patient, $examIds): ExamOrder {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('create', [ExamOrder::class, $patient]);
            $exams = ExamType::query()->select(['id', 'tenant_id', 'code', 'name', 'price'])->whereIn('id', $examIds)
                ->where('scope', 'exame')->where('is_active', true)->whereNotNull('price')->where('price', '>=', 0)->orderBy('id')->lockForUpdate()->get();
            if ($exams->count() !== count($examIds)) {
                throw ValidationException::withMessages(['exam_ids' => 'Selecione somente exames ativos com preço definido no catálogo desta clínica.']);
            }
            $order = new ExamOrder;
            $order->patient()->associate($patient);
            $order->forceFill(['payer_type' => 'patient', 'status' => 'draft', 'pricing_snapshot' => ['schema_version' => 1, 'source' => 'catalog', 'payer_type' => 'patient'],
                'context_encrypted' => ['schema_version' => 1, 'patient_id' => (int) $patient->id, 'payer_name' => $patient->name], 'authorized_at' => null])->save();
            foreach ($exams as $exam) {
                $item = new ExamOrderItem;
                $item->order()->associate($order);
                $item->forceFill(['exam_type_id' => $exam->id, 'quantity' => 1, 'unit_price' => $exam->price, 'discount_amount' => '0.00', 'status' => 'draft', 'description_snapshot' => $exam->name])->save();
            }
            app(RegistryAudit::class)->record('orders', 'exam_order_created', $order, ExamOrder::class);

            return $order;
        });
    }
}
