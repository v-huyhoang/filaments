<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TenantStatus: string implements HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blacklisted = 'blacklisted';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Inactive => 'Ngừng hoạt động',
            self::Blacklisted => 'Danh sách hạn chế',
        };
    }
}
