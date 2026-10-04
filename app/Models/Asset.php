<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'kind', 'institution', 'notes', 'liquidity', 'access_days', 'available_date', 'withdrawal_cost_cents', 'value_uncertain'])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['access_days' => 'integer', 'available_date' => 'immutable_date', 'withdrawal_cost_cents' => 'integer', 'value_uncertain' => 'boolean'];
    }

    /** @return HasMany<AssetMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class);
    }

    /** @return HasMany<AssetReserve, $this> */
    public function reserves(): HasMany
    {
        return $this->hasMany(AssetReserve::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<AssetValuation, $this> */
    public function valuations(): HasMany
    {
        return $this->hasMany(AssetValuation::class);
    }
}
