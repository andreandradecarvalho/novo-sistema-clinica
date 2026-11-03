<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;

final class UnitPolicy
{
    public function viewAny(User $actor): bool
    {
        return app(TenantPermission::class)->allows($actor, 'units.view');
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor) && app(TenantPermission::class)->allows($actor, 'units.manage');
    }

    public function view(User $actor, Unit $unit): bool
    {
        return (int) $unit->tenant_id === app(TenantContext::class)->id() && $this->viewAny($actor);
    }

    public function update(User $actor, Unit $unit): bool
    {
        return $this->view($actor, $unit) && $this->create($actor);
    }
}
