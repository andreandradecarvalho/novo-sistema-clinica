<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionExamOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('patients.orders.authorize') ? 'authorize' : 'cancel', $this->route('order'));
    }

    public function rules(): array
    {
        return ['expected_status' => ['required', 'in:draft,authorized'],
            'reason' => $this->routeIs('patients.orders.cancel') ? ['required', 'string', 'max:1000'] : ['prohibited'],
            'tenant_id' => ['prohibited'], 'patient_id' => ['prohibited'], 'id' => ['prohibited'], 'status' => ['prohibited'],
            'items' => ['prohibited'], 'authorized_at' => ['prohibited'], 'context_encrypted' => ['prohibited'], 'pricing_snapshot' => ['prohibited'],
            'unit_price' => ['prohibited'], 'discount_amount' => ['prohibited'], 'actor_id' => ['prohibited']];
    }
}
