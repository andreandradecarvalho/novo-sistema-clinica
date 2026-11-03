<?php

declare(strict_types=1);

namespace App\Domain\Professionals\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class Professional extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $fillable = ['council', 'council_uf', 'council_number', 'specialty'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        self::updating(function (Professional $professional): void {
            if ($professional->isDirty('user_id')) {
                throw new LogicException('The professional user cannot change.');
            }
        });
    }
}
