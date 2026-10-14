<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RoomValidator
{
    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function validate(User $landlord, array $attributes, ?Room $room): array
    {
        return Validator::make($attributes, [
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where('landlord_id', $landlord->id)->whereNull('deleted_at')],
            'room_number' => ['required', 'string', 'max:255', Rule::unique('rooms', 'room_number')->where('property_id', $attributes['property_id'] ?? null)->ignore($room)],
            'floor' => ['required', 'integer', 'min:1'], 'area' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'base_rent' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'deposit_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_occupants' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::enum(RoomStatus::class)],
            'description' => ['nullable', 'string', 'max:5000'],
        ])->validate();
    }
}
