<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';
}
