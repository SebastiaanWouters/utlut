<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UrlContentExtractor
{
    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MS = 500;

    /**
     * Fetch URL content and extract article title and body using DeepSeek.
     *
     * @return array{title: string, body: string}
     */
    public function extract(string $url): array
    {
        $htmlContent = $this->fetchUrl($url);
        $plainText = $this->htmlToPlainText($htmlContent);

        try {
            return $this->extractWithDeepSeek($url, $plainText);
        } catch (\Exception $e) {
            Log::warning('DeepSeek extraction failed, using fallback', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackExtract($url, $htmlContent, $plainText);
        }
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
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9,nl;q=0.8',
        ])->timeout(30)->get($url);

        if ($response->failed()) {
            throw new \Exception("Failed to fetch URL: {$url}");
        }

        return $response->body();
    }

    /**
     * Convert HTML to plain text.
     */
    protected function htmlToPlainText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<nav\b[^>]*>.*?<\/nav>/is', '', $html);
        $html = preg_replace('/<footer\b[^>]*>.*?<\/footer>/is', '', $html);
        $html = preg_replace('/<header\b[^>]*>.*?<\/header>/is', '', $html);

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        $maxLength = config('utlut.extractor.max_length');
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength).'...';
        }

        return $text;
    }

    /**
     * Use DeepSeek to extract article title and body from text with retry logic.
     *
     * @return array{title: string, body: string}
     */
    protected function extractWithDeepSeek(string $url, string $text): array
    {
        $nagaConfig = config('services.naga');
        $extractorConfig = config('utlut.extractor');

        if (! $nagaConfig['key']) {
            throw new \Exception('Naga API key is not configured');
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return $this->attemptDeepSeekExtraction($url, $text, $nagaConfig, $extractorConfig);
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('DeepSeek extraction attempt failed', [
                    'attempt' => $attempt,
                    'max_retries' => self::MAX_RETRIES,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
                }
            }
        }

        throw $lastException ?? new \Exception('DeepSeek extraction failed after retries');
    }

    /**
     * Single attempt at DeepSeek extraction.
     *
     * @param  array{key: string, url: string}  $nagaConfig
     * @param  array{timeout: int, model: string, temperature: float, max_tokens: int}  $extractorConfig
     * @return array{title: string, body: string}
     */
    protected function attemptDeepSeekExtraction(string $url, string $text, array $nagaConfig, array $extractorConfig): array
    {
        $prompt = <<<PROMPT
Extract the main article content from the following webpage text. Return a JSON object with exactly two fields:
- "title": The main headline/title of the article
- "body": The main article content (only the article body text, no navigation, ads, or footer content)

If the content is not in English, keep it in the original language.
Clean up any formatting issues and make the body readable as continuous prose.

URL: {$url}

Webpage text:
{$text}

Respond ONLY with valid JSON, no markdown or other formatting.
PROMPT;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$nagaConfig['key'],
            'Content-Type' => 'application/json',
        ])->timeout($extractorConfig['timeout'])->post($nagaConfig['url'].'/v1/chat/completions', [
            'model' => $extractorConfig['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant that extracts article content from webpages. Always respond with valid JSON only.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $extractorConfig['temperature'],
            'max_tokens' => $extractorConfig['max_tokens'],
        ]);

        if ($response->failed()) {
            throw new \Exception('API request failed: '.$response->status().' - '.$response->body());
        }

        $result = $response->json();

        if (! is_array($result)) {
            throw new \Exception('API returned non-array response');
        }

        $content = $result['choices'][0]['message']['content'] ?? null;

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

        Log::error('DeepSeek returned unparseable content', [
            'content' => substr($content, 0, 500),
            'json_error' => json_last_error_msg(),
        ]);

        throw new \Exception('Failed to parse JSON from DeepSeek response: '.json_last_error_msg());
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
