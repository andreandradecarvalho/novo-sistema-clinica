<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ExamPreparation extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $table = 'exam_type_preparations';

    #[\Override]
    protected $fillable = ['title', 'instructions'];

    #[\Override]
    public $timestamps = false;

    /** @return BelongsTo<ExamType, $this> */
    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    protected static function booted(): void
    {
        self::updating(function (ExamPreparation $preparation): void {
            if ($preparation->isDirty('exam_type_id')) {
                throw new LogicException('The preparation owner cannot change.');
            }
        });
    }
}
