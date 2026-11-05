<?php

declare(strict_types=1);

namespace App\Domain\Clinical\Policies;

use App\Domain\Clinical\Models\HealthRecord;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Models\Visit;

final class HealthRecordPolicy
{
    public function viewAny(User $actor, Visit $visit): bool
    {
        return $actor->can('view', $visit) && app(TenantPermission::class)->allows($actor, 'clinical.access')
            && app(TenantPermission::class)->allows($actor, 'health_records.view');
    }

    public function create(User $actor, Visit $visit): bool
    {
        return $this->viewAny($actor, $visit) && app(TenantPermission::class)->allows($actor, 'health_records.create')
            && app(TenantPermission::class)->allows($actor, 'catalog.view');
    }

    public function view(User $actor, HealthRecord $record): bool
    {
        return (int) $record->tenant_id === app(TenantContext::class)->id() && $record->visit !== null && $this->viewAny($actor, $record->visit);
    }
}
