<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class Cnpj
{
    public string $value;

    public function __construct(string $value)
    {
        $value = strtoupper($value);
        if (! preg_match('~\A(?:[A-Z0-9]{12}[0-9]{2}|[A-Z0-9]{2}\.[A-Z0-9]{3}\.[A-Z0-9]{3}/[A-Z0-9]{4}-[0-9]{2})\z~', $value)) {
            throw new InvalidArgumentException('CNPJ inválido.');
        }
        $value = str_replace(['.', '/', '-'], '', $value);
        if (preg_match('/^(.)\1{13}$/', $value)) {
            throw new InvalidArgumentException('CNPJ inválido.');
        }
        foreach ([12, 13] as $length) {
            $sum = 0;
            for ($index = 0; $index < $length; $index++) {
                $weight = (($length - 1 - $index) % 8) + 2;
                $sum += (ord($value[$index]) - 48) * $weight;
            }
            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;
            if ($digit !== (int) $value[$length]) {
                throw new InvalidArgumentException('CNPJ inválido.');
            }
        }
        $this->value = $value;
    }
}
