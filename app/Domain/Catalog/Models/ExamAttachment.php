<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ExamAttachment extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $table = 'exam_type_attachments';

    #[\Override]
    protected $fillable = ['label'];

    #[\Override]
    protected $hidden = ['disk', 'path'];

    /** @return BelongsTo<ExamType, $this> */
    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Attachments cannot be overwritten.'));
        self::deleting(fn () => throw new LogicException('Attachments cannot be deleted through model operations.'));
    }
}
