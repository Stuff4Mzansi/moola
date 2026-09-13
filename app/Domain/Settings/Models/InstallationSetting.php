<?php

namespace App\Domain\Settings\Models;

use App\Domain\Identity\Models\Household;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InstallationSetting extends Model
{
    protected $fillable = [
        'household_id',
        'currency_code',
        'locale',
        'timezone',
        'budget_start_day',
        'backup_retention_count',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
