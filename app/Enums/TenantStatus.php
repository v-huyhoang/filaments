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
        return __('statuses.tenant.'.$this->value);
    }
}
