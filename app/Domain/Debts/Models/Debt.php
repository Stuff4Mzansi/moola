<?php

namespace App\Domain\Debts\Models;

use App\Domain\Identity\Models\Household;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory;

    protected $fillable = [
        'household_id',
        'name',
        'current_principal_minor',
        'apr_basis_points',
        'minimum_payment_minor',
        'planned_payment_minor',
        'opened_on',
        'is_archived',
    ];

    protected static function newFactory(): DebtFactory
    {
        return DebtFactory::new();
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    protected function casts(): array
    {
        return [
            'opened_on' => 'immutable_date',
            'is_archived' => 'boolean',
        ];
    }
}
