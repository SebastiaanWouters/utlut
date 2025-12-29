<?php

use App\Services\YouTubeUrlParser;

beforeEach(function () {
    $this->parser = new YouTubeUrlParser;
});

it('detects valid youtube.com watch URLs', function () {
    expect($this->parser->isYouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))->toBeTrue();
    expect($this->parser->isYouTubeUrl('https://youtube.com/watch?v=dQw4w9WgXcQ'))->toBeTrue();
    expect($this->parser->isYouTubeUrl('http://www.youtube.com/watch?v=dQw4w9WgXcQ'))->toBeTrue();
});

it('detects valid youtu.be short URLs', function () {
    expect($this->parser->isYouTubeUrl('https://youtu.be/dQw4w9WgXcQ'))->toBeTrue();
    expect($this->parser->isYouTubeUrl('http://youtu.be/dQw4w9WgXcQ'))->toBeTrue();
});

it('detects valid youtube shorts URLs', function () {
    expect($this->parser->isYouTubeUrl('https://www.youtube.com/shorts/dQw4w9WgXcQ'))->toBeTrue();
    expect($this->parser->isYouTubeUrl('https://youtube.com/shorts/dQw4w9WgXcQ'))->toBeTrue();
});

it('detects valid youtube embed URLs', function () {
    expect($this->parser->isYouTubeUrl('https://www.youtube.com/embed/dQw4w9WgXcQ'))->toBeTrue();
});

it('detects valid youtube live URLs', function () {
    expect($this->parser->isYouTubeUrl('https://www.youtube.com/live/dQw4w9WgXcQ'))->toBeTrue();
});

it('detects valid music.youtube.com URLs', function () {
    expect($this->parser->isYouTubeUrl('https://music.youtube.com/watch?v=dQw4w9WgXcQ'))->toBeTrue();
});

it('rejects non-youtube URLs', function () {
    expect($this->parser->isYouTubeUrl('https://example.com/video'))->toBeFalse();
    expect($this->parser->isYouTubeUrl('https://vimeo.com/123456'))->toBeFalse();
    expect($this->parser->isYouTubeUrl('https://google.com'))->toBeFalse();
});

it('rejects youtube URLs without valid video ID', function () {
    expect($this->parser->isYouTubeUrl('https://www.youtube.com/watch'))->toBeFalse();
    expect($this->parser->isYouTubeUrl('https://www.youtube.com/'))->toBeFalse();
});

it('extracts video ID from various URL formats', function () {
    expect($this->parser->extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ');
    expect($this->parser->extractVideoId('https://youtu.be/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ');
    expect($this->parser->extractVideoId('https://www.youtube.com/shorts/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ');
    expect($this->parser->extractVideoId('https://www.youtube.com/embed/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ');
    expect($this->parser->extractVideoId('https://music.youtube.com/watch?v=dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ');
});

it('extracts video ID from URL with extra parameters', function () {
    expect($this->parser->extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=120'))->toBe('dQw4w9WgXcQ');
    expect($this->parser->extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLrandomlist'))->toBe('dQw4w9WgXcQ');
});

it('returns null for invalid URLs', function () {
    expect($this->parser->extractVideoId('https://example.com'))->toBeNull();
    expect($this->parser->extractVideoId('not a url'))->toBeNull();
});

it('normalizes URLs to canonical format', function () {
    expect($this->parser->normalize('https://youtu.be/dQw4w9WgXcQ'))
        ->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($this->parser->normalize('https://www.youtube.com/shorts/dQw4w9WgXcQ'))
        ->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($this->parser->normalize('https://music.youtube.com/watch?v=dQw4w9WgXcQ'))
        ->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

it('returns null when normalizing invalid URLs', function () {
    expect($this->parser->normalize('https://example.com'))->toBeNull();
});

it('accepts raw video ID as input', function () {
    expect($this->parser->isYouTubeUrl('dQw4w9WgXcQ'))->toBeTrue();
    expect($this->parser->extractVideoId('dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ');
    expect($this->parser->normalize('dQw4w9WgXcQ'))->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

it('rejects video IDs with wrong length', function () {
    expect($this->parser->isYouTubeUrl('short'))->toBeFalse();
    expect($this->parser->isYouTubeUrl('waytoolongtobevalid'))->toBeFalse();
});
