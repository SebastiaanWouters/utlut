<?php

use App\Jobs\ExtractArticleContent;
use App\Jobs\GenerateArticleAudio;
use App\Models\Article;
use App\Services\UrlContentExtractor;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

it('extracts content and updates article', function () {
    Queue::fake();

    $article = Article::factory()->create([
        'url' => 'https://example.com/article',
        'extraction_status' => 'extracting',
    ]);

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldReceive('extract')
        ->once()
        ->with('https://example.com/article')
        ->andReturn([
            'title' => 'Extracted Title',
            'body' => 'Extracted body content',
        ]);

    $job = new ExtractArticleContent($article);
    $job->handle($mockExtractor);

    $article->refresh();

    expect($article->title)->toBe('Extracted Title')
        ->and($article->body)->toBe('Extracted body content')
        ->and($article->extraction_status)->toBe('ready');

    Queue::assertPushed(GenerateArticleAudio::class, fn ($job) => $job->article->id === $article->id);
});

it('marks article as failed on extraction error', function () {
    $article = Article::factory()->create([
        'url' => 'https://example.com/article',
        'extraction_status' => 'extracting',
    ]);

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldReceive('extract')
        ->once()
        ->andThrow(new Exception('Extraction failed'));

    $job = new ExtractArticleContent($article);
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

it('skips extraction if already ready with body', function () {
    Queue::fake();

    $article = Article::factory()->create([
        'extraction_status' => 'ready',
        'body' => 'Existing body',
    ]);

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldNotReceive('extract');

    $job = new ExtractArticleContent($article);
    $job->handle($mockExtractor);

    Queue::assertNotPushed(GenerateArticleAudio::class);
});
