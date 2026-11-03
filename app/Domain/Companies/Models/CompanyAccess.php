<?php

declare(strict_types=1);

namespace App\Domain\Companies\Models;

use App\Domain\Companies\Enums\CompanyAccessScope;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable|null $revoked_at
 */
final class CompanyAccess extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $table = 'company_user_access';

    #[\Override]
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['scope' => CompanyAccessScope::class, 'valid_from' => 'immutable_datetime', 'valid_until' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    protected static function booted(): void
    {
        self::updating(function (CompanyAccess $access): void {
            if ($access->getRawOriginal('revoked_at') !== null || array_diff(array_keys($access->getDirty()), ['revoked_at', 'updated_at']) !== []) {
                throw new LogicException('Company grants are immutable except for revocation.');
            }
        });
        self::deleting(fn () => throw new LogicException('Company grants cannot be deleted.'));
    }
}
