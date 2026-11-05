<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $unit_price
 * @property string $discount_amount
 */
final class ExamOrderItem extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:2', 'discount_amount' => 'decimal:2'];
    }

    /** @return BelongsTo<ExamOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ExamOrder::class, 'exam_order_id');
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Order item snapshots cannot change.'));
        self::deleting(fn () => throw new LogicException('Order items cannot be deleted.'));
    }
}
