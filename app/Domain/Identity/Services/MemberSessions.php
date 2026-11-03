<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class MemberSessions
{
    public function __construct(private TenantContext $context) {}

    private function query(int $userId): Builder
    {
        return DB::table('sessions')->where('tenant_id', $this->context->id())->where('user_id', $userId);
    }

    public function list(int $userId): Collection
    {
        return $this->query($userId)->select(['id', 'ip_address', 'last_activity'])->orderByDesc('last_activity')->get();
    }

    public function revoke(int $userId, string $id): bool
    {
        return $this->query($userId)->where('id', $id)->delete() > 0;
    }

    public function revokeOthers(int $userId, ?string $keepId = null): void
    {
        $this->query($userId)->when($keepId !== null, fn (Builder $query) => $query->where('id', '<>', $keepId))->delete();
    }
}
