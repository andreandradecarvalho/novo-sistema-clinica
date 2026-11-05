<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Scheduling\Actions\CancelAppointment;
use Illuminate\Foundation\Http\FormRequest;

final class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('appointment'));
    }

    public function rules(): array
    {
        $rules = CancelAppointment::rules();
        foreach (['id', 'tenant_id', 'patient_id', 'public_id', 'unit_id', 'scheduled_date', 'scheduled_at', 'status', 'confirmed_at', 'checked_in_at', 'canceled_at', 'created_by', 'context_encrypted', 'creation_key_hash', 'creation_request_hash', 'deleted_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
