<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Scheduling\Actions\ListRooms;
use App\Domain\Scheduling\Actions\ReadRoom;
use App\Domain\Scheduling\Actions\SaveRoom;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Tenancy\Models\Unit;
use App\Http\Requests\SaveRoomRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class RoomController extends Controller
{
    public function index(Unit $unit, ListRooms $action): View
    {
        return view('rooms.index', ['unit' => $unit, 'rooms' => $action->execute($unit)]);
    }

    public function create(Unit $unit): View
    {
        Gate::authorize('create', [Room::class, $unit]);

        return view('rooms.form', ['unit' => $unit, 'room' => new Room]);
    }

    public function store(SaveRoomRequest $request, Unit $unit, SaveRoom $action): RedirectResponse
    {
        $room = $action->execute($unit, $request->validated());

        return redirect()->route('units.rooms.show', [$unit->public_id, $room->id])->with('status', 'Sala cadastrada.');
    }

    public function show(Unit $unit, Room $room, ReadRoom $action): View
    {
        return view('rooms.show', ['unit' => $unit, 'room' => $action->execute($unit, $room)]);
    }

    public function edit(Unit $unit, Room $room, ReadRoom $action): View
    {
        return view('rooms.form', ['unit' => $unit, 'room' => $action->execute($unit, $room, true)]);
    }

    public function update(SaveRoomRequest $request, Unit $unit, Room $room, SaveRoom $action): RedirectResponse
    {
        $room = $action->execute($unit, $request->validated(), $room);

        return redirect()->route('units.rooms.show', [$unit->public_id, $room->id])->with('status', 'Sala atualizada.');
    }
}
