<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Models\Room;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use Illuminate\Support\Facades\Gate;

final class ReadRoom
{
    public function execute(Unit $unit, Room $room, bool $editing = false): Room
    {
        $unit = Unit::query()->whereKey($unit->getKey())->firstOrFail();
        $room = $unit->rooms()->whereKey($room->getKey())->firstOrFail();
        Gate::authorize($editing ? 'update' : 'view', $room);
        app(RegistryAudit::class)->record('rooms', $editing ? 'room_edit_viewed' : 'room_viewed', $room, Room::class);

        return $room;
    }
}
