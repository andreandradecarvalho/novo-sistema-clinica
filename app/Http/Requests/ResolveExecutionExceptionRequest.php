<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Executions\Actions\ResolveExecutionException;
use Illuminate\Foundation\Http\FormRequest;

final class ResolveExecutionExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resolveException', $this->route('execution'));
    }

    public function rules(): array
    {
        $rules = ResolveExecutionException::rules();
        foreach (['id', 'tenant_id', 'patient_id', 'visit_id', 'exam_order_item_id', 'exam_type_id', 'unit_id', 'room_id', 'performed_by', 'professional_id', 'authorization_id', 'attempt_no', 'status', 'started_at', 'finished_at', 'created_by', 'source', 'reason_encrypted', 'context_encrypted', 'creation_key_hash', 'creation_request_hash', 'created_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
