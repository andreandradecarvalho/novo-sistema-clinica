<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Services\TenantContext;

final class TenantPermission
{
    public function allows(User $actor, string $permission): bool
    {
        return $actor->is_active && (int) $actor->tenant_id === app(TenantContext::class)->id()
            && $actor->two_factor_confirmed_at !== null && $actor->can($permission);
    }
}
