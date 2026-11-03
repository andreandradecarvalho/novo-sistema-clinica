<?php

declare(strict_types=1);

namespace App\Domain\Companies\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @property bool $is_active */
final class CompanyUnit extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $fillable = ['name', 'city', 'state', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        self::updating(function (CompanyUnit $unit): void {
            if ($unit->isDirty('company_id')) {
                throw new LogicException('The establishment owner cannot change.');
            }
        });
    }
}
