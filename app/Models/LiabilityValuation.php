<?php

namespace App\Models;

use Database\Factories\LiabilityValuationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['amount_cents', 'date', 'notes'])]
class LiabilityValuation extends Model
{
    /** @use HasFactory<LiabilityValuationFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'date' => 'immutable_date'];
    }

    /** @return BelongsTo<Liability, $this> */
    public function liability(): BelongsTo
    {
        return $this->belongsTo(Liability::class);
    }
}
