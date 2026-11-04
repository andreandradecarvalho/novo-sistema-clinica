<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Catalog\Models\ExamPreparation;
use Illuminate\Foundation\Http\FormRequest;

final class SaveExamPreparationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $preparation = $this->route('preparation');

        return $preparation instanceof ExamPreparation
            ? $this->user()->can('update', $preparation)
            : $this->user()->can('create', [ExamPreparation::class, $this->route('exam_type')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['required', 'string', 'max:16000'],
            'tenant_id' => ['prohibited'], 'exam_type_id' => ['prohibited'], 'id' => ['prohibited'],
        ];
    }
}
