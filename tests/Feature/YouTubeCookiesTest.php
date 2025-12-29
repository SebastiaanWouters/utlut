<?php

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

    $this->assertFileExists($cookiesPath);

    unlink($cookiesPath);
});

test('youtube extractor works without cookies', function () {
    config([
        'sundo.youtube.cookies_path' => null,
        'sundo.youtube.timeout' => 60,
        'sundo.youtube.yt_dlp_path' => 'yt-dlp',
        'sundo.youtube.ffmpeg_path' => 'ffmpeg',
    ]);

    $this->assertNull(config('sundo.youtube.cookies_path'));
});

test('youtube extractor handles missing cookies file gracefully', function () {
    $cookiesPath = storage_path('app/nonexistent-cookies.txt');

    config([
        'sundo.youtube.cookies_path' => $cookiesPath,
        'sundo.youtube.timeout' => 60,
        'sundo.youtube.yt_dlp_path' => 'yt-dlp',
        'sundo.youtube.ffmpeg_path' => 'ffmpeg',
    ]);

    $this->assertFileDoesNotExist($cookiesPath);
});
