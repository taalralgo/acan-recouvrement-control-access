<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SaasPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaasPlatform>
 */
class SaasPlatformFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'base_url' => 'https://' . fake()->unique()->domainName(),
            'api_token' => fake()->sha256(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['active' => false]);
    }
}
