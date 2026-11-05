<?php

declare(strict_types=1);

namespace App\Domain\Visits\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Models\Visit;

final class VisitPolicy
{
    public function checkIn(User $actor, Appointment $appointment): bool
    {
        return $actor->can('view', $appointment) && app(TenantPermission::class)->allows($actor, 'visits.view')
            && app(TenantPermission::class)->allows($actor, 'visits.check_in') && app(TenantPermission::class)->allows($actor, 'orders.view');
    }

    public function cancel(User $actor, Visit $visit): bool
    {
        return $this->view($actor, $visit) && app(TenantPermission::class)->allows($actor, 'visits.cancel');
    }

    public function view(User $actor, Visit $visit): bool
    {
        return (int) $visit->tenant_id === app(TenantContext::class)->id() && app(TenantPermission::class)->allows($actor, 'visits.view')
            && $visit->appointment !== null && $actor->can('view', $visit->appointment) && $visit->order !== null && $actor->can('view', $visit->order);
    }
}
