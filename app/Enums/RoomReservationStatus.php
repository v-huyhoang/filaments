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
        return match ($this) {
            self::Held => 'Đang giữ chỗ',
            self::Cancelled => 'Đã hủy',
            self::Expired => 'Đã hết hạn',
            self::Converted => 'Đã chuyển thành hợp đồng',
        };
    }
}
