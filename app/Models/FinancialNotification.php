<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'budget_id', 'budget_period_id', 'event_key', 'type', 'title', 'message', 'tab', 'read_at', 'resolved_at', 'email_sent_at', 'email_attempts', 'email_next_attempt_at'])]
class FinancialNotification extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime', 'email_sent_at' => 'immutable_datetime', 'email_attempts' => 'integer', 'email_next_attempt_at' => 'immutable_datetime'];
    }

    public static function visibleTo(User $user): Builder
    {
        return static::query()->where('user_id', $user->id)->whereIn('budget_id', Budget::visibleTo($user)->select('id'));
    }

    public function url(): string
    {
        return route('budgets.index', ['period' => $this->budget_period_id, 'tab' => $this->tab]);
    }
}
