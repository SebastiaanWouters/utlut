<?php

use App\Services\AudioChunker;

it('returns single chunk for short text', function () {
    $chunker = new AudioChunker;

    $chunks = $chunker->chunk('Short text.');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0])->toBe('Short text.');
});

it('splits long text at sentence boundaries', function () {
    $chunker = new AudioChunker;

    // Create text longer than chunk size
    $text = str_repeat('This is a sentence. ', 250); // ~5000 chars

    $chunks = $chunker->chunk($text);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(strlen($chunk))->toBeLessThanOrEqual(AudioChunker::CHUNK_SIZE + 50);
    }
});

it('preserves all content when chunking', function () {
    $chunker = new AudioChunker;

    $text = str_repeat('Hello world. ', 400); // ~5200 chars

    $chunks = $chunker->chunk($text);

    // Rejoin and compare (accounting for trimmed whitespace)
    $rejoined = implode(' ', $chunks);
    expect(str_word_count($rejoined))->toBe(str_word_count($text));
});

it('handles text without sentence boundaries', function () {
    $chunker = new AudioChunker;

    // Long text without periods
    $text = str_repeat('word ', 1000); // ~5000 chars

    $chunks = $chunker->chunk($text);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(strlen($chunk))->toBeLessThanOrEqual(AudioChunker::CHUNK_SIZE + 50);
    }
});

it('handles question marks as sentence boundaries', function () {
    $chunker = new AudioChunker;

    $text = str_repeat('Is this a question? ', 250);

    $chunks = $chunker->chunk($text);

    expect(count($chunks))->toBeGreaterThan(1);

    // Chunks should end at question marks when possible
    foreach ($chunks as $index => $chunk) {
        if ($index < count($chunks) - 1) {
            expect($chunk)->toEndWith('?');
        }
    }
});

it('handles exclamation marks as sentence boundaries', function () {
    $chunker = new AudioChunker;

    $text = str_repeat('What an exciting statement! ', 250);

    $chunks = $chunker->chunk($text);

    expect(count($chunks))->toBeGreaterThan(1);
});
