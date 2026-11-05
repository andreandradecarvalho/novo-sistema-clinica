<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Scheduling\Actions\SaveRoom;
use App\Domain\Scheduling\Models\Room;
use Illuminate\Foundation\Http\FormRequest;

final class SaveRoomRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(SaveRoom::normalize($this->all()));
    }

    public function authorize(): bool
    {
        $room = $this->route('room');

        return $room instanceof Room ? $this->user()->can('update', $room) : $this->user()->can('create', [Room::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return SaveRoom::rules();
    }
}
