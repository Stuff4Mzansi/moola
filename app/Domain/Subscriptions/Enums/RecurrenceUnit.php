<?php

namespace App\Domain\Subscriptions\Enums;

enum RecurrenceUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
}
