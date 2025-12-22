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

        /** @var ArticleAudio $audioRecord */
        $audioRecord = $this->article->audio()->firstOrCreate([], [
            'status' => 'pending',
            'voice' => 'default',
        ]);

        $fileName = "audio/{$this->article->id}.mp3";

        // Idempotency check: if already ready and file exists, don't re-process
        if ($audioRecord->status === 'ready' && Storage::disk('public')->exists($fileName)) {
            return;
        }

        $audioRecord->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        try {
            $audioContents = $tts->generate($this->article->body);

            Storage::disk('public')->put($fileName, $audioContents);

            $this->article->update([
                'audio_url' => Storage::disk('public')->url($fileName),
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
