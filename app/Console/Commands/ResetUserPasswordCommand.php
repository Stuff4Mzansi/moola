<?php

namespace App\Console\Commands;

use App\Domain\Identity\Actions\ResetUserPassword;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class ResetUserPasswordCommand extends Command
{
    protected $signature = 'moola:user:reset-password {email : User email address}';

    protected $description = 'Issue a temporary password and invalidate a user\'s sessions';

    public function handle(ResetUserPassword $resetter): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email_normalized', $email)->first();

        if ($user === null) {
            $this->error('No user was found for that email address.');

            return self::FAILURE;
        }

        $password = $this->secret('Temporary password');
        $confirmation = $this->secret('Confirm temporary password');

        if (! is_string($password) || $password !== $confirmation) {
            $this->error('The password confirmation does not match.');

            return self::FAILURE;
        }

        try {
            $resetter->handle($user, $password);
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Password reset. The user must change it after signing in.');

        return self::SUCCESS;
    }
}
