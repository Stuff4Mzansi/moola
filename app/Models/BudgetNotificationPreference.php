<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'budget_id', 'muted_types', 'email_enabled', 'email_after_notification_id'])]
class BudgetNotificationPreference extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['muted_types' => 'array', 'email_enabled' => 'boolean', 'email_after_notification_id' => 'integer'];
    }
}
