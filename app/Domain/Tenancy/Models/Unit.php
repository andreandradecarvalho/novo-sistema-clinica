<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** @property bool $is_active */
final class Unit extends Model
{
    use BelongsToTenant, SoftDeletes;

    #[\Override]
    protected $fillable = ['name', 'city', 'state', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        self::creating(function (Unit $unit): void {
            $unit->public_id ??= (string) Str::ulid();
        });
    }
}
