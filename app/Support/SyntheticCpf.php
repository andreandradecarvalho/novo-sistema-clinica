<?php

declare(strict_types=1);

namespace App\Support;

final class SyntheticCpf
{
    public static function generate(): string
    {
        $digits = (string) random_int(100000000, 999999999);
        foreach ([9, 10] as $length) {
            $sum = 0;
            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $digits[$index] * ($length + 1 - $index);
            }
            $remainder = $sum % 11;
            $digits .= $remainder < 2 ? '0' : (string) (11 - $remainder);
        }

        return $digits;
    }
}
