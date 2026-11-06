<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @property array<string, mixed>|null $value */
final class Setting extends Model
{
    use BelongsToTenant;

    public const CREATED_AT = null;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'array', 'updated_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Settings require an explicit configuration action.'));
        self::deleting(fn () => throw new LogicException('Settings history cannot be deleted.'));
    }
}
