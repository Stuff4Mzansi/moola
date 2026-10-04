<?php

namespace App\Models;

use Database\Factories\BudgetTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['savings_goal_id', 'interest_cents', 'budget_recurring_charge_id', 'budget_category_id', 'budget_commitment_id', 'amount_cents', 'date', 'description'])]
class BudgetTransaction extends Model
{
    public ?bool $interestIsEstimated = null;

    /** @use HasFactory<BudgetTransactionFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<BudgetPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'budget_period_id');
    }

    /** @return BelongsTo<BudgetRecurringCharge, $this> */
    public function recurringCharge(): BelongsTo
    {
        return $this->belongsTo(BudgetRecurringCharge::class, 'budget_recurring_charge_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['savings_goal_id' => 'integer', 'interest_cents' => 'integer', 'budget_recurring_charge_id' => 'integer', 'amount_cents' => 'integer', 'date' => 'immutable_date'];
    }
}
