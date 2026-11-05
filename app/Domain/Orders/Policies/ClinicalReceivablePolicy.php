<?php

declare(strict_types=1);

namespace App\Domain\Orders\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Tenancy\Services\TenantContext;

final class ClinicalReceivablePolicy
{
    public function viewAny(User $actor, ExamOrder $order): bool
    {
        return $actor->can('view', $order) && app(TenantPermission::class)->allows($actor, 'receivables.view');
    }

    public function create(User $actor, ExamOrder $order): bool
    {
        return $this->viewAny($actor, $order) && app(TenantPermission::class)->allows($actor, 'receivables.manage');
    }

    public function settle(User $actor, ClinicalReceivable $receivable): bool
    {
        return $this->transition($actor, $receivable, 'receivables.settle');
    }

    public function cancel(User $actor, ClinicalReceivable $receivable): bool
    {
        return $this->transition($actor, $receivable, 'receivables.cancel');
    }

    private function transition(User $actor, ClinicalReceivable $receivable, string $permission): bool
    {
        return (int) $receivable->tenant_id === app(TenantContext::class)->id() && $receivable->order !== null
            && $this->viewAny($actor, $receivable->order) && app(TenantPermission::class)->allows($actor, $permission);
    }
}
