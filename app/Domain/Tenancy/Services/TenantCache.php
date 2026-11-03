<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

final readonly class TenantCache
{
    public function __construct(private TenantContext $context) {}

    public function remember(string $key, int $seconds, Closure $callback): mixed
    {
        return Cache::remember($this->context->key($key), $seconds, $callback);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->context->key($key));
    }
}
