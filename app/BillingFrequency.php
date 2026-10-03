<?php

namespace App;

enum BillingFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function paymentsPerYear(): int
    {
        return match ($this) {
            self::Weekly => 52,
            self::Monthly => 12,
            self::Quarterly => 4,
            self::Yearly => 1,
        };
    }

    public function intervalMonths(): int
    {
        return match ($this) {
            self::Weekly => 0,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
    }
}
