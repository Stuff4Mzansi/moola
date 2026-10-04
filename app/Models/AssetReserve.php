<?php

namespace App\Models;

use Database\Factories\AssetReserveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['savings_goal_id', 'name', 'kind', 'amount_cents', 'is_automatic'])]
class AssetReserve extends Model
{
    /** @use HasFactory<AssetReserveFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_automatic' => 'boolean', 'asset_id' => 'integer', 'savings_goal_id' => 'integer', 'amount_cents' => 'integer'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<SavingsGoal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class, 'savings_goal_id');
    }
}
