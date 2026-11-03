<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Tenant extends Model
{
    use SoftDeletes;

    #[\Override]
    protected $fillable = ['name', 'slug', 'timezone', 'status'];

    protected static function booted(): void
    {
        self::creating(function (Tenant $tenant): void {
            $tenant->public_id ??= (string) Str::ulid();
        });
    }
}
