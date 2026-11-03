<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final readonly class TenantStorage
{
    public function __construct(private TenantContext $context) {}

    public function path(string $path): string
    {
        if (! preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_.-]+)*\z~D', $path) || str_contains($path, '..')) {
            throw new InvalidArgumentException('Invalid private storage path.');
        }

        return 'tenants/'.$this->context->id().'/'.$path;
    }

    public function put(string $path, string $contents): bool
    {
        return Storage::disk('local')->put($this->path($path), $contents);
    }

    public function get(string $path): ?string
    {
        return Storage::disk('local')->get($this->path($path));
    }
}
