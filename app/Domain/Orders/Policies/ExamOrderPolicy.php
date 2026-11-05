<?php

declare(strict_types=1);

namespace App\Domain\Orders\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Tenancy\Services\TenantContext;

final class ExamOrderPolicy
{
    public function viewAny(User $actor, Patient $patient): bool
    {
        return $actor->can('view', $patient) && app(TenantPermission::class)->allows($actor, 'orders.view');
    }

    public function create(User $actor, Patient $patient): bool
    {
        return $this->viewAny($actor, $patient) && app(TenantPermission::class)->allows($actor, 'orders.manage')
            && app(TenantPermission::class)->allows($actor, 'catalog.view');
    }

    public function view(User $actor, ExamOrder $order): bool
    {
        return (int) $order->tenant_id === app(TenantContext::class)->id()
            && $order->patient !== null && $this->viewAny($actor, $order->patient);
    }

    public function authorize(User $actor, ExamOrder $order): bool
    {
        return $this->cancel($actor, $order) && app(TenantPermission::class)->allows($actor, 'catalog.view');
    }

    public function cancel(User $actor, ExamOrder $order): bool
    {
        return $this->view($actor, $order) && app(TenantPermission::class)->allows($actor, 'orders.manage');
    }
}
