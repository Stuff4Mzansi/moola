<?php

namespace App\Domain\Debts\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DebtPayment extends Model
{
    protected $fillable = [
        'debt_id',
        'recorded_by_user_id',
        'amount_minor',
        'balance_after_minor',
        'paid_on',
        'note',
    ];

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected function casts(): array
    {
        return ['paid_on' => 'immutable_date'];
    }
}
