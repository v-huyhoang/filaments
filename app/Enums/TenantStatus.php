<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blacklisted = 'blacklisted';
}
