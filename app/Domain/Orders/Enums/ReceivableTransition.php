<?php

declare(strict_types=1);

namespace App\Domain\Orders\Enums;

enum ReceivableTransition: string
{
    case Settle = 'settle';
    case Cancel = 'cancel';

    public function status(): string
    {
        return $this === self::Settle ? 'paid' : 'canceled';
    }
}
