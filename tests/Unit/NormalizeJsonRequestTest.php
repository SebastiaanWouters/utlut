<?php

use App\Http\Middleware\NormalizeJsonRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('passes through requests with JSON content type', function () {
    $middleware = new NormalizeJsonRequest;

    $request = Request::create('/api/save', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['url' => 'https://example.com']));

    $called = false;
    $middleware->handle($request, function ($req) use (&$called) {
        $called = true;

        return new Response('OK');
    });

    expect($called)->toBeTrue();
});

test('parses JSON body when content type is text/plain', function () {
    $middleware = new NormalizeJsonRequest;

    $request = Request::create('/api/save', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
    ], json_encode([
        'url' => 'https://example.com/article',
        'title' => 'Test Article',
        'body' => 'Article content.',
    ]));

    $middleware->handle($request, fn ($req) => new Response('OK'));

    expect($request->input('url'))->toBe('https://example.com/article')
        ->and($request->input('title'))->toBe('Test Article')
        ->and($request->input('body'))->toBe('Article content.');
});

test('parses JSON body when content type is missing', function () {
    $middleware = new NormalizeJsonRequest;

    $request = Request::create('/api/save', 'POST', [], [], [], [], json_encode([
        'url' => 'https://example.com/no-content-type',
    ]));

    $middleware->handle($request, fn ($req) => new Response('OK'));

    expect($request->input('url'))->toBe('https://example.com/no-content-type');
});

test('ignores invalid JSON body', function () {
    $middleware = new NormalizeJsonRequest;

    $request = Request::create('/api/save', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
    ], 'not valid json {{{');

    $middleware->handle($request, fn ($req) => new Response('OK'));

    expect($request->input('url'))->toBeNull();
});

test('ignores GET requests', function () {
    $middleware = new NormalizeJsonRequest;

    $request = Request::create('/api/articles', 'GET', [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
    ], json_encode(['url' => 'https://example.com']));

    $middleware->handle($request, fn ($req) => new Response('OK'));

    // GET request body should not be parsed
    expect($request->input('url'))->toBeNull();
});
