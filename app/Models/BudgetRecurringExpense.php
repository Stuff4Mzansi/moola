<?php

namespace App\Models;

use App\BillingFrequency;
use Database\Factories\BudgetRecurringExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'category_name', 'amount_cents', 'billing_frequency', 'start_date', 'end_date', 'is_active'])]
class BudgetRecurringExpense extends Model
{
    /** @use HasFactory<BudgetRecurringExpenseFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'billing_frequency' => BillingFrequency::class, 'start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'is_active' => 'boolean'];
    }

    /** @return HasMany<BudgetRecurringCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(BudgetRecurringCharge::class);
    }
}
