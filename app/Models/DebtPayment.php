<?php

namespace App\Models;

use Database\Factories\DebtPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['request_id', 'budget_transaction_id', 'amount_cents', 'interest_cents', 'interest_is_estimated', 'date', 'notes', 'budget_period_name'])]
class DebtPayment extends Model
{
    /** @use HasFactory<DebtPaymentFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['interest_is_estimated' => 'boolean', 'amount_cents' => 'integer', 'interest_cents' => 'integer', 'date' => 'immutable_date'];
    }

    /** @return BelongsTo<Debt, $this> */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    /** @return BelongsTo<BudgetTransaction, $this> */
    public function budgetTransaction(): BelongsTo
    {
        return $this->belongsTo(BudgetTransaction::class);
    }
}
