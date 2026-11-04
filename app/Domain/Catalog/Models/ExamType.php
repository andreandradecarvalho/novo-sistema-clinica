<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property bool $is_active
 * @property bool $requires_fasting
 * @property string|null $price
 */
final class ExamType extends Model
{
    use BelongsToTenant;

    public const array EDITABLE = ['code', 'name', 'requires_fasting', 'default_duration_min', 'price', 'is_active', 'ordem'];

    #[\Override]
    protected $fillable = self::EDITABLE;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'requires_fasting' => 'boolean', 'default_duration_min' => 'integer', 'price' => 'decimal:2', 'ordem' => 'integer'];
    }

    public static function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    /** @return HasMany<ExamPreparation, $this> */
    public function preparations(): HasMany
    {
        return $this->hasMany(ExamPreparation::class);
    }

    protected static function booted(): void
    {
        self::creating(function (ExamType $examType): void {
            $examType->public_id ??= (string) Str::ulid();
        });
        self::saving(function (ExamType $examType): void {
            $examType->code = self::normalizeCode((string) $examType->code);
            if ($examType->exists && $examType->isDirty('public_id')) {
                throw new LogicException('The public identifier cannot change.');
            }
        });
    }
}
