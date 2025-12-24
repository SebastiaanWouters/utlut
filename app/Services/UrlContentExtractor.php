<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

class UrlContentExtractor
{
    private const RETRY_DELAY_MS = 500;

    /**
     * Non-retryable error patterns (auth failures, rate limits, etc.)
     *
     * @var array<string>
     */
    private const NON_RETRYABLE_PATTERNS = [
        '401',
        '403',
        '429',
        'rate limit',
        'invalid api key',
        'unauthorized',
        'authentication',
        'quota exceeded',
    ];

    /**
     * Fetch URL content and extract article title and body using OpenRouter LLM.
     *
     * @return array{title: string, body: string}
     */
    public function extract(string $url): array
    {
        Log::info('Starting content extraction', ['url' => $url]);

        $htmlContent = $this->fetchUrl($url);
        $plainText = $this->htmlToPlainText($htmlContent);

        Log::info('HTML converted to plain text', [
            'url' => $url,
            'html_length' => strlen($htmlContent),
            'text_length' => strlen($plainText),
        ]);

        try {
            $result = $this->extractWithLlm($url, $plainText);
            Log::info('LLM extraction successful', ['url' => $url]);

            return $result;
        } catch (\Exception $e) {
            Log::warning('LLM extraction failed, using fallback', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackExtract($url, $htmlContent, $plainText);
        }
    }

    /**
     * Clean and structure client-provided content using OpenRouter LLM.
     * Used when content is provided directly (e.g., from iOS shortcut behind paywall).
     *
     * @return array{title: string, body: string}
     */
    public function clean(string $rawContent, ?string $providedTitle = null, ?string $url = null): array
    {
        Log::info('Starting content cleanup', [
            'url' => $url,
            'content_length' => strlen($rawContent),
            'has_title' => ! empty($providedTitle),
        ]);

        // Convert HTML to plain text if it looks like HTML
        $plainText = $this->looksLikeHtml($rawContent)
            ? $this->htmlToPlainText($rawContent)
            : $rawContent;

        try {
            $result = $this->cleanWithLlm($plainText, $providedTitle);
            Log::info('LLM cleanup successful', ['url' => $url]);

            return $result;
        } catch (\Exception $e) {
            Log::warning('LLM cleanup failed, using fallback', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            // Fallback: use provided title or extract from URL, clean body text
            return [
                'title' => $providedTitle ?: ($url ? $this->extractTitleFromUrl($url) : 'Article'),
                'body' => $this->cleanBodyText($plainText),
            ];
        }
    }

    /**
     * Check if content looks like HTML.
     */
    protected function looksLikeHtml(string $content): bool
    {
        return preg_match('/<[a-z][\s\S]*>/i', $content) === 1;
    }

    /**
     * Use OpenRouter LLM to clean and structure provided content.
     *
     * @return array{title: string, body: string}
     */
    protected function cleanWithLlm(string $text, ?string $providedTitle = null): array
    {
        $apiKey = config('laravel-openrouter.api_key');

        if (! $apiKey) {
            throw new \Exception('OpenRouter API key is not configured');
        }

        $maxRetries = config('sundo.extractor.max_retries', 2);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info('Attempting LLM cleanup', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                return $this->attemptLlmCleanup($text, $providedTitle);
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('LLM cleanup attempt failed', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldRetry($e)) {
                    throw $e;
                }

                if ($attempt < $maxRetries) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
                }
            }
        }

        throw $lastException ?? new \Exception('LLM cleanup failed after retries');
    }

    /**
     * Single attempt at LLM cleanup.
     *
     * @return array{title: string, body: string}
     */
    protected function attemptLlmCleanup(string $text, ?string $providedTitle = null): array
    {
        $extractorConfig = config('sundo.extractor');
        $timeout = $extractorConfig['timeout'] ?? 30;

        $systemPrompt = 'You are a helpful assistant that cleans and structures article content for text-to-speech. Return valid JSON with "title" and "body" fields only.';

        $titleInstruction = $providedTitle
            ? "Provided title (verify/improve if needed): \"{$providedTitle}\""
            : 'Extract the main headline/title from the content';

        $userPrompt = <<<PROMPT
Clean and structure this article content for text-to-speech conversion. Return JSON: {"title": "...", "body": "..."}

Rules:
- title: {$titleInstruction}
- body: The main article content only, cleaned up for audio narration
- Remove navigation, ads, footers, sidebars, cookie notices, subscription prompts
- Remove URLs, image captions, "Read more" links, social share buttons
- Remove author bylines ("By John Smith", "Written by...", "Author: ...")
- Remove publication dates ("December 24, 2024", "Published on...", "Updated...")
- Remove reading time indicators ("5 min read", "Reading time...")
- Remove category tags, share/comment counts, source attributions ("Reuters -", "AP -")
- Start body with the actual article content, not metadata
- Keep paragraphs readable and flowing naturally
- Keep original language if not English

Content to clean:
{$text}
PROMPT;

        $response = Prism::text()
            ->using(Provider::OpenRouter, $extractorConfig['model'])
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->withMaxTokens($extractorConfig['max_tokens'])
            ->usingTemperature($extractorConfig['temperature'])
            ->withClientOptions(['timeout' => $timeout])
            ->withProviderOptions(['response_format' => ['type' => 'json_object']])
            ->generate();

        $content = $response->text;

        if (empty($content) || ! is_string($content)) {
            throw new \Exception('API returned empty or invalid content');
        }

        return $this->parseJsonResponse($content);
    }

