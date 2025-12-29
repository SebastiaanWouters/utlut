<?php

namespace App\Services;

class YouTubeUrlParser
{
    private const VIDEO_ID_PATTERN = '/^[a-zA-Z0-9_-]{11}$/';

    private const URL_PATTERNS = [
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]{11})/',
        '/(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
        '/(?:https?:\/\/)?(?:www\.)?music\.youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
    ];

    public function isYouTubeUrl(string $url): bool
    {
        return $this->extractVideoId($url) !== null;
    }

    public function extractVideoId(string $url): ?string
    {
        $url = trim($url);

        if (preg_match(self::VIDEO_ID_PATTERN, $url)) {
            return $url;
        }

        foreach (self::URL_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function normalize(string $url): ?string
    {
        $videoId = $this->extractVideoId($url);

        if ($videoId === null) {
            return null;
        }

        return "https://www.youtube.com/watch?v={$videoId}";
    }
}
