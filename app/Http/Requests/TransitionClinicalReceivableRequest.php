<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Orders\Actions\TransitionClinicalReceivable;
use Illuminate\Foundation\Http\FormRequest;

final class TransitionClinicalReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('*.settle') ? 'settle' : 'cancel', $this->route('receivable'));
    }

    public function rules(): array
    {
        $rules = TransitionClinicalReceivable::rules();
        foreach (['id', 'tenant_id', 'exam_order_id', 'company_id', 'payer_type', 'amount', 'due_date', 'status', 'settled_at', 'external_ref', 'transition_encrypted', 'created_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
