<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\UrlContentExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanArticleContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Article $article,
        public string $rawContent,
        public ?string $providedTitle = null
    ) {}

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [60, 120];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CleanArticleContent job failed', [
            'article_id' => $this->article->id,
            'url' => $this->article->url,
            'error' => $exception->getMessage(),
        ]);

        $this->article->update(['extraction_status' => 'failed']);
    }

    /**
     * Execute the job.
     */
    public function handle(UrlContentExtractor $extractor): void
    {
        Log::info('CleanArticleContent job started', [
            'article_id' => $this->article->id,
            'url' => $this->article->url,
            'content_length' => strlen($this->rawContent),
            'attempt' => $this->attempts(),
        ]);

        // Skip if already processed
        if ($this->article->extraction_status === 'ready' && ! empty($this->article->body)) {
            Log::info('Article already cleaned, skipping', ['article_id' => $this->article->id]);

            return;
        }

        try {
            $result = $extractor->clean(
                $this->rawContent,
                $this->providedTitle,
                $this->article->url
            );

            $this->article->update([
                'title' => $result['title'],
                'body' => $result['body'],
                'extraction_status' => 'ready',
            ]);

            Log::info('Article content cleanup completed', [
                'article_id' => $this->article->id,
                'title' => $result['title'],
            ]);

            GenerateArticleAudio::dispatch($this->article);
        } catch (\Exception $e) {
            Log::error('Article content cleanup failed', [
                'article_id' => $this->article->id,
                'url' => $this->article->url,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            $this->article->update([
                'extraction_status' => 'failed',
            ]);

            throw $e;
        }
    }
}
