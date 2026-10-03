<?php

namespace App\Models;

use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'scope', 'include_subscriptions', 'repeat_cycle', 'anchor_date'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    public static function visibleTo(User $user): Builder
    {
        return static::query()->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)->orWhere(function (Builder $shared) use ($user): void {
                $shared->where('scope', 'household')->whereHas('members', fn (Builder $members): Builder => $members->where('users.id', $user->id));
            });
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['include_subscriptions' => 'boolean', 'anchor_date' => 'immutable_date'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<BudgetPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(BudgetPeriod::class);
    }

    /** @return HasMany<BudgetRecurringExpense, $this> */
    public function recurringExpenses(): HasMany
    {
        return $this->hasMany(BudgetRecurringExpense::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'budget_members')->withPivot('role');
    }

    public function memberRole(User $user): ?string
    {
        if ($user->id === $this->user_id) {
            return 'owner';
        }

        return $this->scope === 'household' ? $this->members()->where('users.id', $user->id)->first()?->pivot->role : null;
    }
}
