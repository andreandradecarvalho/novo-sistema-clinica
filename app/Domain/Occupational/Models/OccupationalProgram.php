<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Models;

use App\Domain\Companies\Models\Company;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_until
 */
final class OccupationalProgram extends Model
{
    use BelongsToTenant;

    public const array LIST_COLUMNS = ['id', 'tenant_id', 'company_id', 'version', 'valid_from', 'valid_until', 'source_reference'];

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['context_encrypted'];

    protected function casts(): array
    {
        return ['context_encrypted' => 'encrypted:array', 'valid_from' => 'immutable_date', 'valid_until' => 'immutable_date'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<OccupationalProtocol, $this> */
    public function protocols(): HasMany
    {
        return $this->hasMany(OccupationalProtocol::class, 'program_id');
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Program references are immutable. Register a new version.'));
        self::deleting(fn () => throw new LogicException('Program references cannot be deleted.'));
    }
}
