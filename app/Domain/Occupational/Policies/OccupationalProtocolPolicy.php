<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProtocol;
use App\Domain\Occupational\Services\ProtocolApprover;
use App\Domain\Tenancy\Services\TenantContext;

final class OccupationalProtocolPolicy
{
    public function viewAny(User $actor, OccupationalProgram $program): bool
    {
        return $actor->can('view', $program) && app(TenantPermission::class)->allows($actor, 'protocols.view');
    }

    public function create(User $actor, OccupationalProgram $program): bool
    {
        return $this->viewAny($actor, $program) && app(TenantPermission::class)->allows($actor, 'protocols.manage')
            && app(TenantPermission::class)->allows($actor, 'catalog.view');
    }

    public function view(User $actor, OccupationalProtocol $protocol): bool
    {
        return (int) $protocol->tenant_id === app(TenantContext::class)->id()
            && $protocol->program !== null && $this->viewAny($actor, $protocol->program);
    }

    public function approve(User $actor, OccupationalProtocol $protocol): bool
    {
        return $this->view($actor, $protocol) && app(TenantPermission::class)->allows($actor, 'protocols.approve')
            && app(TenantPermission::class)->allows($actor, 'clinical.access')
            && app(TenantPermission::class)->allows($actor, 'catalog.view') && app(ProtocolApprover::class)->find($actor) !== null;
    }
}
