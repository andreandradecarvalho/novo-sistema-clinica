<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** @property bool $is_active */
final class HealthQuestion extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $fillable = ['code', 'label', 'ordem'];

    #[\Override]
    public $timestamps = false;

    /** @return BelongsTo<HealthQuestionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(HealthQuestionGroup::class, 'group_id');
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'ordem' => 'integer', 'options' => 'array', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Publish a new question version instead.'));
        self::deleting(fn () => throw new LogicException('Question versions cannot be deleted.'));
    }
}
