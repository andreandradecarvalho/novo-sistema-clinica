<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class HealthQuestionGroup extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $fillable = ['code', 'name', 'ordem'];

    #[\Override]
    public $timestamps = false;

    /** @return HasMany<HealthQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(HealthQuestion::class, 'group_id');
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Question groups are immutable.'));
        self::deleting(fn () => throw new LogicException('Question groups cannot be deleted.'));
    }
}
