<?php

namespace App\Jobs;

use App\Enums\AudioErrorCode;
use App\Models\Article;
use App\Models\ArticleAudio;
use App\Services\AudioChunker;
use App\Services\AudioProgressEstimator;
use App\Services\NagaTts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateArticleAudio implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Article $article,
        public int $startChunk = 0
    ) {}

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->article->id;
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public function uniqueFor(): int
    {
        return 300;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Handle a job failure (called when all retries are exhausted).
     */
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('GenerateArticleAudio job failed permanently', [
            'article_id' => $this->article->id,
            'error' => $exception->getMessage(),
        ]);

        $errorCode = AudioErrorCode::fromException($exception);

        $this->article->audio?->update([
            'status' => 'failed',
            'error_code' => $errorCode->value,
            'error_message' => $exception->getMessage(),
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(
        NagaTts $tts,
        AudioChunker $chunker,
        AudioProgressEstimator $estimator
    ): void {
        if (empty($this->article->body)) {
            return;
        }

        $fullContent = $this->buildAudioContent();
        $contentHash = hash('sha256', $fullContent);
        $contentLength = strlen($fullContent);
        $disk = config('filesystems.default');

        /** @var ArticleAudio $audioRecord */
        $audioRecord = $this->article->audio()->firstOrCreate([], [
            'status' => 'pending',
            'voice' => 'default',
        ]);

        // Idempotency check
        if ($this->isAlreadyComplete($audioRecord, $contentHash, $disk)) {
            return;
        }

        $chunks = $chunker->chunk($fullContent);
        $totalChunks = count($chunks);

        // Initialize progress tracking on first attempt
        if ($this->startChunk === 0) {
            $audioRecord->update([
                'status' => 'pending',
                'content_hash' => $contentHash,
                'content_length' => $contentLength,
                'error_message' => null,
                'error_code' => null,
                'progress_percent' => 0,
                'total_chunks' => $totalChunks,
                'completed_chunks' => 0,
                'processing_started_at' => now(),
                'estimated_duration_ms' => $estimator->estimateDuration($contentLength),
            ]);
        }

        try {
            $audioContents = $this->processChunks($tts, $chunks, $audioRecord);
            $this->saveAudio($audioContents, $audioRecord, $disk);
        } catch (\Exception $e) {
            $this->handleFailure($audioRecord, $e);

            throw $e;
        }
    }

    /**
     * Build the full audio content with title announcement.
     */
    protected function buildAudioContent(): string
    {
        $title = $this->article->title ?? '';
        $body = $this->article->body;

        if (empty($title)) {
            return $body;
        }

        return "Now playing: {$title}. {$body}";
    }

    /**
     * Check if the audio has already been generated and is still valid.
     */
    protected function isAlreadyComplete(
        ArticleAudio $audioRecord,
        string $contentHash,
        string $disk
    ): bool {
        $fileName = "audio/{$this->article->id}.mp3";

        return $audioRecord->status === 'ready'
            && $audioRecord->content_hash === $contentHash
            && Storage::disk($disk)->exists($fileName);
    }

    /**
     * Process all chunks and return combined audio content.
     *
     * @param  array<string>  $chunks
     */
    protected function processChunks(
        NagaTts $tts,
        array $chunks,
        ArticleAudio $audioRecord
    ): string {
        $audioContents = '';
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            // Skip chunks that were already processed
            if ($index < $this->startChunk) {
                continue;
            }

            $audioContents .= $tts->generate($chunk);

            // Update progress after each chunk
            $completedChunks = $index + 1;
            $progressPercent = (int) (($completedChunks / $totalChunks) * 100);

            $audioRecord->update([
                'completed_chunks' => $completedChunks,
                'progress_percent' => $progressPercent,
            ]);
        }

        return $audioContents;
    }

    /**
     * Save the generated audio to storage.
     */
    protected function saveAudio(
        string $audioContents,
        ArticleAudio $audioRecord,
        string $disk
    ): void {
        $fileName = "audio/{$this->article->id}.mp3";

        try {
            Storage::disk($disk)->put($fileName, $audioContents);
        } catch (\Exception $e) {
            throw new \Exception('Storage failed: '.$e->getMessage());
        }

        $this->article->update([
            'audio_url' => Storage::disk($disk)->url($fileName),
        ]);

        $audioRecord->update([
            'status' => 'ready',
            'audio_path' => $fileName,
            'progress_percent' => 100,
            'processing_completed_at' => now(),
            'retry_count' => 0,
            'next_retry_at' => null,
            'duration_seconds' => $this->getMp3Duration($audioContents),
        ]);
    }

    /**
     * Get the duration of an MP3 file from its binary content.
     *
     * @return int Duration in seconds, or 0 if unable to determine
     */
    protected function getMp3Duration(string $mp3Data): int
    {
        $getID3 = new \getID3;
        $tempFile = tempnam(sys_get_temp_dir(), 'mp3_');

        try {
            file_put_contents($tempFile, $mp3Data);
            $info = $getID3->analyze($tempFile);

            return isset($info['playtime_seconds']) ? (int) round($info['playtime_seconds']) : 0;
        } catch (\Exception $e) {
            return 0;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Handle job failure and record error details.
     */
    protected function handleFailure(ArticleAudio $audioRecord, \Exception $e): void
    {
        $errorCode = AudioErrorCode::fromException($e);
        $retryCount = ($audioRecord->retry_count ?? 0) + 1;

        $updateData = [
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'error_code' => $errorCode->value,
            'retry_count' => $retryCount,
        ];

        // Schedule automatic retry if retryable and under max retries
        if ($errorCode->isRetryable() && $retryCount < 3) {
            $updateData['next_retry_at'] = now()->addSeconds($errorCode->retryDelaySeconds());
        }

        $audioRecord->update($updateData);
    }
}
