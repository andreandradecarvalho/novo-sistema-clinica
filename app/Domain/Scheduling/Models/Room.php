<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** @property bool $is_active */
final class Room extends Model
{
    use BelongsToTenant;

    public const array EDITABLE = ['room_key', 'name', 'ordem', 'is_active'];

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'ordem' => 'integer'];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    protected static function booted(): void
    {
        self::updating(function (Room $room): void {
            if ($room->isDirty('unit_id') || $room->isDirty('pin_hash')) {
                throw new LogicException('Room ownership and credentials require a dedicated workflow.');
            }
        });
        self::deleting(fn () => throw new LogicException('Deactivate rooms instead of deleting them.'));
    }
}
