<?php

declare(strict_types=1);

use App\Services\UrlContentExtractor;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'laravel-openrouter.api_key' => 'test-api-key',
        'laravel-openrouter.api_endpoint' => 'https://openrouter.ai/api/v1/',
        'utlut.extractor.model' => 'google/gemini-2.5-flash-lite',
        'utlut.extractor.timeout' => 30,
        'utlut.extractor.temperature' => 0.1,
        'utlut.extractor.max_tokens' => 4096,
        'utlut.extractor.max_length' => 15000,
    ]);
});

test('parses clean json response', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body>Test content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->once()
        ->andReturn(['title' => 'Clean Title', 'body' => 'Clean body content.']);

    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Clean Title');
    expect($result['body'])->toBe('Clean body content.');
});

test('parses json wrapped in markdown code blocks', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body>Test content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->once()
        ->andReturn(['title' => 'Markdown Title', 'body' => 'Markdown body.']);

    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Markdown Title');
    expect($result['body'])->toBe('Markdown body.');
});

test('falls back to html extraction when api returns invalid json', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>Fallback Title</title></head><body><p>Some content here</p></body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->times(3)
        ->andThrow(new Exception('Invalid JSON'));

    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Fallback Title');
    expect($result['body'])->toContain('Some content here');
});

test('falls back to og:title when available', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><meta property="og:title" content="OG Title Here"><title>Regular Title</title></head><body>Content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->times(3)
        ->andThrow(new Exception('Invalid response'));

    $result = $extractor->extract('https://example.com/my-article');

    expect($result['title'])->toBe('OG Title Here');
});

test('falls back to url-based title when no html title found', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><body>Just content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->times(3)
        ->andThrow(new Exception('Empty response'));

    $result = $extractor->extract('https://example.com/my-awesome-article');

    expect($result['title'])->toBe('My Awesome Article');
});

test('retries on api failure', function () {
    $callCount = 0;

    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body>Content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->times(3)
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new Exception('Rate limit exceeded');
            }

            return ['title' => 'Success', 'body' => 'After retry.'];
        });

    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Success');
    expect($callCount)->toBe(3);
});

test('uses fallback after all retries exhausted', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>Fallback After Retries</title></head><body>Content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->times(3)
        ->andThrow(new Exception('API error'));

    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Fallback After Retries');
});

test('handles json parsing strategies', function () {
    $extractor = new UrlContentExtractor;

    // Use reflection to test the protected parseJsonResponse method
    $reflector = new ReflectionClass($extractor);
    $method = $reflector->getMethod('parseJsonResponse');
    $method->setAccessible(true);

    // Test clean JSON
    $result = $method->invoke($extractor, '{"title": "Clean", "body": "Content"}');
    expect($result['title'])->toBe('Clean');
    expect($result['body'])->toBe('Content');

    // Test markdown code blocks
    $result = $method->invoke($extractor, "```json\n{\"title\": \"Markdown\", \"body\": \"Body\"}\n```");
    expect($result['title'])->toBe('Markdown');
    expect($result['body'])->toBe('Body');

    // Test with extra text
    $result = $method->invoke($extractor, "Here is the content:\n{\"title\": \"Extra\", \"body\": \"Text\"}");
    expect($result['title'])->toBe('Extra');
    expect($result['body'])->toBe('Text');
});

test('validates extraction result', function () {
    $extractor = new UrlContentExtractor;

    $reflector = new ReflectionClass($extractor);
    $method = $reflector->getMethod('isValidExtraction');
    $method->setAccessible(true);

    // Valid extraction
    expect($method->invoke($extractor, ['title' => 'Title', 'body' => 'Body']))->toBeTrue();

    // Missing title
    expect($method->invoke($extractor, ['body' => 'Body']))->toBeFalse();

    // Missing body
    expect($method->invoke($extractor, ['title' => 'Title']))->toBeFalse();

    // Empty title
    expect($method->invoke($extractor, ['title' => '', 'body' => 'Body']))->toBeFalse();

    // Empty body
    expect($method->invoke($extractor, ['title' => 'Title', 'body' => '']))->toBeFalse();

    // Not an array
    expect($method->invoke($extractor, null))->toBeFalse();
    expect($method->invoke($extractor, 'string'))->toBeFalse();
});

test('extracts title from html fallback', function () {
    $extractor = new UrlContentExtractor;

    $reflector = new ReflectionClass($extractor);
    $method = $reflector->getMethod('extractTitleFromHtml');
    $method->setAccessible(true);

    // OG title takes priority
    $html = '<html><head><meta property="og:title" content="OG Title"><title>Title Tag</title></head></html>';
    expect($method->invoke($extractor, $html))->toBe('OG Title');

    // Title tag fallback
    $html = '<html><head><title>Title Tag</title></head></html>';
    expect($method->invoke($extractor, $html))->toBe('Title Tag');

    // H1 fallback
    $html = '<html><body><h1>H1 Title</h1></body></html>';
    expect($method->invoke($extractor, $html))->toBe('H1 Title');

    // No title found
    $html = '<html><body>No title</body></html>';
    expect($method->invoke($extractor, $html))->toBeNull();
});

test('extracts title from url', function () {
    $extractor = new UrlContentExtractor;

    $reflector = new ReflectionClass($extractor);
    $method = $reflector->getMethod('extractTitleFromUrl');
    $method->setAccessible(true);

    expect($method->invoke($extractor, 'https://example.com/my-awesome-article'))->toBe('My Awesome Article');
    expect($method->invoke($extractor, 'https://example.com/test_post.html'))->toBe('Test Post');
    expect($method->invoke($extractor, 'https://example.com/'))->toBe('Example');
});
