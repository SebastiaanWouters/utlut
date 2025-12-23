<?php

use App\Jobs\GenerateArticleAudio;
use App\Models\Article;
use App\Services\AudioChunker;
use App\Services\AudioProgressEstimator;
use App\Services\NagaTts;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

it('generates and stores audio for an article with title announcement', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    $article = Article::factory()->create([
        'title' => 'My Article',
        'body' => 'Test body content',
    ]);

    $mockTts = mock(NagaTts::class);
    $mockTts->shouldReceive('generate')
        ->once()
        ->with('Now playing: My Article. Test body content')
        ->andReturn('fake audio content');

    $job = new GenerateArticleAudio($article);
    $job->handle($mockTts, new AudioChunker, new AudioProgressEstimator);

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
        'title' => 'Test Title',
        'body' => 'Test body content',
    ]);

    $mockTts = mock(NagaTts::class);
    $mockTts->shouldReceive('generate')
        ->once()
        ->with('Now playing: Test Title. Test body content')
        ->andThrow(new Exception('TTS service error'));

    $job = new GenerateArticleAudio($article);

    try {
        $job->handle($mockTts, new AudioChunker, new AudioProgressEstimator);
    } catch (Exception $e) {
        // Expected
    }

    $audioRecord = $article->audio;
    expect($audioRecord->status)->toBe('failed')
        ->and($audioRecord->error_message)->toBe('TTS service error');
});

it('is idempotent and skips if already ready, file exists, and content matches', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');
    Storage::disk('public')->put('audio/1.mp3', 'existing content');

    $title = 'Test Title';
    $body = 'Test';
    $fullContent = "Now playing: {$title}. {$body}";
    $hash = hash('sha256', $fullContent);
    $article = Article::factory()->create(['id' => 1, 'title' => $title, 'body' => $body]);
    $article->audio()->create([
        'status' => 'ready',
        'audio_path' => 'audio/1.mp3',
        'content_hash' => $hash,
    ]);

    $mockTts = mock(NagaTts::class);
    $mockTts->shouldNotReceive('generate');

    $job = new GenerateArticleAudio($article);
    $job->handle($mockTts, new AudioChunker, new AudioProgressEstimator);

    expect(Storage::disk('public')->get('audio/1.mp3'))->toBe('existing content');
});

it('re-generates if content has changed', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');
    Storage::disk('public')->put('audio/1.mp3', 'old audio');

    $title = 'Article Title';
    $oldBody = 'Old Body';
    $newBody = 'New Body';
    $oldFullContent = "Now playing: {$title}. {$oldBody}";
    $newFullContent = "Now playing: {$title}. {$newBody}";
    $article = Article::factory()->create(['id' => 1, 'title' => $title, 'body' => $newBody]);
    $article->audio()->create([
        'status' => 'ready',
        'audio_path' => 'audio/1.mp3',
        'content_hash' => hash('sha256', $oldFullContent),
    ]);

    $mockTts = mock(NagaTts::class);
    $mockTts->shouldReceive('generate')
        ->once()
        ->with($newFullContent)
        ->andReturn('new audio content');

    $job = new GenerateArticleAudio($article);
    $job->handle($mockTts, new AudioChunker, new AudioProgressEstimator);

    $article->refresh();
    expect(Storage::disk('public')->get('audio/1.mp3'))->toBe('new audio content')
        ->and($article->audio->content_hash)->toBe(hash('sha256', $newFullContent));
});

it('generates audio without title announcement when title is empty', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    $article = Article::factory()->create([
        'title' => '',
        'body' => 'Just the body content',
    ]);

    $mockTts = mock(NagaTts::class);
    $mockTts->shouldReceive('generate')
        ->once()
        ->with('Just the body content')
        ->andReturn('fake audio content');

    $job = new GenerateArticleAudio($article);
    $job->handle($mockTts, new AudioChunker, new AudioProgressEstimator);

    $article->refresh();
    expect($article->audio->status)->toBe('ready');
});
