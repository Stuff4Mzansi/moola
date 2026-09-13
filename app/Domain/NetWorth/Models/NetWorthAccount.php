<?php

namespace App\Domain\NetWorth\Models;

use App\Domain\Identity\Models\Household;
use App\Domain\NetWorth\Enums\NetWorthAccountType;
use Database\Factories\NetWorthAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NetWorthAccount extends Model
{
    /** @use HasFactory<NetWorthAccountFactory> */
    use HasFactory;

    protected $fillable = ['household_id', 'name', 'type', 'current_balance_minor', 'is_archived'];

    protected static function newFactory(): NetWorthAccountFactory
    {
        return NetWorthAccountFactory::new();
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(NetWorthSnapshot::class);
    }

    protected function casts(): array
    {
        return [
            'type' => NetWorthAccountType::class,
            'is_archived' => 'boolean',
        ];
    }
}
