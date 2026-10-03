<?php

namespace App\Models;

use Database\Factories\BudgetGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'percentage_basis_points'])]
class BudgetGroup extends Model
{
    /** @use HasFactory<BudgetGroupFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['percentage_basis_points' => 'integer'];
    }

    /** @return HasMany<BudgetCategory, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class);
    }
}
