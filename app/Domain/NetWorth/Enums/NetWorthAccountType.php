<?php

namespace App\Domain\NetWorth\Enums;

enum NetWorthAccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
}
