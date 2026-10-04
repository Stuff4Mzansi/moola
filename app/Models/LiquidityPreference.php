<?php

namespace App\Models;

use Database\Factories\LiquidityPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['budget_ids', 'horizon', 'buffer_cents', 'essential_cents', 'variable_cents', 'income_first'])]
class LiquidityPreference extends Model
{
    /** @use HasFactory<LiquidityPreferenceFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['budget_ids' => 'array', 'horizon' => 'integer', 'buffer_cents' => 'integer', 'essential_cents' => 'integer', 'variable_cents' => 'integer', 'income_first' => 'boolean'];
    }
}
