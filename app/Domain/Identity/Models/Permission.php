<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\Capability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Permission extends Model
{
    protected $fillable = ['household_id', 'user_id', 'capability'];

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
        return ['capability' => Capability::class];
    }
}
