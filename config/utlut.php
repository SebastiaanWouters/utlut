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
        'model' => env('EXTRACTOR_MODEL', 'google/gemini-2.5-flash'),
        'timeout' => env('EXTRACTOR_TIMEOUT', 30),
        'temperature' => env('EXTRACTOR_TEMPERATURE', 0.1),
        'max_tokens' => env('EXTRACTOR_MAX_TOKENS', 8000),
        'url_timeout' => env('EXTRACTOR_URL_TIMEOUT', 20),
        'max_retries' => env('EXTRACTOR_MAX_RETRIES', 2),
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
