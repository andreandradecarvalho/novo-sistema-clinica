<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class MissingTenant extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A tenant context is required.');
    }
}
