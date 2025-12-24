<?php

use App\Jobs\CleanArticleContent;
use App\Jobs\GenerateArticleAudio;
use App\Models\Article;
use App\Services\UrlContentExtractor;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

it('cleans content and updates article', function () {
    Queue::fake();

    $article = Article::factory()->create([
        'url' => 'https://example.com/article',
        'extraction_status' => 'extracting',
    ]);

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldReceive('clean')
        ->once()
        ->with('Raw content from shortcut', 'Provided Title', 'https://example.com/article')
        ->andReturn([
            'title' => 'Cleaned Title',
            'body' => 'Cleaned body content',
        ]);

    $job = new CleanArticleContent($article, 'Raw content from shortcut', 'Provided Title');
    $job->handle($mockExtractor);

    $article->refresh();

    expect($article->title)->toBe('Cleaned Title')
        ->and($article->body)->toBe('Cleaned body content')
        ->and($article->extraction_status)->toBe('ready');

    Queue::assertPushed(GenerateArticleAudio::class, fn ($job) => $job->article->id === $article->id);
});

it('marks article as failed on cleanup error', function () {
    $article = Article::factory()->create([
        'url' => 'https://example.com/article',
        'extraction_status' => 'extracting',
    ]);

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldReceive('clean')
        ->once()
        ->andThrow(new Exception('Cleanup failed'));

    $job = new CleanArticleContent($article, 'Raw content', 'Title');
    $exception = null;

    try {
        $job->handle($mockExtractor);
    } catch (Exception $e) {
        $exception = $e;
    }

    // Simulate what the queue worker does after all retries are exhausted
    $job->failed($exception);

    $article->refresh();
    expect($article->extraction_status)->toBe('failed');
});

it('skips cleanup if already ready with body', function () {
    Queue::fake();

    $article = Article::factory()->create([
        'extraction_status' => 'ready',
        'body' => 'Existing body',
    ]);

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldNotReceive('clean');

    $job = new CleanArticleContent($article, 'New content', 'New Title');
    $job->handle($mockExtractor);

    Queue::assertNotPushed(GenerateArticleAudio::class);
});

it('passes null title to extractor when not provided', function () {
    Queue::fake();

    $article = Article::factory()->create([
        'url' => 'https://example.com/article',
        'extraction_status' => 'extracting',
    ]);

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldReceive('clean')
        ->once()
        ->with('Raw content', null, 'https://example.com/article')
        ->andReturn([
            'title' => 'Extracted Title',
            'body' => 'Cleaned body',
        ]);

    $job = new CleanArticleContent($article, 'Raw content', null);
    $job->handle($mockExtractor);

    $article->refresh();
    expect($article->title)->toBe('Extracted Title');

    Queue::assertPushed(GenerateArticleAudio::class);
});
