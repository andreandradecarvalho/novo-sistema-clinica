<?php

declare(strict_types=1);

namespace App\Domain\Orders\Enums;

enum ExceptionFinancialMode: string
{
    case ReviewRequired = 'review_required';
    case ChargeCompleted = 'charge_completed';
    case ChargeContracted = 'charge_contracted';

    public function label(): string
    {
        return match ($this) {
            self::ReviewRequired => 'Exigir revisão financeira',
            self::ChargeCompleted => 'Cobrar somente exames concluídos',
            self::ChargeContracted => 'Manter valor contratado',
        };
    }
}
