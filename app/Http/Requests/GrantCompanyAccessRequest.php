<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Companies\Enums\CompanyAccessScope;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GrantCompanyAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company && $this->user()->can('create', [CompanyAccess::class, $company]);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'], 'scope' => ['required', Rule::enum(CompanyAccessScope::class)],
            'valid_from' => ['required', 'date_format:Y-m-d\TH:i'],
            'valid_until' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:valid_from'],
            'tenant_id' => ['prohibited'], 'company_id' => ['prohibited'], 'revoked_at' => ['prohibited'],
            'roles' => ['prohibited'], 'permissions' => ['prohibited'],
        ];
    }
}
