<?php

namespace App\Domain\Budgeting\Models;

use App\Domain\Budgeting\Enums\BudgetPeriodState;
use App\Domain\Identity\Models\Household;
use Database\Factories\BudgetPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BudgetPeriod extends Model
{
    /** @use HasFactory<BudgetPeriodFactory> */
    use HasFactory;

    protected $fillable = [
        'household_id',
        'starts_on',
        'ends_on',
        'expected_income_minor',
        'state',
        'finalized_at',
    ];

    protected static function newFactory(): BudgetPeriodFactory
    {
        return BudgetPeriodFactory::new();
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'state' => BudgetPeriodState::class,
            'finalized_at' => 'immutable_datetime',
        ];
    }
}
