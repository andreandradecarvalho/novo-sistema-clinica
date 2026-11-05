<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Scheduling\Actions\ConfirmAppointment;
use Illuminate\Foundation\Http\FormRequest;

final class ConfirmAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('confirm', $this->route('appointment'));
    }

    public function rules(): array
    {
        $rules = ConfirmAppointment::rules();
        foreach (['tenant_id', 'patient_id', 'unit_id', 'scheduled_date', 'scheduled_at', 'status', 'confirmed_at', 'checked_in_at', 'canceled_at', 'context_encrypted', 'reason', 'exam_order_id'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
