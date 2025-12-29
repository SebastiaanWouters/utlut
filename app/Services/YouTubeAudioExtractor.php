<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class YouTubeAudioExtractor
{
    private string $ytDlpPath;

    private string $ffmpegPath;

    public function __construct(
        private YouTubeUrlParser $parser
    ) {
        $this->ytDlpPath = config('sundo.youtube.yt_dlp_path', 'yt-dlp');
        $this->ffmpegPath = config('sundo.youtube.ffmpeg_path', 'ffmpeg');
        $this->checkDependencies();
    }

    private function checkDependencies(): void
    {
        $ytDlpConfig = config('sundo.youtube.yt_dlp_path');
        $ffmpegConfig = config('sundo.youtube.ffmpeg_path');

        if ($ytDlpConfig === 'yt-dlp') {
            $ytDlpPath = shell_exec('which yt-dlp');
            if ($ytDlpPath) {
                $this->ytDlpPath = trim($ytDlpPath);
                Log::info('yt-dlp found in PATH', ['path' => $this->ytDlpPath]);
            } else {
                Log::warning('yt-dlp not found in PATH', ['configured_path' => $ytDlpConfig]);
            }
        } else {
            $this->ytDlpPath = $ytDlpConfig;
            if (file_exists($this->ytDlpPath)) {
                Log::info('yt-dlp configured path exists', ['path' => $this->ytDlpPath]);
            } else {
                Log::error('yt-dlp configured path does not exist', ['path' => $this->ytDlpPath]);
            }
        }

        if ($ffmpegConfig === 'ffmpeg') {
            $ffmpegPath = shell_exec('which ffmpeg');
            if ($ffmpegPath) {
                $this->ffmpegPath = trim($ffmpegPath);
                Log::info('ffmpeg found in PATH', ['path' => $this->ffmpegPath]);
            } else {
                Log::warning('ffmpeg not found in PATH', ['configured_path' => $ffmpegConfig]);
            }
        } else {
            $this->ffmpegPath = $ffmpegConfig;
            if (file_exists($this->ffmpegPath)) {
                Log::info('ffmpeg configured path exists', ['path' => $this->ffmpegPath]);
            } else {
                Log::error('ffmpeg configured path does not exist', ['path' => $this->ffmpegPath]);
            }
        }
    }

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

        $command = [
            $this->ytDlpPath,
            '--dump-json',
            '--no-download',
            '--no-warnings',
            '--user-agent', 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            '--extractor-args', 'youtube:player_client=android',
            '--no-playlist',
            '--retries', '3',
        ];

        $cookiesPath = config('sundo.youtube.cookies_path');
        if ($cookiesPath && file_exists($cookiesPath)) {
            $command[] = '--cookies';
            $command[] = $cookiesPath;
        }

        $command[] = $url;

        $result = Process::timeout($timeout)->run($command);

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
            'yt-dlp' => $this->ytDlpPath,
        ]);

        $command = [
            $this->ytDlpPath,
            '-x',
            '--audio-format', 'mp3',
            '--audio-quality', (string) $audioQuality,
            '--no-playlist',
            '--no-warnings',
            '--user-agent', 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            '--extractor-args', 'youtube:player_client=android',
            '--retries', '3',
            '--retry-sleep', '10',
            '-o', $outputPath,
        ];

        $cookiesPath = config('sundo.youtube.cookies_path');
        if ($cookiesPath && file_exists($cookiesPath)) {
            $command[] = '--cookies';
            $command[] = $cookiesPath;
        }

        $command[] = $url;

        $result = Process::timeout($timeout)->run($command);

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
            Log::error('YouTube video unavailable', ['error' => $errorOutput]);
            throw new \Exception('Video not found or unavailable');
        }

        if (str_contains($error, 'private video')) {
            Log::error('YouTube video is private', ['error' => $errorOutput]);
            throw new \Exception('This video is private');
        }

        if (str_contains($error, 'age-restricted') || str_contains($error, 'sign in to confirm your age')) {
            Log::error('YouTube video is age-restricted', ['error' => $errorOutput]);
            throw new \Exception('This video is age-restricted and cannot be downloaded');
        }

        if (str_contains($error, 'sign in to confirm') || str_contains($error, 'sign in to confirm you\'re not a bot')) {
            Log::error('YouTube requires authentication', ['error' => $errorOutput]);
            throw new \Exception('Video requires authentication. Please try again later.');
        }

        if (str_contains($error, 'copyright')) {
            Log::error('YouTube video blocked by copyright', ['error' => $errorOutput]);
            throw new \Exception('This video is unavailable due to copyright restrictions');
        }

        if (str_contains($error, 'timeout') || str_contains($error, 'timed out')) {
            Log::error('YouTube download timeout', ['error' => $errorOutput]);
            throw new \Exception('YouTube download timed out');
        }

        Log::error('yt-dlp error', ['error' => $errorOutput]);
        throw new \Exception('Failed to extract audio from YouTube');
    }
}
