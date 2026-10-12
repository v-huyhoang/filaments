<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RoomStatus: string implements HasLabel
{
    case AVAILABLE = 'available';
    case OCCUPIED = 'occupied';
    case MAINTENANCE = 'maintenance';
    case INACTIVE = 'inactive';

    public function getLabel(): ?string
    {
        return __('statuses.room.'.$this->value);
    }
}
