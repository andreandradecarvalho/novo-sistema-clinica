<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Tenancy\Services\TenantContext;

final class ExamTypePolicy
{
    public function viewAny(User $actor): bool
    {
        return app(TenantPermission::class)->allows($actor, 'catalog.view');
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor) && app(TenantPermission::class)->allows($actor, 'catalog.manage');
    }

    public function view(User $actor, ExamType $examType): bool
    {
        return (int) $examType->tenant_id === app(TenantContext::class)->id() && $this->viewAny($actor);
    }

    public function update(User $actor, ExamType $examType): bool
    {
        return $this->view($actor, $examType) && $this->create($actor);
    }
}
