<?php

namespace App\Domain\Identity\Enums;

enum Capability: string
{
    case BudgetView = 'budget.view';
    case BudgetManage = 'budget.manage';
    case TransactionsView = 'transactions.view';
    case TransactionsManage = 'transactions.manage';
    case DebtsView = 'debts.view';
    case DebtsManage = 'debts.manage';
    case NetWorthView = 'networth.view';
    case NetWorthManage = 'networth.manage';
    case SubscriptionsView = 'subscriptions.view';
    case SubscriptionsManage = 'subscriptions.manage';
    case UsersManage = 'users.manage';
    case SettingsManage = 'settings.manage';
}
