<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\PatientIdentity;
use App\Http\Rules\ValidCpf;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SearchPatientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Patient::class);
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['name', 'cpf'])],
            'name' => ['exclude_unless:mode,name', 'required', 'string', 'max:180', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && mb_strlen(PatientIdentity::normalizeName($value)) < 3) {
                    $fail('Informe ao menos três caracteres do início do nome.');
                }
            }],
            'birth_date' => ['exclude_unless:mode,name', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cpf' => ['exclude_unless:mode,cpf', 'required', 'string', 'max:14', new ValidCpf],
            'tenant_id' => ['prohibited'], 'cpf_hash' => ['prohibited'],
        ];
    }
}
