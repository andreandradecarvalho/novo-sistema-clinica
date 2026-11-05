<?php

declare(strict_types=1);

namespace App\Domain\Orders\Enums;

enum OrderTransition: string
{
    case Authorize = 'authorize';
    case Cancel = 'cancel';
}
