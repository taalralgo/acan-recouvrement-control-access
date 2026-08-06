<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Groupe;
use App\Models\SaasPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Groupe>
 */
class GroupeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_id' => SaasPlatform::factory(),
            'external_id' => fake()->unique()->numberBetween(1, 100000),
            'code' => strtoupper(fake()->unique()->lexify('grp???')),
            'name' => fake()->unique()->company(),
            'lang' => 'fr',
            'users_count' => fake()->numberBetween(1, 30),
            'is_blocked' => false,
            'platform_enabled' => true,
            'synced_at' => now(),
        ];
    }

    public function blocked(string $reason = 'Facture impayée.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => $reason,
        ]);
    }
}
