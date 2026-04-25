<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->lexify('??????')),
            'name' => fake()->words(3, true),
            'echoid' => strtoupper(fake()->lexify('??????')),
            'source_type' => fake()->randomElement(['msg', 'jam', 'squish', 'hudson']),
            'area_type' => fake()->randomElement(['Net', 'EMail', 'Echo', 'News', 'Local']),
            'group_id' => null,
            'sort_order' => fake()->numberBetween(0, 1000),
            'message_count' => fake()->numberBetween(1, 500),
            'unread_count' => fake()->numberBetween(0, 50),
        ];
    }
}
