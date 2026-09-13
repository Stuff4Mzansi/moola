<?php

namespace App\Domain\Identity\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_normalized',
        'password',
        'is_enabled',
        'must_change_password',
        'is_administrator',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_memberships')
            ->withPivot(['joined_at', 'removed_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HouseholdMembership::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'is_administrator' => 'boolean',
        ];
    }
}
