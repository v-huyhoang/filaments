<?php

namespace App\Enums;

enum RoomReservationStatus: string
{
    case Held = 'held';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Converted = 'converted';
}
