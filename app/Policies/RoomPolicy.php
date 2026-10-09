<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Room $room): bool
    {
        return $this->ownsRoom($user, $room);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Room $room): bool
    {
        return $this->ownsRoom($user, $room);
    }

    public function delete(User $user, Room $room): bool
    {
        return $this->ownsRoom($user, $room);
    }

    public function restore(User $user, Room $room): bool
    {
        return $this->ownsRoom($user, $room);
    }

    public function forceDelete(User $user, Room $room): bool
    {
        return $this->ownsRoom($user, $room);
    }

    private function ownsRoom(User $user, Room $room): bool
    {
        return $room->property()
            ->where('landlord_id', $user->id)
            ->exists();
    }
}
