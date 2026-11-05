<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveRoom
{
    public function execute(Unit $unit, array $input, ?Room $room = null): Room
    {
        $input = self::normalize($input);
        Validator::make($input, self::rules())->validate();
        try {
            return DB::transaction(function () use ($unit, $input, $room): Room {
                $unit = Unit::query()->whereKey($unit->getKey())->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
                $room = $room === null ? new Room : $unit->rooms()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize($room->exists ? 'update' : 'create', $room->exists ? $room : [Room::class, $unit]);
                if (! $unit->is_active && (! $room->exists || (bool) $input['is_active'])) {
                    throw ValidationException::withMessages(['room' => 'A unidade precisa estar ativa para cadastrar ou ativar salas.']);
                }
                $event = $room->exists ? 'room_updated' : 'room_created';
                if (! $room->exists) {
                    $room->unit()->associate($unit);
                }
                $room->forceFill(array_intersect_key($input, array_flip(Room::EDITABLE)))->save();
                app(RegistryAudit::class)->record('rooms', $event, $room, Room::class);

                return $room;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['room_key' => 'Já existe uma sala com esta chave nesta clínica.']);
        }
    }

    public static function normalize(array $input): array
    {
        foreach (['room_key', 'name'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input['room_key']) && is_string($input['room_key'])) {
            $input['room_key'] = strtoupper($input['room_key']);
        }

        return $input;
    }

    public static function rules(): array
    {
        $rules = ['room_key' => ['required', 'string', 'max:20', 'regex:/\A[A-Z0-9][A-Z0-9_-]*\z/'],
            'name' => ['required', 'string', 'max:100'], 'ordem' => ['required', 'integer', 'min:0', 'max:65535'], 'is_active' => ['required', 'boolean']];
        foreach (['id', 'tenant_id', 'unit_id', 'pin', 'pin_hash', 'created_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
