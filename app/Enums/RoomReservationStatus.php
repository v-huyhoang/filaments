<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RoomReservationStatus: string implements HasLabel
{
    case Held = 'held';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Converted = 'converted';

    public function getLabel(): ?string
    {
        return __('statuses.room_reservation.'.$this->value);
    }
}
