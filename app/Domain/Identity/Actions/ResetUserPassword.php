<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\AuditEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class ResetUserPassword
{
    public function handle(User $user, string $temporaryPassword, ?User $actor = null): void
    {
        Validator::validate(
            ['password' => $temporaryPassword],
            ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]],
        );

        DB::transaction(function () use ($user, $temporaryPassword, $actor): void {
            $user->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            AuditEvent::query()->create([
                'household_id' => $user->memberships()->whereNull('removed_at')->value('household_id'),
                'actor_user_id' => $actor?->id,
                'event_type' => 'user.password_reset',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => ['source' => $actor === null ? 'moola:user:reset-password' : 'administrator'],
                'occurred_at' => now(),
            ]);
        });
    }
}
