<?php

declare(strict_types=1);

namespace App\Domain\Companies\Models;

use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** @property bool $is_active */
final class Company extends Model
{
    use BelongsToTenant, SoftDeletes;

    #[\Override]
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<CompanyUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(CompanyUnit::class);
    }

    /** @return HasMany<CompanyAccess, $this> */
    public function accesses(): HasMany
    {
        return $this->hasMany(CompanyAccess::class);
    }

    /** @return HasMany<OccupationalProgram, $this> */
    public function programs(): HasMany
    {
        return $this->hasMany(OccupationalProgram::class);
    }

    protected static function booted(): void
    {
        self::creating(function (Company $company): void {
            $company->public_id ??= (string) Str::ulid();
            $company->slug ??= Str::lower($company->public_id);
        });
    }
}
