<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Orders\Models\ExamOrder;
use Illuminate\Foundation\Http\FormRequest;

final class CreateExamOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('company') !== null
            ? $this->user()->can('createForCompany', [ExamOrder::class, $this->route('patient'), $this->route('company')])
            : $this->user()->can('create', [ExamOrder::class, $this->route('patient')]);
    }

    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'exam_ids' => ['required', 'array', 'list', 'min:1', 'max:20'], 'exam_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'creation_key_hash' => ['prohibited'], 'creation_request_hash' => ['prohibited'],
            'tenant_id' => ['prohibited'], 'patient_id' => ['prohibited'], 'id' => ['prohibited'], 'public_id' => ['prohibited'],
            'items' => ['prohibited'], 'status' => ['prohibited'], 'payer_type' => ['prohibited'], 'company_id' => ['prohibited'], 'company_unit_id' => ['prohibited'],
            'requested_by' => ['prohibited'], 'authorized_at' => ['prohibited'], 'pricing_snapshot' => ['prohibited'], 'context_encrypted' => ['prohibited'], 'unit_price' => ['prohibited'], 'discount_amount' => ['prohibited']];
    }
}
