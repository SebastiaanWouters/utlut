<?php

namespace App\Services;

use App\Models\ArticleAudio;

class AudioProgressEstimator
{
    /**
     * Average characters processed per second by TTS API.
     */
    private const CHARS_PER_SECOND = 50;

    /**
     * Overhead for API calls, file operations (milliseconds).
     */
    private const OVERHEAD_MS = 3000;

    /**
     * Estimate the duration for audio generation in milliseconds.
     */
    public function estimateDuration(int $contentLength): int
    {
        $processingTimeMs = ($contentLength / self::CHARS_PER_SECOND) * 1000;

        return (int) ($processingTimeMs + self::OVERHEAD_MS);
    }

    /**
     * Calculate the progress percentage based on completed chunks.
     */
    public function calculateProgress(ArticleAudio $audio): int
    {
        if ($audio->total_chunks <= 1) {
            return $audio->progress_percent ?? 0;
        }

        return (int) (($audio->completed_chunks / $audio->total_chunks) * 100);
    }

    /**
     * Calculate estimated time remaining in seconds.
     */
    public function calculateEtaSeconds(ArticleAudio $audio): ?int
    {
        if (! $audio->processing_started_at || ! $audio->estimated_duration_ms) {
            return null;
        }

        $elapsedMs = (int) abs(now()->diffInMilliseconds($audio->processing_started_at));
        $remainingMs = max(0, $audio->estimated_duration_ms - $elapsedMs);

        return (int) ($remainingMs / 1000);
    }

    /**
     * Get optimal polling interval based on ETA.
     *
     * Returns interval in milliseconds:
     * - < 5s remaining: 1000ms
     * - 5-30s remaining: 2000ms
     * - 30-60s remaining: 3000ms
     * - > 60s: 5000ms
     */
    public function getOptimalPollingInterval(ArticleAudio $audio): int
    {
        $eta = $this->calculateEtaSeconds($audio);

        if ($eta === null) {
            return 3000;
        }

        return match (true) {
            $eta < 5 => 1000,
            $eta < 30 => 2000,
            $eta < 60 => 3000,
            default => 5000,
        };
    }
}
