<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Orders\Actions\ReviewClinicalOrder;
use App\Domain\Orders\Models\ClinicalReceivable;
use Illuminate\Foundation\Http\FormRequest;

final class ReviewClinicalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', [ClinicalReceivable::class, $this->route('order')]);
    }

    public function rules(): array
    {
        $rules = ReviewClinicalOrder::rules();
        foreach (['id', 'tenant_id', 'patient_id', 'exam_order_id', 'receivable_id', 'amount', 'status', 'payer_type', 'company_id', 'due_date', 'settled_at', 'external_ref', 'context_encrypted', 'financial_reviews', 'created_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
