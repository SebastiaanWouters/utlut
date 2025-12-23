<?php

namespace App\Console\Commands;

use App\Jobs\ExtractArticleContent;
use App\Models\Article;
use Illuminate\Console\Command;

class RetryFailedExtractions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:retry-extraction
                            {--failed : Only retry articles with failed status}
                            {--stuck-for=10 : Retry articles stuck in extracting status for X minutes}
                            {--id= : Retry a specific article by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry content extraction for failed or stuck articles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Article::query();

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        } elseif ($this->option('failed')) {
            $query->where('extraction_status', 'failed');
        } else {
            $stuckMinutes = (int) $this->option('stuck-for');
            $query->where(function ($q) use ($stuckMinutes) {
                $q->where('extraction_status', 'failed')
                    ->orWhere(function ($sq) use ($stuckMinutes) {
                        $sq->where('extraction_status', 'extracting')
                            ->where('updated_at', '<', now()->subMinutes($stuckMinutes));
                    });
            });
        }

        $articles = $query->get();

        if ($articles->isEmpty()) {
            $this->info('No articles found to retry.');

            return self::SUCCESS;
        }

        $this->info("Found {$articles->count()} article(s) to retry.");

        $bar = $this->output->createProgressBar($articles->count());
        $bar->start();

        foreach ($articles as $article) {
            $article->update(['extraction_status' => 'extracting']);
            ExtractArticleContent::dispatch($article);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Extraction jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
