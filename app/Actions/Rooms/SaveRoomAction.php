<?php

namespace App\Actions\Rooms;

use App\Enums\ContractStatus;
use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveRoomAction
{
    public function __construct(private RoomValidator $validator) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $landlord, array $attributes, ?Room $room = null): Room
    {
        Gate::forUser($landlord)->authorize($room === null ? 'create' : 'update', $room ?? Room::class);
        $validated = $this->validator->validate($landlord, $attributes, $room);

        return DB::transaction(function () use ($landlord, $validated, $room): Room {
            Property::query()->where('landlord_id', $landlord->id)->whereKey($validated['property_id'])->lockForUpdate()->firstOrFail();
            if ($room !== null) {
                $room = Room::query()->lockForUpdate()->findOrFail($room->id);
                Gate::forUser($landlord)->authorize('update', $room);
                if ((int) $validated['property_id'] !== $room->property_id &&
                    ($room->contracts()->exists() || $room->reservations()->exists() || DB::table('meter_readings')->where('room_id', $room->id)->exists())) {
                    throw ValidationException::withMessages(['property_id' => __('workflow.room_history_locked')]);
                }
            }
            $occupied = $room?->contracts()->where('status', ContractStatus::Active)->exists() ?? false;
            if (($occupied && $validated['status'] !== RoomStatus::OCCUPIED->value) || (! $occupied && $validated['status'] === RoomStatus::OCCUPIED->value)) {
                throw ValidationException::withMessages(['status' => __('workflow.room_status_managed')]);
            }
            if ($room === null) {
                $room = new Room;
            }
            $room->fill($validated)->save();

            return $room;
        }, attempts: 3);
    }
}
