<?php

namespace App\Domain\Budgeting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BudgetAllocation extends Model
{
    protected $fillable = [
        'budget_period_id',
        'budget_subcategory_id',
        'allocation_minor',
        'opening_rollover_minor',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'budget_period_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(BudgetSubcategory::class, 'budget_subcategory_id');
    }
}
