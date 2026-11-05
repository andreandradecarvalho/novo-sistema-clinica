<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Actions\CreateAppointment;
use App\Domain\Scheduling\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;

final class CreateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('patient') instanceof Patient && $this->user()->can('create', [Appointment::class, $this->route('patient')]);
    }

    public function rules(): array
    {
        $rules = CreateAppointment::rules();
        foreach (['id', 'tenant_id', 'patient_id', 'public_id', 'company_id', 'company_unit_id', 'scheduled_at', 'specialty', 'status', 'is_priority', 'is_special', 'has_gynecology', 'snapshot_company_name', 'confirmed_at', 'checked_in_at', 'canceled_at', 'notes', 'legacy_id', 'created_by', 'created_at', 'updated_at', 'deleted_at', 'active_flag', 'unit_scope', 'slot_key', 'context_encrypted', 'creation_key_hash', 'creation_request_hash', 'exam_order_id'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
