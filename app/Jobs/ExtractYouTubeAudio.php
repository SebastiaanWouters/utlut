<?php

namespace App\Jobs;

use App\Enums\AudioErrorCode;
use App\Models\Article;
use App\Services\YouTubeAudioExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExtractYouTubeAudio implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $maxExceptions = 3;

    public function __construct(
        public Article $article
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->article->id;
    }

    public function uniqueFor(): int
    {
        return 600;
    }

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExtractYouTubeAudio job failed permanently', [
            'article_id' => $this->article->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $errorCode = AudioErrorCode::fromException($exception);

        $this->article->update(['extraction_status' => 'failed']);

        if ($this->article->audio) {
            $this->article->audio->update([
                'status' => 'failed',
                'error_message' => $errorCode->userMessage(),
                'error_code' => $errorCode->value,
            ]);
        } else {
            $this->article->audio()->create([
                'status' => 'failed',
                'error_message' => $errorCode->userMessage(),
                'error_code' => $errorCode->value,
            ]);
        }
    }

    public function handle(YouTubeAudioExtractor $extractor): void
    {
        Log::info('ExtractYouTubeAudio job started', [
            'article_id' => $this->article->id,
            'url' => $this->article->url,
        ]);

        if ($this->article->extraction_status === 'ready' && $this->article->audio_url) {
            Log::info('YouTube audio already extracted, skipping', ['article_id' => $this->article->id]);

            return;
        }

        $audioRecord = $this->article->audio()->firstOrCreate([], [
            'status' => 'pending',
            'voice' => 'youtube',
        ]);

        $audioRecord->update([
            'status' => 'pending',
            'processing_started_at' => now(),
            'progress_percent' => 0,
        ]);

        try {
            $disk = config('filesystems.default');
            $fileName = "youtube/{$this->article->id}.mp3";
            $tempPath = sys_get_temp_dir()."/yt_{$this->article->id}.mp3";

            $result = $extractor->extract($this->article->url, $tempPath);

            $audioRecord->update(['progress_percent' => 50]);

            Storage::disk($disk)->put($fileName, file_get_contents($tempPath));

            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            $this->article->update([
                'title' => $result['title'],
                'extraction_status' => 'ready',
                'audio_url' => Storage::disk($disk)->url($fileName),
            ]);

            $audioRecord->update([
                'status' => 'ready',
                'audio_path' => $fileName,
                'duration_seconds' => $result['duration_seconds'],
                'progress_percent' => 100,
                'processing_completed_at' => now(),
            ]);

            Log::info('YouTube audio extraction completed', [
                'article_id' => $this->article->id,
                'title' => $result['title'],
                'duration' => $result['duration_seconds'],
            ]);
        } catch (\Exception $e) {
            Log::error('YouTube audio extraction failed', [
                'article_id' => $this->article->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorCode = AudioErrorCode::fromException($e);

            $audioRecord->update([
                'status' => 'failed',
                'error_message' => $errorCode->userMessage(),
                'error_code' => $errorCode->value,
            ]);

            throw $e;
        }
    }
}
