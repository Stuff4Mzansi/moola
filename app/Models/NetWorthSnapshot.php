<?php

namespace App\Models;

use Database\Factories\NetWorthSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['date', 'assets_cents', 'debts_cents', 'net_worth_cents', 'details'])]
class NetWorthSnapshot extends Model
{
    /** @use HasFactory<NetWorthSnapshotFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'assets_cents' => 'integer', 'debts_cents' => 'integer', 'net_worth_cents' => 'integer', 'details' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
