<?php

namespace App\Models;

use Database\Factories\BudgetTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['budget_recurring_charge_id', 'budget_category_id', 'budget_commitment_id', 'amount_cents', 'date', 'description'])]
class BudgetTransaction extends Model
{
    /** @use HasFactory<BudgetTransactionFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<BudgetRecurringCharge, $this> */
    public function recurringCharge(): BelongsTo
    {
        return $this->belongsTo(BudgetRecurringCharge::class, 'budget_recurring_charge_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['budget_recurring_charge_id' => 'integer', 'amount_cents' => 'integer', 'date' => 'immutable_date'];
    }
}
