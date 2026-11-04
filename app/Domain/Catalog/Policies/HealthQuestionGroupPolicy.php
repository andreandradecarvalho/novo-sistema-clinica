<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Tenancy\Services\TenantContext;

final class HealthQuestionGroupPolicy
{
    public function viewAny(User $actor): bool
    {
        return app(TenantPermission::class)->allows($actor, 'catalog.view');
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor) && app(TenantPermission::class)->allows($actor, 'catalog.manage');
    }

    public function view(User $actor, HealthQuestionGroup $group): bool
    {
        return (int) $group->tenant_id === app(TenantContext::class)->id() && $this->viewAny($actor);
    }

    public function publish(User $actor, HealthQuestionGroup $group): bool
    {
        return $this->view($actor, $group) && $this->create($actor);
    }
}
