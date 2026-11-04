<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublishHealthQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('group'));
    }

    public function rules(): array
    {
        $revision = $this->route('question') !== null;

        return [
            'code' => $revision ? ['prohibited'] : ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'ordem' => $revision ? ['prohibited'] : ['required', 'integer', 'between:0,65535'],
            'tenant_id' => ['prohibited'], 'id' => ['prohibited'], 'group_id' => ['prohibited'],
            'version' => ['prohibited'], 'type' => ['prohibited'], 'options' => ['prohibited'],
            'applies_to' => ['prohibited'], 'is_active' => ['prohibited'],
        ];
    }
}
