<?php

namespace App\Domain\Budgeting\Models;

use App\Domain\Budgeting\Enums\BudgetGroup;
use App\Domain\Identity\Models\Household;
use Database\Factories\BudgetSubcategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BudgetSubcategory extends Model
{
    /** @use HasFactory<BudgetSubcategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'household_id',
        'main_group_identifier',
        'label',
        'position',
        'is_archived',
        'rollover_enabled',
    ];

    protected static function newFactory(): BudgetSubcategoryFactory
    {
        return BudgetSubcategoryFactory::new();
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
            'main_group_identifier' => BudgetGroup::class,
            'is_archived' => 'boolean',
            'rollover_enabled' => 'boolean',
        ];
    }
}
