<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

final class SaveEmploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');
        $employment = $this->route('employment');
        if (! $patient instanceof Patient) {
            return false;
        }

        return $employment instanceof EmploymentLink ? $this->user()->can('update', [$employment, $patient]) : $this->user()->can('create', [EmploymentLink::class, $patient]);
    }

    public function rules(): array
    {
        return [
            'company_unit_id' => ['nullable', 'integer'], 'registration_number' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:150'], 'department' => ['nullable', 'string', 'max:150'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'tenant_id' => ['prohibited'], 'patient_id' => ['prohibited'], 'company_id' => ['prohibited'],
            'ends_on' => ['prohibited'], 'termination_date' => ['prohibited'], 'source' => ['prohibited'], 'context_encrypted' => ['prohibited'],
        ];
    }
}
