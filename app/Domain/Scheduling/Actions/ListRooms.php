<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Models\Room;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListRooms
{
    public function execute(Unit $unit): LengthAwarePaginator
    {
        $unit = Unit::query()->whereKey($unit->getKey())->firstOrFail();
        Gate::authorize('viewAny', [Room::class, $unit]);
        $rooms = $unit->rooms()->select(['id', 'tenant_id', 'unit_id', 'room_key', 'name', 'ordem', 'is_active'])->orderBy('ordem')->orderBy('name')->orderBy('id')->paginate(20);
        app(RegistryAudit::class)->record('rooms', 'rooms_listed', $unit, Unit::class);

        return $rooms;
    }
}
