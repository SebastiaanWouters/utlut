<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleAudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ArticleAudio>
 */
class ArticleAudioFactory extends Factory
{
    protected $model = ArticleAudio::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'status' => 'ready',
            'audio_path' => 'https://example.com/audio/'.fake()->uuid().'.mp3',
            'provider' => 'naga',
        ];
    }
}
