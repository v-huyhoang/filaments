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
        return match ($this) {
            self::AVAILABLE => 'Còn trống',
            self::OCCUPIED => 'Đang có người thuê',
            self::MAINTENANCE => 'Bảo trì',
            self::INACTIVE => 'Ngừng hoạt động',
        };
    }
}
