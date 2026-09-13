<?php

namespace App\Domain\Budgeting\Enums;

enum BudgetPeriodState: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Closed = 'closed';
}
