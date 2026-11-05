<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Occupational\Models\OccupationalProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class SaveOccupationalProtocolRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', [OccupationalProtocol::class, $this->route('program')]);
    }

    public function rules(): array
    {
        return [
            'code' => $this->route('protocol') === null ? ['required', 'string', 'max:60', 'regex:/^[A-Z0-9_-]+$/'] : ['prohibited'],
            'valid_from' => ['required', 'date_format:Y-m-d'], 'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'source_reference' => ['required', 'string', 'max:190'], 'job_title' => ['nullable', 'string', 'max:120'],
            'risk_context' => ['required', 'string', 'max:4000'], 'exam_codes' => ['required', 'string', 'max:4000'],
            'tenant_id' => ['prohibited'], 'program_id' => ['prohibited'], 'id' => ['prohibited'], 'version' => ['prohibited'],
            'rules' => ['prohibited'], 'approved_by' => ['prohibited'], 'approved_at' => ['prohibited'],
            'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ];
    }
}
