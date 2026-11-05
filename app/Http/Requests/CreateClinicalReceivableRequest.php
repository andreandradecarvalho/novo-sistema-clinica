<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Orders\Models\ClinicalReceivable;
use Illuminate\Foundation\Http\FormRequest;

final class CreateClinicalReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [ClinicalReceivable::class, $this->route('order')]);
    }

    public function rules(): array
    {
        $rules = ['due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31']];
        foreach (['id', 'tenant_id', 'exam_order_id', 'company_id', 'payer_type', 'amount', 'status', 'settled_at', 'external_ref', 'idempotency_key', 'transition_encrypted', 'created_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
