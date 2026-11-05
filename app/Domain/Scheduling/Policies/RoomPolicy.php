<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;

final class RoomPolicy
{
    public function viewAny(User $actor, Unit $unit): bool
    {
        return $actor->can('view', $unit) && app(TenantPermission::class)->allows($actor, 'rooms.view');
    }

    public function create(User $actor, Unit $unit): bool
    {
        return $this->viewAny($actor, $unit) && app(TenantPermission::class)->allows($actor, 'rooms.manage');
    }

    public function view(User $actor, Room $room): bool
    {
        return (int) $room->tenant_id === app(TenantContext::class)->id() && $room->unit !== null && $this->viewAny($actor, $room->unit);
    }

    public function update(User $actor, Room $room): bool
    {
        return $this->view($actor, $room) && app(TenantPermission::class)->allows($actor, 'rooms.manage');
    }
}
