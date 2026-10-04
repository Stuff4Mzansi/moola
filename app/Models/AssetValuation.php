<?php

namespace App\Models;

use Database\Factories\AssetValuationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['amount_cents', 'date', 'notes', 'movement_cutoff_id'])]
class AssetValuation extends Model
{
    /** @use HasFactory<AssetValuationFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['movement_cutoff_id' => 'integer', 'asset_id' => 'integer', 'amount_cents' => 'integer', 'date' => 'immutable_date'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
