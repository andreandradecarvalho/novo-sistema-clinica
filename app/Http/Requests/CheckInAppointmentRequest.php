<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Visits\Actions\CheckInAppointment;
use App\Domain\Visits\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

final class CheckInAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('checkIn', [Visit::class, $this->route('appointment')]);
    }

    public function rules(): array
    {
        $rules = CheckInAppointment::rules();
        foreach (['id', 'public_id', 'tenant_id', 'patient_id', 'appointment_id', 'unit_id', 'status', 'checked_in_at', 'completed_at', 'created_by', 'source', 'context_encrypted', 'confirmed_at', 'canceled_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
