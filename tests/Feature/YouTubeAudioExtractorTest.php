<?php

use App\Services\YouTubeAudioExtractor;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;

beforeEach(function () {
    Storage::fake('local');
});

it('handles youtube video unavailable error', function () {
    $extractor = app(YouTubeAudioExtractor::class);

    $reflection = new ReflectionClass($extractor);
    $method = $reflection->getMethod('handleError');
    $method->setAccessible(true);

    $method->invoke($extractor, 'ERROR: [youtube] test: Video unavailable');
})->throws(\Exception::class, 'Video not found or unavailable');

it('handles youtube age restricted error', function () {
    $extractor = app(YouTubeAudioExtractor::class);

    $reflection = new ReflectionClass($extractor);
    $method = $reflection->getMethod('handleError');
    $method->setAccessible(true);

    $method->invoke($extractor, 'ERROR: [youtube] test: Sign in to confirm your age');
})->throws(\Exception::class, 'This video is age-restricted and cannot be downloaded');

it('handles youtube authentication required error', function () {
    $extractor = app(YouTubeAudioExtractor::class);

    $reflection = new ReflectionClass($extractor);
    $method = $reflection->getMethod('handleError');
    $method->setAccessible(true);

    $method->invoke($extractor, 'ERROR: [youtube] test: Sign in to confirm you\'re not a bot');
})->throws(\Exception::class, 'Video requires authentication. Please try again later.');

it('handles youtube timeout error', function () {
    $extractor = app(YouTubeAudioExtractor::class);

    $reflection = new ReflectionClass($extractor);
    $method = $reflection->getMethod('handleError');
    $method->setAccessible(true);

    $method->invoke($extractor, 'ERROR: [youtube] test: Download timed out');
})->throws(\Exception::class, 'YouTube download timed out');

it('handles youtube private video error', function () {
    $extractor = app(YouTubeAudioExtractor::class);

    $reflection = new ReflectionClass($extractor);
    $method = $reflection->getMethod('handleError');
    $method->setAccessible(true);

    $method->invoke($extractor, 'ERROR: [youtube] test: private video');
})->throws(\Exception::class, 'This video is private');
