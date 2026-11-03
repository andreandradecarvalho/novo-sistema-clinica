<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Companies\Models\Company;
use App\Domain\Shared\ValueObjects\Cnpj;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class SaveCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company ? $this->user()->can('update', $company) : $this->user()->can('create', Company::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'cnpj' => ['nullable', 'string', 'max:18', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    if (! is_string($value)) {
                        throw new InvalidArgumentException;
                    }
                    new Cnpj($value);
                } catch (InvalidArgumentException) {
                    $fail('CNPJ inválido.');
                }
            }],
            'is_active' => ['required', 'boolean'],
            'tenant_id' => ['prohibited'], 'parent_id' => ['prohibited'], 'public_id' => ['prohibited'],
            'slug' => ['prohibited'], 'type' => ['prohibited'], 'storage_prefix' => ['prohibited'],
        ];
    }
}
