<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Clinical\Actions\CollectHealthAnswer;
use App\Domain\Clinical\Models\HealthRecord;
use Illuminate\Foundation\Http\FormRequest;

final class CollectHealthAnswerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('answer'))) {
            $this->merge(['answer' => trim($this->input('answer'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', [HealthRecord::class, $this->route('visit')]);
    }

    public function rules(): array
    {
        $rules = CollectHealthAnswer::rules();
        foreach (['id', 'tenant_id', 'patient_id', 'visit_id', 'appointment_id', 'public_id', 'filled_at', 'filled_by', 'legacy_id', 'deleted_at', 'source', 'context_encrypted', 'creation_key_hash', 'answer_encrypted', 'question_label_snapshot', 'question_type_snapshot', 'question_options_snapshot', 'version', 'created_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
