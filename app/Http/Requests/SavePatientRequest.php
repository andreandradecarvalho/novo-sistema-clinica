<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Patients\Models\Patient;
use App\Http\Rules\ValidCpf;
use Illuminate\Foundation\Http\FormRequest;

final class SavePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof Patient ? $this->user()->can('update', $patient) : $this->user()->can('create', Patient::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'email' => ['nullable', 'email', 'max:180'],
            'mobile_phone' => ['nullable', 'string', 'max:20', 'regex:/\A[0-9+() -]+\z/'],
            'cpf' => ['nullable', 'string', 'max:14', new ValidCpf],
            'tenant_id' => ['prohibited'], 'user_id' => ['prohibited'], 'company_id' => ['prohibited'],
            'company_unit_id' => ['prohibited'], 'possible_duplicate_of_id' => ['prohibited'],
            'cpf_hash' => ['prohibited'], 'cpf_encrypted' => ['prohibited'],
        ];
    }
}
