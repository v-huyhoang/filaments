<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Bản nháp',
            self::Scheduled => 'Đã lên lịch',
            self::Active => 'Đang hiệu lực',
            self::Expired => 'Đã hết hạn',
            self::Terminated => 'Đã chấm dứt',
            self::Cancelled => 'Đã hủy',
        };
    }
}
