<?php

use Illuminate\Support\Facades\Process;

test('youtube extractor uses cookies when configured', function () {
    $cookiesPath = storage_path('app/test-cookies.txt');

    config([
        'sundo.youtube.cookies_path' => $cookiesPath,
        'sundo.youtube.timeout' => 60,
        'sundo.youtube.yt_dlp_path' => 'yt-dlp',
        'sundo.youtube.ffmpeg_path' => 'ffmpeg',
    ]);

    $testCookies = "# Netscape HTTP Cookie File\n.youtube.com	TRUE	/	FALSE	0	TEST	test_value\n";
    file_put_contents($cookiesPath, $testCookies);

    Process::fake();

    $extractor = app(\App\Services\YouTubeAudioExtractor::class);

    $extractor->getMetadata('https://www.youtube.com/watch?v=test');

    Process::assertRan(function ($command) {
        return in_array('--cookies', $command) && in_array($cookiesPath, $command);
    });

    unlink($cookiesPath);
});

test('youtube extractor works without cookies', function () {
    config([
        'sundo.youtube.cookies_path' => null,
        'sundo.youtube.timeout' => 60,
        'sundo.youtube.yt_dlp_path' => 'yt-dlp',
        'sundo.youtube.ffmpeg_path' => 'ffmpeg',
    ]);

    Process::fake();

    $extractor = app(\App\Services\YouTubeAudioExtractor::class);

    $extractor->getMetadata('https://www.youtube.com/watch?v=test');

    Process::assertRan(function ($command) {
        return ! in_array('--cookies', $command);
    });
});

test('youtube extractor handles missing cookies file gracefully', function () {
    $cookiesPath = storage_path('app/nonexistent-cookies.txt');

    config([
        'sundo.youtube.cookies_path' => $cookiesPath,
        'sundo.youtube.timeout' => 60,
        'sundo.youtube.yt_dlp_path' => 'yt-dlp',
        'sundo.youtube.ffmpeg_path' => 'ffmpeg',
    ]);

    Process::fake();

    $extractor = app(\App\Services\YouTubeAudioExtractor::class);

    $extractor->getMetadata('https://www.youtube.com/watch?v=test');

    Process::assertRan(function ($command) {
        return ! in_array('--cookies', $command);
    });
});
