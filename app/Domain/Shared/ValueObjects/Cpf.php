<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class Cpf
{
    public string $digits;

    public function __construct(#[\SensitiveParameter] string $value)
    {
        if (! preg_match('/\A(?:[0-9]{11}|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2})\z/', $value)) {
            throw new InvalidArgumentException('CPF inválido.');
        }
        $digits = str_replace(['.', '-'], '', $value);
        if (preg_match('/^(.)\1{10}$/', $digits)) {
            throw new InvalidArgumentException('CPF inválido.');
        }
        foreach ([9, 10] as $length) {
            $sum = 0;
            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $digits[$index] * ($length + 1 - $index);
            }
            $digit = (11 - $sum % 11) % 10;
            if ($sum % 11 < 2) {
                $digit = 0;
            }
            if ($digit !== (int) $digits[$length]) {
                throw new InvalidArgumentException('CPF inválido.');
            }
        }
        $this->digits = $digits;
    }
}
