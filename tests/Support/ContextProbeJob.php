<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

final class ContextProbeJob implements ShouldQueue
{
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $resultKey, public int $tenantId, public int $userId, public bool $fail = false) {}

    public function handle(TenantContext $context): void
    {
        $user = User::query()->findOrFail($this->userId);
        Redis::rpush($this->resultKey, json_encode([
            'pid' => getmypid(), 'tenant_id' => $context->id(), 'expected' => $this->tenantId,
            'user_tenant' => (int) $user->tenant_id, 'can_view' => $user->can('users.view'),
            'team_id' => (int) app(PermissionRegistrar::class)->getPermissionsTeamId(),
        ], JSON_THROW_ON_ERROR));
        if ($this->fail) {
            throw new RuntimeException('Synthetic tenant failure.');
        }
    }
}
