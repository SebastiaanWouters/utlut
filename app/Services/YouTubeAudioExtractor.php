<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class YouTubeAudioExtractor
{
    public function __construct(
        private YouTubeUrlParser $parser
    ) {}

    /**
     * Extract audio and metadata from a YouTube video.
     *
     * @return array{title: string, duration_seconds: int, audio_path: string}
     *
     * @throws \Exception
     */
    public function extract(string $url, string $outputPath): array
    {
        $normalizedUrl = $this->parser->normalize($url);

        if ($normalizedUrl === null) {
            throw new \Exception('Invalid YouTube URL');
        }

        $metadata = $this->getMetadata($normalizedUrl);
        $this->validateMetadata($metadata);
        $this->downloadAudio($normalizedUrl, $outputPath);

        return [
            'title' => $metadata['title'],
            'duration_seconds' => $metadata['duration_seconds'],
            'audio_path' => $outputPath,
        ];
    }

    /**
     * Get video metadata without downloading.
     *
     * @return array{title: string, duration_seconds: int}
     *
     * @throws \Exception
     */
    public function getMetadata(string $url): array
    {
        $timeout = config('sundo.youtube.timeout', 60);

        $result = Process::timeout($timeout)->run([
            'yt-dlp',
            '--dump-json',
            '--no-download',
            '--no-warnings',
            $url,
        ]);

        if (! $result->successful()) {
            $this->handleError($result->errorOutput());
        }

        $json = json_decode($result->output(), true);

        if (! $json) {
            throw new \Exception('Failed to parse YouTube metadata');
        }

        return [
            'title' => $json['title'] ?? 'Untitled',
            'duration_seconds' => (int) ($json['duration'] ?? 0),
        ];
    }

    /**
     * Download audio to the specified path.
     *
     * @throws \Exception
     */
    private function downloadAudio(string $url, string $outputPath): void
    {
        $timeout = config('sundo.youtube.timeout', 300);
        $audioQuality = config('sundo.youtube.audio_quality', 0);

        Log::info('Downloading YouTube audio', [
            'url' => $url,
            'output_path' => $outputPath,
        ]);

        $result = Process::timeout($timeout)->run([
            'yt-dlp',
            '-x',
            '--audio-format', 'mp3',
            '--audio-quality', (string) $audioQuality,
            '--no-playlist',
            '--no-warnings',
            '-o', $outputPath,
            $url,
        ]);

        if (! $result->successful()) {
            $this->handleError($result->errorOutput());
        }

        if (! file_exists($outputPath)) {
            throw new \Exception('Audio file was not created');
        }

        Log::info('YouTube audio downloaded successfully', [
            'url' => $url,
            'file_size' => filesize($outputPath),
        ]);
    }

    private function validateMetadata(array $metadata): void
    {
        $maxDuration = config('sundo.youtube.max_duration_seconds', 7200);

        if ($metadata['duration_seconds'] > $maxDuration) {
            $maxMinutes = (int) ($maxDuration / 60);
            throw new \Exception("Video exceeds maximum duration ({$maxMinutes} minutes)");
        }
    }

    private function handleError(string $errorOutput): void
    {
        $error = strtolower($errorOutput);

        if (str_contains($error, 'video unavailable') || str_contains($error, 'not available')) {
            throw new \Exception('Video not found or unavailable');
        }

        if (str_contains($error, 'private video')) {
            throw new \Exception('This video is private');
        }

        if (str_contains($error, 'age-restricted') || str_contains($error, 'sign in to confirm your age')) {
            throw new \Exception('This video is age-restricted and cannot be downloaded');
        }

        if (str_contains($error, 'copyright')) {
            throw new \Exception('This video is unavailable due to copyright restrictions');
        }

        Log::error('yt-dlp error', ['error' => $errorOutput]);
        throw new \Exception('Failed to extract audio from YouTube');
    }
}
