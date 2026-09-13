<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HouseholdMembership extends Model
{
    protected $fillable = ['household_id', 'user_id', 'joined_at', 'removed_at'];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }
}
