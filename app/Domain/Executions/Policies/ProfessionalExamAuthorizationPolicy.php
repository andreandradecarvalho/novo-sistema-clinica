<?php

declare(strict_types=1);

namespace App\Domain\Executions\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Professionals\Models\Professional;

final class ProfessionalExamAuthorizationPolicy
{
    public function viewAny(User $actor, Professional $professional): bool
    {
        return $actor->can('view', $professional) && app(TenantPermission::class)->allows($actor, 'catalog.view')
            && app(TenantPermission::class)->allows($actor, 'executions.authorizations.view');
    }

    public function manage(User $actor, Professional $professional): bool
    {
        return $this->viewAny($actor, $professional) && app(TenantPermission::class)->allows($actor, 'executions.authorizations.manage');
    }
}
