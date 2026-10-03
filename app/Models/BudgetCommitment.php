<?php

namespace App\Models;

use Database\Factories\BudgetCommitmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['subscription_id', 'name', 'scheduled_date', 'amount_cents', 'is_current'])]
class BudgetCommitment extends Model
{
    /** @use HasFactory<BudgetCommitmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scheduled_date' => 'immutable_date', 'amount_cents' => 'integer', 'is_current' => 'boolean'];
    }

    /** @return HasOne<BudgetTransaction, $this> */
    public function transaction(): HasOne
    {
        return $this->hasOne(BudgetTransaction::class);
    }
}
