<?php

namespace App\Models;

use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'creditor', 'opening_balance_cents', 'balance_date', 'annual_rate_basis_points', 'minimum_payment_cents', 'due_anchor', 'notes'])]
class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['opening_balance_cents' => 'integer', 'annual_rate_basis_points' => 'integer', 'minimum_payment_cents' => 'integer', 'balance_date' => 'immutable_date', 'due_anchor' => 'immutable_date'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<DebtPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    /** @return HasMany<BudgetRecurringExpense, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(BudgetRecurringExpense::class);
    }
}
