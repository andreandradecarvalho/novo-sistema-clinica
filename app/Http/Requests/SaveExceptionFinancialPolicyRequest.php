<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Orders\Actions\SaveExceptionFinancialPolicy;
use App\Domain\Tenancy\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

final class SaveExceptionFinancialPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageExceptionPolicy', Setting::class);
    }

    public function rules(): array
    {
        $rules = SaveExceptionFinancialPolicy::rules();
        foreach (['id', 'tenant_id', 'key', 'value', 'revision', 'history', 'actor_id', 'occurred_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
