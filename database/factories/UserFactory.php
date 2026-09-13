<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => $email = fake()->unique()->safeEmail(),
            'email_normalized' => mb_strtolower(trim($email)),
            'password' => static::$password ??= Hash::make(fake()->password(24, 32)),
            'is_enabled' => true,
            'must_change_password' => true,
            'is_administrator' => false,
        ];
    }

    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_administrator' => true,
        ]);
    }
}
