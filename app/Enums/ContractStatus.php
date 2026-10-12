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
        return __('statuses.contract.'.$this->value);
    }
}
