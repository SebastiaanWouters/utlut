<?php

declare(strict_types=1);

use App\Services\UrlContentExtractor;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.naga.key' => 'test-api-key',
        'services.naga.url' => 'https://api.test.com',
        'utlut.extractor.model' => 'deepseek-chat',
        'utlut.extractor.timeout' => 30,
        'utlut.extractor.temperature' => 0.1,
        'utlut.extractor.max_tokens' => 4096,
        'utlut.extractor.max_length' => 15000,
    ]);
});

test('parses clean json response', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body>Test content</body></html>'),
        'api.test.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => '{"title": "Clean Title", "body": "Clean body content."}',
                ],
            ]],
        ]),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Clean Title');
    expect($result['body'])->toBe('Clean body content.');
});

test('parses json wrapped in markdown code blocks', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body>Test content</body></html>'),
        'api.test.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => "```json\n{\"title\": \"Markdown Title\", \"body\": \"Markdown body.\"}\n```",
                ],
            ]],
        ]),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Markdown Title');
    expect($result['body'])->toBe('Markdown body.');
});

test('parses json with extra text before', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body>Test content</body></html>'),
        'api.test.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => "Here is the extracted content:\n{\"title\": \"Extra Text Title\", \"body\": \"Extra text body.\"}",
                ],
            ]],
        ]),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Extra Text Title');
    expect($result['body'])->toBe('Extra text body.');
});

test('falls back to html extraction when api returns invalid json', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>Fallback Title</title></head><body><p>Some content here</p></body></html>'),
        'api.test.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => 'I cannot extract the content because...',
                ],
            ]],
        ]),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Fallback Title');
    expect($result['body'])->toContain('Some content here');
});

test('falls back to og:title when available', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><meta property="og:title" content="OG Title Here"><title>Regular Title</title></head><body>Content</body></html>'),
        'api.test.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => 'Invalid response',
                ],
            ]],
        ]),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/my-article');

    expect($result['title'])->toBe('OG Title Here');
});

test('falls back to url-based title when no html title found', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><body>Just content</body></html>'),
        'api.test.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => '',
                ],
            ]],
        ]),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/my-awesome-article');

    expect($result['title'])->toBe('My Awesome Article');
});

test('retries on api failure', function () {
    $callCount = 0;

    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body>Content</body></html>'),
        'api.test.com/*' => function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                return Http::response(['error' => 'Rate limit'], 429);
            }

            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"title": "Success", "body": "After retry."}',
                    ],
                ]],
            ]);
        },
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Success');
    expect($callCount)->toBe(3);
});

test('uses fallback after all retries exhausted', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>Fallback After Retries</title></head><body>Content</body></html>'),
        'api.test.com/*' => Http::response(['error' => 'Server error'], 500),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Fallback After Retries');
});

test('handles empty choices array', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Empty Choices</title><body>Content</body></html>'),
        'api.test.com/*' => Http::response([
            'choices' => [],
        ]),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Empty Choices');
});

test('handles missing content field', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Missing Content</title><body>Body text</body></html>'),
        'api.test.com/*' => Http::response([
            'choices' => [[
                'message' => [],
            ]],
        ]),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Missing Content');
});

test('handles json with only title (missing body)', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Missing Body</title><body>Fallback body</body></html>'),
        'api.test.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => '{"title": "Only Title"}',
                ],
            ]],
        ]),
    ]);

    $extractor = new UrlContentExtractor;
    $result = $extractor->extract('https://example.com/article');

    expect($result['title'])->toBe('Missing Body');
});
