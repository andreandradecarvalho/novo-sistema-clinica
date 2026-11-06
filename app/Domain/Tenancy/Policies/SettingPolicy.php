<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;

final class SettingPolicy
{
    public function viewExceptionPolicy(User $actor): bool
    {
        return app(TenantPermission::class)->allows($actor, 'financial_policies.view')
            && app(TenantPermission::class)->allows($actor, 'receivables.view');
    }

    public function manageExceptionPolicy(User $actor): bool
    {
        return $this->viewExceptionPolicy($actor) && app(TenantPermission::class)->allows($actor, 'financial_policies.manage')
            && app(TenantPermission::class)->allows($actor, 'receivables.manage');
    }
}
