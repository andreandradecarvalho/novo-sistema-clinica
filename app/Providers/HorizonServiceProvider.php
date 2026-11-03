<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

final class HorizonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Payloads aggregate all clinics. Deny even locally until platform identity is implemented.
        Horizon::auth(fn (): bool => false);
    }
}
