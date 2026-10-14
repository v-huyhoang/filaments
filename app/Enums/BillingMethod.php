<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BillingMethod: string implements HasLabel
{
    case Metered = 'metered';
    case Fixed = 'fixed';
    case PerPerson = 'per_person';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return __('services.billing_methods.'.$this->value);
    }
}
