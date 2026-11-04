<?php

declare(strict_types=1);

namespace App\Domain\Patients\Enums;

enum ConsentDecision: string
{
    case Granted = 'granted';
    case Refused = 'refused';
    case Withdrawn = 'withdrawn';
    case Acknowledged = 'acknowledged';

    public function label(): string
    {
        return match ($this) {
            self::Granted => 'Consentimento concedido',
            self::Refused => 'Consentimento recusado',
            self::Withdrawn => 'Consentimento retirado',
            self::Acknowledged => 'Ciência de termos',
        };
    }
}
