<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Enums\RoomStatus;
use App\Models\Contract;
use App\Models\Room;
use App\Models\RoomReservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractValidator
{
    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function validate(array $attributes): array
    {
        return Validator::make($attributes, [
            'room_id' => ['required', 'integer'], 'tenant_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'rent_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'deposit_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'payment_due_day' => ['required', 'integer', 'between:1,28'],
            'status' => ['nullable', Rule::in([ContractStatus::Draft->value, ContractStatus::Scheduled->value])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();
    }

    /** @param array<string, mixed> $attributes */
    public function ensureRoomCanBeCommitted(Room $room, array $attributes, ?RoomReservation $reservation = null, ?Contract $except = null): void
    {
        if (in_array($room->status, [RoomStatus::MAINTENANCE, RoomStatus::INACTIVE], true)) {
            throw ValidationException::withMessages(['room_id' => __('messages.room_cannot_contract')]);
        }
        if (Contract::query()->where('room_id', $room->id)->committed()
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except->id))
            ->whereDate('start_date', '<=', $attributes['end_date'] ?? '9999-12-31')
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $attributes['start_date']))->lockForUpdate()->first(['id']) !== null) {
            throw ValidationException::withMessages(['start_date' => __('messages.contract_overlap')]);
        }
        if (RoomReservation::query()->liveHold()->where('room_id', $room->id)
            ->when($reservation, fn (Builder $query) => $query->whereKeyNot($reservation->id))->lockForUpdate()->first(['id']) !== null) {
            throw ValidationException::withMessages(['room_id' => __('messages.room_live_hold')]);
        }
    }
}
