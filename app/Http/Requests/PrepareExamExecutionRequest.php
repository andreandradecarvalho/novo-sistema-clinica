<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Executions\Actions\PrepareExamExecution;
use App\Domain\Executions\Models\ExamExecution;
use Illuminate\Foundation\Http\FormRequest;

final class PrepareExamExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('prepare', [ExamExecution::class, $this->route('visit')]);
    }

    public function rules(): array
    {
        $rules = PrepareExamExecution::rules();
        foreach (['id', 'tenant_id', 'patient_id', 'visit_id',  'exam_type_id', 'room_id', 'performed_by', 'attempt_no', 'status', 'started_at', 'finished_at', 'created_by', 'source', 'reason_encrypted', 'context_encrypted', 'creation_key_hash', 'creation_request_hash', 'created_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
