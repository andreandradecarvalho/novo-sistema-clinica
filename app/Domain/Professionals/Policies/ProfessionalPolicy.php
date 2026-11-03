<?php

declare(strict_types=1);

namespace App\Domain\Professionals\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Tenancy\Services\TenantContext;

final class ProfessionalPolicy
{
    public function viewAny(User $actor): bool
    {
        return app(TenantPermission::class)->allows($actor, 'professionals.view');
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor) && app(TenantPermission::class)->allows($actor, 'professionals.manage');
    }

    public function view(User $actor, Professional $professional): bool
    {
        return (int) $professional->tenant_id === app(TenantContext::class)->id() && $this->viewAny($actor);
    }

    public function update(User $actor, Professional $professional): bool
    {
        return $this->view($actor, $professional) && $this->create($actor);
    }
}
