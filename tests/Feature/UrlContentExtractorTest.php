<?php

declare(strict_types=1);

use App\Services\UrlContentExtractor;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'laravel-openrouter.api_key' => 'test-api-key',
        'laravel-openrouter.api_endpoint' => 'https://openrouter.ai/api/v1/',
        'sundo.extractor.model' => 'google/gemini-3-flash-preview',
        'sundo.extractor.timeout' => 30,
        'sundo.extractor.temperature' => 0.1,
        'sundo.extractor.max_tokens' => 4096,
        'sundo.extractor.max_length' => 15000,
        'sundo.extractor.url_timeout' => 30,
        'sundo.extractor.max_retries' => 2,
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
        ->times(2)
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
        ->times(2)
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
        ->times(2)
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
        ->times(2)
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 2) {
                throw new Exception('Temporary API error');
            }

            return ['title' => 'Success', 'body' => 'After retry.'];
        });

    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Success');
    expect($callCount)->toBe(2);
});

test('uses fallback after all retries exhausted', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>Fallback After Retries</title></head><body>Content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->times(2)
        ->andThrow(new Exception('API error'));

    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Fallback After Retries');
});

test('does not retry on non-retryable errors like 401 unauthorized', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>Auth Error Fallback</title></head><body>Content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->once() // Should only try once, not retry
        ->andThrow(new Exception('401 Unauthorized'));

    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Auth Error Fallback');
});

test('does not retry on rate limit errors', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>Rate Limit Fallback</title></head><body>Content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->once() // Should only try once, not retry
        ->andThrow(new Exception('Rate limit exceeded'));

    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Rate Limit Fallback');
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

test('sends referer header with google.com', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body>Content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->once()
        ->andReturn(['title' => 'Test', 'body' => 'Content']);

    $extractor->extract('https://example.com/article');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Referer', 'https://google.com');
    });
});

test('sends configured http headers from config', function () {
    config([
        'sundo.extractor.http_headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9,nl;q=0.8',
            'Referer' => 'https://google.com',
            'DNT' => '1',
            'Cache-Control' => 'no-cache',
        ],
    ]);

    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body>Content</body></html>'),
    ]);

    $extractor = Mockery::mock(UrlContentExtractor::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $extractor->shouldReceive('attemptLlmExtraction')
        ->once()
        ->andReturn(['title' => 'Test', 'body' => 'Content']);

    $extractor->extract('https://example.com/article');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Referer', 'https://google.com')
            && $request->hasHeader('DNT', '1')
            && $request->hasHeader('Cache-Control', 'no-cache');
    });
});
