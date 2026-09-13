<?php

namespace App\Domain\NetWorth\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NetWorthSnapshot extends Model
{
    protected $fillable = [
        'net_worth_account_id',
        'recorded_by_user_id',
        'balance_minor',
        'recorded_on',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(NetWorthAccount::class, 'net_worth_account_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected function casts(): array
    {
        return ['recorded_on' => 'immutable_date'];
    }
}
