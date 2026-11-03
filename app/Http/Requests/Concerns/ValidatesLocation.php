<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Shared\Enums\BrazilianState;
use Illuminate\Validation\Rule;

trait ValidatesLocation
{
    protected function locationRules(): array
    {
        return [
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', Rule::enum(BrazilianState::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('state'))) {
            $this->merge(['state' => strtoupper($this->input('state'))]);
        }
    }
}