    /**
     * Fallback extraction using basic HTML parsing when LLM fails.
     *
     * @return array{title: string, body: string}
     */
    protected function fallbackExtract(string $url, string $html, string $plainText): array
    {
        $title = $this->extractTitleFromHtml($html) ?: $this->extractTitleFromUrl($url);
        $body = $this->cleanBodyText($plainText);

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    /**
     * Extract title from HTML using common patterns.
     */
    protected function extractTitleFromHtml(string $html): ?string
    {
        // Try og:title first
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try <title> tag
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try h1 tag
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    /**
     * Extract a title from URL as last resort.
     */
    protected function extractTitleFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $segments = array_filter(explode('/', $path));
        $lastSegment = end($segments) ?: parse_url($url, PHP_URL_HOST) ?: 'Article';

        // Clean up slug-style URLs
        $title = str_replace(['-', '_'], ' ', $lastSegment);
        $title = preg_replace('/\.[a-z]+$/i', '', $title);

        return ucwords($title);
    }

    /**
     * Clean body text for fallback.
     */
    protected function cleanBodyText(string $text): string
    {
        // Take the first substantial portion
        $maxLength = min(strlen($text), 5000);

        return substr($text, 0, $maxLength);
    }

    /**
     * Fetch raw HTML from URL.
     */
    protected function fetchUrl(string $url): string
    {
        $timeout = config('sundo.extractor.url_timeout', 30);

        Log::info('Fetching URL', ['url' => $url, 'timeout' => $timeout]);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9,nl;q=0.8',
        ])->timeout($timeout)->get($url);

        if ($response->failed()) {
            throw new \Exception("Failed to fetch URL: {$url} (status: {$response->status()})");
        }

        Log::info('URL fetched successfully', ['url' => $url, 'size' => strlen($response->body())]);

