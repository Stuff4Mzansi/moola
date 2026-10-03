<?php

namespace App\Models;

use Database\Factories\BudgetRecurringChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['budget_recurring_expense_id', 'budget_category_id', 'name', 'scheduled_date', 'amount_cents', 'history_months', 'history_payments', 'is_current'])]
class BudgetRecurringCharge extends Model
{
    /** @use HasFactory<BudgetRecurringChargeFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['budget_recurring_expense_id' => 'integer', 'budget_category_id' => 'integer', 'scheduled_date' => 'immutable_date', 'amount_cents' => 'integer', 'history_months' => 'integer', 'history_payments' => 'integer', 'is_current' => 'boolean'];
    }

    /** @return HasOne<BudgetTransaction, $this> */
    public function transaction(): HasOne
    {
        return $this->hasOne(BudgetTransaction::class);
    }
}
