<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TTS Configuration
    |--------------------------------------------------------------------------
    */
    'tts' => [
        'model' => env('TTS_MODEL', 'gpt-4o-mini-tts:free'),
        'timeout' => env('TTS_TIMEOUT', 120),
        'default_voice' => env('TTS_VOICE', 'alloy'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Extractor Configuration
    |--------------------------------------------------------------------------
    */
    'extractor' => [
        'max_length' => env('EXTRACTOR_MAX_LENGTH', 8000),
        'model' => env('EXTRACTOR_MODEL', 'google/gemini-3-flash-preview'),
        'timeout' => env('EXTRACTOR_TIMEOUT', 30),
        'temperature' => env('EXTRACTOR_TEMPERATURE', 0.1),
        'max_tokens' => env('EXTRACTOR_MAX_TOKENS', 8000),
        'url_timeout' => env('EXTRACTOR_URL_TIMEOUT', 20),
        'max_retries' => env('EXTRACTOR_MAX_RETRIES', 2),

        'http_headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9,nl;q=0.8',
            'Referer' => env('EXTRACTOR_REFERER', 'https://www.google.com'),
            'DNT' => '1',
            'Cache-Control' => 'no-cache',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | YouTube Audio Extraction Configuration
    |--------------------------------------------------------------------------
    */
    'youtube' => [
        'timeout' => env('YOUTUBE_TIMEOUT', 300),
        'max_duration_seconds' => env('YOUTUBE_MAX_DURATION', 7200),
        'audio_quality' => env('YOUTUBE_AUDIO_QUALITY', 0),
        'audio_format' => env('YOUTUBE_AUDIO_FORMAT', 'mp3'),
        'yt_dlp_path' => env('YOUTUBE_YT_DLP_PATH', 'yt-dlp'),
        'ffmpeg_path' => env('YOUTUBE_FFMPEG_PATH', 'ffmpeg'),
        'cookies_path' => env('YOUTUBE_COOKIES_PATH') ?: storage_path('app/cookies.txt'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Configuration
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'articles' => env('ARTICLES_PER_PAGE', 20),
    ],
];
