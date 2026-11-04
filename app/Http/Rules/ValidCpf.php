<?php

declare(strict_types=1);

namespace App\Http\Rules;

use App\Domain\Shared\ValueObjects\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class ValidCpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if (! is_string($value)) {
                throw new InvalidArgumentException;
            }
            new Cpf($value);
        } catch (InvalidArgumentException) {
            $fail('CPF inválido.');
        }
    }
}
