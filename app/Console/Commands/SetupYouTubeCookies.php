<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupYouTubeCookies extends Command
{
    protected $signature = 'youtube:setup-cookies';

    protected $description = 'Setup YouTube cookies from base64-encoded ENV variable';

    public function handle(): int
    {
        $cookiesB64 = env('YOUTUBE_COOKIES_B64');
        $cookiesPath = config('sundo.youtube.cookies_path', storage_path('app/cookies.txt'));

        if (empty($cookiesB64)) {
            $this->info('YOUTUBE_COOKIES_B64 not set in environment, skipping cookies setup');

            return self::SUCCESS;
        }

        $this->info('Setting up YouTube cookies...');

        // Create directory if it doesn't exist
        $directory = dirname($cookiesPath);
        if (empty($directory) || $directory === '.') {
            $directory = storage_path('app');
        }
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Decode and write cookies file
        $decodedCookies = base64_decode($cookiesB64);

        if ($decodedCookies === false) {
            $this->error('Failed to decode base64 cookies');

            return self::FAILURE;
        }

        File::put($cookiesPath, $decodedCookies);

        // Set secure permissions
        chmod($cookiesPath, 0600);

        $this->info("YouTube cookies setup complete: {$cookiesPath}");
        $this->info('Cookies file size: '.strlen($decodedCookies).' bytes');

        return self::SUCCESS;
    }
}
