<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_token_id' => DeviceToken::factory(),
            'url' => fake()->url(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'audio_url' => null,
        ];
    }
}
