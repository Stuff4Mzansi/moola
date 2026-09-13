<?php

namespace App\Domain\Budgeting\Enums;

enum BudgetGroup: string
{
    case Essentials = 'essentials';
    case GuiltFreeSpend = 'guilt_free_spend';
    case Future = 'future';

    public function label(): string
    {
        return match ($this) {
            self::Essentials => 'Essentials',
            self::GuiltFreeSpend => 'Guilt-Free Spend',
            self::Future => 'Future',
        };
    }
}
