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
        'max_length' => env('EXTRACTOR_MAX_LENGTH', 15000),
        'model' => env('EXTRACTOR_MODEL', 'gpt-5-mini-2025-08-07:free'),
        'timeout' => env('EXTRACTOR_TIMEOUT', 120),
        'temperature' => env('EXTRACTOR_TEMPERATURE', 0.1),
        'max_tokens' => env('EXTRACTOR_MAX_TOKENS', 8000),
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
