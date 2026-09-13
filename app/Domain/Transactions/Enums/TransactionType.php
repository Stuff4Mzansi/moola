<?php

namespace App\Domain\Transactions\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
}
