<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Occupational\Models\OccupationalProgram;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterOccupationalProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [OccupationalProgram::class, $this->route('company')]);
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:40'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'source_reference' => ['required', 'string', 'max:190'],
            'context' => ['required', 'string', 'max:16000'],
            'tenant_id' => ['prohibited'], 'company_id' => ['prohibited'], 'company_unit_id' => ['prohibited'],
            'id' => ['prohibited'], 'responsible_professional_id' => ['prohibited'], 'context_encrypted' => ['prohibited'],
            'approved_by' => ['prohibited'], 'approved_at' => ['prohibited'], 'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ];
    }
}
