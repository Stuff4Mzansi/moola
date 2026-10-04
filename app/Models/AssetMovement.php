<?php

namespace App\Models;

use Database\Factories\AssetMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['savings_contribution_id', 'amount_cents', 'date'])]
class AssetMovement extends Model
{
    /** @use HasFactory<AssetMovementFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'date' => 'immutable_date'];
    }
}
