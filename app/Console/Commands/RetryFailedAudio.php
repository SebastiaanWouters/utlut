<?php

namespace App\Console\Commands;

use App\Jobs\GenerateArticleAudio;
use App\Models\ArticleAudio;
use Illuminate\Console\Command;

class RetryFailedAudio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:retry-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed audio generation jobs that are due for retry';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dueForRetry = ArticleAudio::where('status', 'failed')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->where('retry_count', '<', 3)
            ->with('article')
            ->get();

        if ($dueForRetry->isEmpty()) {
            $this->info('No audio jobs due for retry.');

            return Command::SUCCESS;
        }

        foreach ($dueForRetry as $audio) {
            $audio->update([
                'status' => 'pending',
                'next_retry_at' => null,
            ]);

            GenerateArticleAudio::dispatch(
                $audio->article,
                $audio->completed_chunks ?? 0
            );

            $this->info("Retrying audio for article {$audio->article_id}");
        }

        $this->info("Dispatched {$dueForRetry->count()} retry jobs");

        return Command::SUCCESS;
    }
}
