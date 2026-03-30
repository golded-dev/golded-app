<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Dataset;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dataset_id' => Dataset::factory(),
            'area_id' => Area::factory(),
            'msgno' => fake()->unique()->numberBetween(1, 9999),
            'subject' => fake()->sentence(6),
            'from_name' => fake()->name(),
            'from_address' => fake()->numerify('#:###/##'),
            'to_name' => fake()->name(),
            'to_address' => null,
            'body_text' => fake()->paragraphs(3, true),
            'attributes_raw' => 0,
            'posted_at' => fake()->dateTimeBetween('-30 years', '-25 years'),
            'is_read' => false,
            'is_marked' => false,
            'is_bookmarked' => false,
        ];
    }
}
