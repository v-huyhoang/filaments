<?php

namespace App\Enums;

enum RoomStatus: string
{
    case AVAILABLE = 'available';
    case OCCUPIED = 'occupied';
    case MAINTENANCE = 'maintenance';
    case INACTIVE = 'inactive';

    // public function label(): string
    // {
    //     return match($this) {
    //         self::AVAILABLE => 'Bản nháp',
    //         self::OCCUPIED => 'Chờ duyệt',
    //         self::MAINTENANCE => 'Đã xuất bản',
    //         self::INACTIVE => 'Đã lưu trữ',
    //     };
    // }
}
