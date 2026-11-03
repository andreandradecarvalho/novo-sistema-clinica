<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyUnit;
use App\Http\Requests\Concerns\ValidatesLocation;
use Illuminate\Foundation\Http\FormRequest;

final class SaveCompanyUnitRequest extends FormRequest
{
    use ValidatesLocation;

    public function authorize(): bool
    {
        $company = $this->route('company');
        if (! $company instanceof Company) {
            return false;
        }
        $unit = $this->route('unit');

        return $unit instanceof CompanyUnit ? $this->user()->can('update', [$unit, $company]) : $this->user()->can('create', [CompanyUnit::class, $company]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            ...$this->locationRules(),
            'is_active' => ['required', 'boolean'],
            'tenant_id' => ['prohibited'], 'company_id' => ['prohibited'], 'id' => ['prohibited'],
        ];
    }
}
