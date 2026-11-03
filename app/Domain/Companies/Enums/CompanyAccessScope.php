<?php

declare(strict_types=1);

namespace App\Domain\Companies\Enums;

enum CompanyAccessScope: string
{
    case OccupationalStatus = 'occupational_status';
    case ReleasedAso = 'released_aso';

    public function label(): string
    {
        return match ($this) {
            self::OccupationalStatus => 'Situação operacional ocupacional',
            self::ReleasedAso => 'ASO liberado do empregador',
        };
    }

    public function permission(): string
    {
        return match ($this) {
            self::OccupationalStatus => 'company_portal.status.view',
            self::ReleasedAso => 'company_portal.aso.view',
        };
    }
}
