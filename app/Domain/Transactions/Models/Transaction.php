<?php

namespace App\Domain\Transactions\Models;

use App\Domain\Budgeting\Models\BudgetSubcategory;
use App\Domain\Identity\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Transactions\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'household_id',
        'entered_by_user_id',
        'budget_subcategory_id',
        'type',
        'amount_minor',
        'transacted_on',
        'description',
    ];

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(BudgetSubcategory::class, 'budget_subcategory_id');
    }

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'transacted_on' => 'immutable_date',
        ];
    }
}
