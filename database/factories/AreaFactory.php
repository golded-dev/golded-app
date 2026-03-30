<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dataset_id' => Dataset::factory(),
            'code' => strtoupper(fake()->lexify('??????')),
            'name' => fake()->words(3, true),
            'echoid' => strtoupper(fake()->lexify('??????')),
            'group_id' => null,
            'sort_order' => fake()->numberBetween(0, 1000),
            'message_count' => fake()->numberBetween(1, 500),
            'unread_count' => fake()->numberBetween(0, 50),
        ];
    }
}
