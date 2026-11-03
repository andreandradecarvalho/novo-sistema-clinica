<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}.users.{userId}', fn (User $user, string $tenantId, string $userId): bool => (int) $tenantId === app(TenantContext::class)->id()
    && (int) $user->tenant_id === app(TenantContext::class)->id()
    && (int) $userId === (int) $user->getKey()
    && $user->is_active);
