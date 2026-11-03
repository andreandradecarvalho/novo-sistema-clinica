<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Enums\BrazilianState;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveProfessionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $professional = $this->route('professional');

        return $professional instanceof Professional ? $this->user()->can('update', $professional) : $this->user()->can('create', Professional::class);
    }

    public function rules(): array
    {
        $id = app(TenantContext::class)->id();

        return [
            'user_id' => $this->route('professional') instanceof Professional ? ['prohibited'] : [
                'required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $id)->where('is_active', true)->whereNull('deleted_at'),
            ],
            'council' => ['nullable', 'required_with:council_uf,council_number', 'string', 'max:10', 'regex:/\A[A-Z][A-Z0-9-]*\z/'],
            'council_uf' => ['nullable', 'required_with:council,council_number', 'string', Rule::enum(BrazilianState::class)],
            'council_number' => ['nullable', 'required_with:council,council_uf', 'string', 'max:20', 'regex:~\A[A-Z0-9][A-Z0-9./-]*\z~'],
            'specialty' => ['nullable', 'string', 'max:60'], 'tenant_id' => ['prohibited'],
            'is_active' => ['prohibited'], 'unit_id' => ['prohibited'], 'roles' => ['prohibited'], 'permissions' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['council', 'council_uf', 'council_number'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtoupper($this->input($field))]);
            }
        }
    }
}
