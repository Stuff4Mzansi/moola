<?php

namespace App\Models;

use Database\Factories\BudgetCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'allocated_cents', 'kind'])]
class BudgetCategory extends Model
{
    /** @use HasFactory<BudgetCategoryFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['allocated_cents' => 'integer', 'budget_group_id' => 'integer'];
    }

    /** @return HasMany<BudgetTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class);
    }
}
