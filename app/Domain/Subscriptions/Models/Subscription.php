<?php

namespace App\Domain\Subscriptions\Models;

use App\Domain\Budgeting\Models\BudgetSubcategory;
use App\Domain\Identity\Models\Household;
use App\Domain\Subscriptions\Enums\RecurrenceUnit;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'household_id',
        'budget_subcategory_id',
        'name',
        'amount_minor',
        'recurrence_unit',
        'recurrence_interval',
        'anchor_day',
        'occurs_on_last_day',
        'next_charge_on',
        'is_active',
    ];

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(BudgetSubcategory::class, 'budget_subcategory_id');
    }

    protected function casts(): array
    {
        return [
            'recurrence_unit' => RecurrenceUnit::class,
            'occurs_on_last_day' => 'boolean',
            'next_charge_on' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }
}
