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

class ExtractArticleContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(public Article $article) {}

    /**
     * Execute the job.
     */
    public function handle(UrlContentExtractor $extractor): void
    {
        if ($this->article->extraction_status === 'ready' && ! empty($this->article->body)) {
            return;
        }

        try {
            $result = $extractor->extract($this->article->url);

            $this->article->update([
                'title' => $result['title'],
                'body' => $result['body'],
                'extraction_status' => 'ready',
            ]);

            GenerateArticleAudio::dispatch($this->article);
        } catch (\Exception $e) {
            Log::error('Article extraction failed', [
                'article_id' => $this->article->id,
                'url' => $this->article->url,
                'error' => $e->getMessage(),
            ]);

            $this->article->update([
                'extraction_status' => 'failed',
            ]);

            throw $e;
        }
    }
}