        return $response->body();
    }

    /**
     * Convert HTML to plain text, extracting only relevant content.
     */
    protected function htmlToPlainText(string $html): string
    {
        // Extract only body content first
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $html = $matches[1];
        }

        // Remove non-content elements
        $removePatterns = [
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<style\b[^>]*>.*?<\/style>/is',
            '/<nav\b[^>]*>.*?<\/nav>/is',
            '/<footer\b[^>]*>.*?<\/footer>/is',
            '/<header\b[^>]*>.*?<\/header>/is',
            '/<aside\b[^>]*>.*?<\/aside>/is',
            '/<noscript\b[^>]*>.*?<\/noscript>/is',
            '/<iframe\b[^>]*>.*?<\/iframe>/is',
            '/<form\b[^>]*>.*?<\/form>/is',
            '/<svg\b[^>]*>.*?<\/svg>/is',
            // Remove common ad/sidebar/comment containers by class
            '/<[^>]+class="[^"]*(?:sidebar|comment|advertisement|ad-|social-share|related-posts|newsletter)[^"]*"[^>]*>.*?<\/[^>]+>/is',
            // Remove elements with common non-content IDs
            '/<[^>]+id="[^"]*(?:sidebar|comments|advertisement|footer|header|nav)[^"]*"[^>]*>.*?<\/[^>]+>/is',
        ];

        foreach ($removePatterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Use smaller max length since we're now extracting cleaner content
        $maxLength = min(config('sundo.extractor.max_length'), 8000);
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength).'...';
        }

        return $text;
    }

    /**
     * Use OpenRouter LLM to extract article title and body from text with retry logic.
     *
     * @return array{title: string, body: string}
     */
    protected function extractWithLlm(string $url, string $text): array
    {
        $apiKey = config('laravel-openrouter.api_key');

        if (! $apiKey) {
            throw new \Exception('OpenRouter API key is not configured');
        }

        $maxRetries = config('sundo.extractor.max_retries', 2);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info('Attempting LLM extraction', [
                    'url' => $url,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                return $this->attemptLlmExtraction($url, $text);
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('LLM extraction attempt failed', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage(),
                ]);

                // Don't retry non-transient errors
                if (! $this->shouldRetry($e)) {
                    Log::warning('Error is non-retryable, stopping retries', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }

                if ($attempt < $maxRetries) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
                }
            }
        }

        throw $lastException ?? new \Exception('LLM extraction failed after retries');
    }

    /**
     * Determine if an exception is retryable (transient error).
     */
    protected function shouldRetry(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        foreach (self::NON_RETRYABLE_PATTERNS as $pattern) {
            if (str_contains($message, strtolower($pattern))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Single attempt at LLM extraction using OpenRouter via Prism.
     *
     * @return array{title: string, body: string}
     */
    protected function attemptLlmExtraction(string $url, string $text): array
    {
        $extractorConfig = config('sundo.extractor');
        $timeout = $extractorConfig['timeout'] ?? 30;

        $systemPrompt = 'You are a helpful assistant that extracts article content from webpages. Return valid JSON with "title" and "body" fields only.';

        $userPrompt = <<<PROMPT
Extract the main article content from this webpage text. Return JSON: {"title": "...", "body": "..."}

Rules:
- title: The main headline/title
- body: The article content only, cleaned for audio narration
- Remove navigation, ads, footers, sidebars
- Remove author bylines ("By John Smith", "Written by...", "Author: ...")
- Remove publication dates ("December 24, 2024", "Published on...", "Updated...")
- Remove reading time indicators ("5 min read", "Reading time...")
- Remove category tags, share/comment counts, source attributions ("Reuters -", "AP -")
- Start body with the actual article content, not metadata
- Keep original language if not English

Webpage text:
{$text}
PROMPT;

        $response = Prism::text()
            ->using(Provider::OpenRouter, $extractorConfig['model'])
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->withMaxTokens($extractorConfig['max_tokens'])
            ->usingTemperature($extractorConfig['temperature'])
            ->withClientOptions(['timeout' => $timeout])
            ->withProviderOptions(['response_format' => ['type' => 'json_object']])
            ->generate();

        $content = $response->text;

        if (empty($content) || ! is_string($content)) {
            throw new \Exception('API returned empty or invalid content');
        }

        return $this->parseJsonResponse($content);
    }

    /**
     * Parse JSON from LLM response with multiple fallback strategies.
     *
     * @return array{title: string, body: string}
     */
    protected function parseJsonResponse(string $content): array
    {
        // Strategy 1: Direct parse (ideal case)
        $parsed = json_decode($content, true);
        if ($this->isValidExtraction($parsed)) {
            return $this->normalizeExtraction($parsed);
        }

        // Strategy 2: Strip markdown code blocks (various formats)
        $cleaned = $this->stripMarkdownCodeBlocks($content);
        $parsed = json_decode($cleaned, true);
        if ($this->isValidExtraction($parsed)) {
            return $this->normalizeExtraction($parsed);
        }

        // Strategy 3: Find JSON object in content using regex
        if (preg_match('/\{[^{}]*"title"[^{}]*"body"[^{}]*\}/s', $content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($this->isValidExtraction($parsed)) {
                return $this->normalizeExtraction($parsed);
            }
        }

        // Strategy 4: Find any JSON object with nested content
        if (preg_match('/\{(?:[^{}]|(?:\{[^{}]*\}))*\}/s', $content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($this->isValidExtraction($parsed)) {
                return $this->normalizeExtraction($parsed);
            }
        }

        Log::error('LLM returned unparseable content', [
            'content' => substr($content, 0, 500),
            'json_error' => json_last_error_msg(),
        ]);

        throw new \Exception('Failed to parse JSON from LLM response: '.json_last_error_msg());
    }

    /**
     * Strip various markdown code block formats.
     */
    protected function stripMarkdownCodeBlocks(string $content): string
    {
        $content = trim($content);

        // Remove ```json or ``` blocks
        $content = preg_replace('/^```(?:json|JSON)?\s*/m', '', $content);
        $content = preg_replace('/\s*```$/m', '', $content);

        // Remove leading/trailing whitespace and newlines
        return trim($content);
    }

    /**
     * Check if parsed data is a valid extraction result.
     *
     * @param  mixed  $parsed
     */
    protected function isValidExtraction($parsed): bool
    {
        return is_array($parsed)
            && isset($parsed['title'])
            && isset($parsed['body'])
            && is_string($parsed['title'])
            && is_string($parsed['body'])
            && strlen($parsed['title']) > 0
            && strlen($parsed['body']) > 0;
    }

    /**
     * Normalize extraction result.
     *
     * @param  array{title: mixed, body: mixed}  $parsed
     * @return array{title: string, body: string}
     */
    protected function normalizeExtraction(array $parsed): array
    {
        return [
            'title' => trim((string) $parsed['title']),
            'body' => trim((string) $parsed['body']),
        ];
    }
}
