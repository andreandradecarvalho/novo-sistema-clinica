<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Models;

use App\Domain\Professionals\Models\Professional;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property array $rules
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable|null $approved_at
 */
final class OccupationalProtocol extends Model
{
    use BelongsToTenant;

    public const array LIST_COLUMNS = ['id', 'tenant_id', 'program_id', 'code', 'version', 'valid_from', 'valid_until', 'approved_by', 'approved_at'];

    #[\Override]
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['rules' => 'array', 'version' => 'integer', 'valid_from' => 'immutable_date', 'valid_until' => 'immutable_date', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<OccupationalProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(OccupationalProgram::class, 'program_id');
    }

    /** @return BelongsTo<Professional, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'approved_by');
    }

    protected static function booted(): void
    {
        self::creating(function (OccupationalProtocol $protocol): void {
            if ($protocol->approved_by !== null || $protocol->approved_at !== null) {
                throw new LogicException('New protocol versions cannot arrive approved.');
            }
        });
        self::updating(fn () => throw new LogicException('Protocol versions are immutable; approval uses its guarded action.'));
        self::deleting(fn () => throw new LogicException('Protocol versions cannot be deleted.'));
    }
}
