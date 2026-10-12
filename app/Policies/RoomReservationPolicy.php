<?php

namespace App\Policies;

use App\Models\RoomReservation;
use App\Models\User;

class RoomReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RoomReservation $roomReservation): bool
    {
        return $this->ownsReservation($user, $roomReservation);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RoomReservation $roomReservation): bool
    {
        return $this->ownsReservation($user, $roomReservation);
    }

    public function delete(User $user, RoomReservation $roomReservation): bool
    {
        return false;
    }

    public function restore(User $user, RoomReservation $roomReservation): bool
    {
        return false;
    }

    public function forceDelete(User $user, RoomReservation $roomReservation): bool
    {
        return false;
    }

    private function ownsReservation(User $user, RoomReservation $roomReservation): bool
    {
        return $roomReservation->room()
            ->whereHas('property', fn ($query) => $query->where('landlord_id', $user->id))
            ->exists();
    }
}
