<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->userName() . '@' . config('blockaccess.email_domain'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_COLLECTOR,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * Compte fraîchement créé, encore sur son mot de passe temporaire.
     */
    public function pendingPasswordChange(): static
    {
        return $this->state(fn (array $attributes): array => [
            'must_change_password' => true,
        ]);
    }
}
