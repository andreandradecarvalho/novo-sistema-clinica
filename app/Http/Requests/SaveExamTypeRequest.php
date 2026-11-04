<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Catalog\Models\ExamType;
use Illuminate\Foundation\Http\FormRequest;

final class SaveExamTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => ExamType::normalizeCode($this->input('code'))]);
        }
    }

    public function authorize(): bool
    {
        $examType = $this->route('exam_type');

        return $examType instanceof ExamType ? $this->user()->can('update', $examType) : $this->user()->can('create', ExamType::class);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'requires_fasting' => ['required', 'boolean'],
            'default_duration_min' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'ordem' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
            'tenant_id' => ['prohibited'], 'id' => ['prohibited'], 'public_id' => ['prohibited'], 'scope' => ['prohibited'],
        ];
    }
}
