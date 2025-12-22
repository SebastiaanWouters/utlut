<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\ArticleAudio;
use App\Services\NagaTts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateArticleAudio implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(public Article $article) {}

    /**
     * Execute the job.
     */
    public function handle(NagaTts $tts): void
    {
        if (empty($this->article->body)) {
            return;
        }

        $contentHash = hash('sha256', $this->article->body);
        $fileName = "audio/{$this->article->id}.mp3";
        $disk = app()->isProduction() ? 'cloud' : 'public';

        /** @var ArticleAudio $audioRecord */
        $audioRecord = $this->article->audio()->firstOrCreate([], [
            'status' => 'pending',
            'voice' => 'default',
        ]);

        // Idempotency check: if already ready, file exists, and content hasn't changed, don't re-process
        if (
            $audioRecord->status === 'ready' &&
            $audioRecord->content_hash === $contentHash &&
            Storage::disk($disk)->exists($fileName)
        ) {
            return;
        }

        $audioRecord->update([
            'status' => 'pending',
            'content_hash' => $contentHash,
            'error_message' => null,
        ]);

        try {
            $audioContents = $tts->generate($this->article->body);

            Storage::disk($disk)->put($fileName, $audioContents);

            $this->article->update([
                'audio_url' => Storage::disk($disk)->url($fileName),
            ]);

            $audioRecord->update([
                'status' => 'ready',
                'audio_path' => $fileName,
            ]);
        } catch (\Exception $e) {
            $audioRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
