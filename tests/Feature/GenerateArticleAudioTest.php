<?php

use App\Jobs\GenerateArticleAudio;
use App\Models\Article;
use App\Services\NagaTts;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

it('generates and stores audio for an article', function () {
    Storage::fake('public');

    $article = Article::factory()->create([
        'body' => 'Test body content',
    ]);

    $mockTts = mock(NagaTts::class);
    $mockTts->shouldReceive('generate')
        ->once()
        ->with('Test body content')
        ->andReturn('fake audio content');

    $job = new GenerateArticleAudio($article);
    $job->handle($mockTts);

    $article->refresh();
    $audioRecord = $article->audio;

    expect($audioRecord->status)->toBe('ready')
        ->and($audioRecord->audio_path)->toBe("audio/{$article->id}.mp3")
        ->and(Storage::disk('public')->exists("audio/{$article->id}.mp3"))->toBeTrue()
        ->and(Storage::disk('public')->get("audio/{$article->id}.mp3"))->toBe('fake audio content')
        ->and($article->audio_url)->toBe(Storage::disk('public')->url("audio/{$article->id}.mp3"));
});

it('handles failure when tts service fails', function () {
    $article = Article::factory()->create([
        'body' => 'Test body content',
    ]);

    $mockTts = mock(NagaTts::class);
    $mockTts->shouldReceive('generate')
        ->once()
        ->andThrow(new Exception('TTS service error'));

    $job = new GenerateArticleAudio($article);

    try {
        $job->handle($mockTts);
    } catch (Exception $e) {
        // Expected
    }

    $audioRecord = $article->audio;
    expect($audioRecord->status)->toBe('failed')
        ->and($audioRecord->error_message)->toBe('TTS service error');
});

it('is idempotent and skips if already ready and file exists', function () {
    Storage::fake('public');
    Storage::disk('public')->put('audio/1.mp3', 'existing content');

    $article = Article::factory()->create(['id' => 1, 'body' => 'Test']);
    $article->audio()->create([
        'status' => 'ready',
        'audio_path' => 'audio/1.mp3',
    ]);

    $mockTts = mock(NagaTts::class);
    $mockTts->shouldNotReceive('generate');

    $job = new GenerateArticleAudio($article);
    $job->handle($mockTts);

    expect(Storage::disk('public')->get('audio/1.mp3'))->toBe('existing content');
});
