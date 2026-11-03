<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

final class EndEmploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');
        $employment = $this->route('employment');

        return $patient instanceof Patient && $employment instanceof EmploymentLink && $this->user()->can('update', [$employment, $patient]);
    }

    public function rules(): array
    {
        return ['termination_date' => ['required', 'date_format:Y-m-d']];
    }
}
