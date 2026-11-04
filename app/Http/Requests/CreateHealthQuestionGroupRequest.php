<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Catalog\Models\HealthQuestionGroup;
use Illuminate\Foundation\Http\FormRequest;

final class CreateHealthQuestionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', HealthQuestionGroup::class);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'ordem' => ['required', 'integer', 'between:0,65535'],
            'tenant_id' => ['prohibited'], 'id' => ['prohibited'],
        ];
    }
}
