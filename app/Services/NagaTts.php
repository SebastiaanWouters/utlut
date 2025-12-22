<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NagaTts
{
    /**
     * Generate audio for the given text using Naga API with OpenAI TTS o4-mini (free version).
     *
     * @return string Raw audio bytes (MP3 format)
     */
    public function generate(string $text, ?string $voice = null): string
    {
        $nagaConfig = config('services.naga');
        $ttsConfig = config('utlut.tts');

        if (! $nagaConfig['key']) {
            throw new \Exception('Naga API key is not configured');
        }

        $response = Http::timeout($ttsConfig['timeout'])->withHeaders([
            'Authorization' => 'Bearer '.$nagaConfig['key'],
            'Content-Type' => 'application/json',
        ])->post($nagaConfig['url'].'/v1/audio/speech', [
            'model' => $ttsConfig['model'],
            'input' => $text,
            'voice' => $voice ?? $ttsConfig['default_voice'],
            'response_format' => 'mp3',
        ]);

        if ($response->failed()) {
            Log::error('Naga TTS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to generate audio from Naga: '.$response->body());
        }

        return $response->body();
    }
}
