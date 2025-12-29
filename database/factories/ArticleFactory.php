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
            'source_type' => 'article',
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'audio_url' => null,
        ];
    }

    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'url' => 'https://www.youtube.com/watch?v='.fake()->regexify('[a-zA-Z0-9_-]{11}'),
            'source_type' => 'youtube',
            'body' => null,
        ]);
    }
}
