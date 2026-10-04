<?php

namespace App\Models;

use Database\Factories\SavingsContributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['request_id', 'budget_transaction_id', 'amount_cents', 'date', 'notes', 'budget_period_name'])]
class SavingsContribution extends Model
{
    /** @use HasFactory<SavingsContributionFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'date' => 'immutable_date'];
    }

    /** @return BelongsTo<SavingsGoal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class, 'savings_goal_id');
    }

    /** @return BelongsTo<BudgetTransaction, $this> */
    public function budgetTransaction(): BelongsTo
    {
        return $this->belongsTo(BudgetTransaction::class);
    }
}
