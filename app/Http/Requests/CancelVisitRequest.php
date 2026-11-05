<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Visits\Actions\CancelVisit;
use Illuminate\Foundation\Http\FormRequest;

final class CancelVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('visit'));
    }

    public function rules(): array
    {
        $rules = CancelVisit::rules();
        foreach (['id', 'tenant_id', 'public_id', 'patient_id', 'appointment_id', 'exam_order_id', 'unit_id', 'status', 'checked_in_at', 'completed_at', 'created_by', 'source', 'context_encrypted', 'created_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
