<?php

namespace App\Models;

use Database\Factories\BudgetPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'start_date', 'end_date'])]
class BudgetPeriod extends Model
{
    /** @use HasFactory<BudgetPeriodFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'version' => 'integer'];
    }

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /** @return HasMany<BudgetCategory, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class);
    }

    /** @return HasMany<BudgetGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(BudgetGroup::class);
    }

    /** @return HasMany<BudgetIncome, $this> */
    public function incomes(): HasMany
    {
        return $this->hasMany(BudgetIncome::class);
    }

    /** @return HasMany<BudgetTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class);
    }

    /** @return HasMany<BudgetRecurringCharge, $this> */
    public function recurringCharges(): HasMany
    {
        return $this->hasMany(BudgetRecurringCharge::class);
    }

    /** @return HasMany<BudgetCommitment, $this> */
    public function commitments(): HasMany
    {
        return $this->hasMany(BudgetCommitment::class);
    }
}
