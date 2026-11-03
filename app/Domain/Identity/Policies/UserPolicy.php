<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Tenancy\Services\TenantContext;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && (int) $actor->tenant_id === app(TenantContext::class)->id() && $actor->can('users.view');
    }

    public function assignUnit(User $actor, User $subject): bool
    {
        return $subject->is_active && $this->view($actor, $subject)
            && app(TenantPermission::class)->allows($actor, 'users.manage')
            && app(TenantPermission::class)->allows($actor, 'units.view');
    }

    public function view(User $actor, User $subject): bool
    {
        return (int) $actor->tenant_id === (int) $subject->tenant_id && $this->viewAny($actor);
    }
}
