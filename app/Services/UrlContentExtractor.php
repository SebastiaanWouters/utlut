<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UrlContentExtractor
{
    /**
     * Fetch URL content and extract article title and body using DeepSeek.
     *
     * @return array{title: string, body: string}
     */
    public function extract(string $url): array
    {
        $htmlContent = $this->fetchUrl($url);
        $plainText = $this->htmlToPlainText($htmlContent);

        return $this->extractWithDeepSeek($url, $plainText);
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
     * Use DeepSeek to extract article title and body from text.
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
            Log::error('DeepSeek extraction failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to extract content with DeepSeek: '.$response->body());
        }

        $result = $response->json();
        $content = $result['choices'][0]['message']['content'] ?? '';

        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/i', '', $content);
        $content = trim($content);

        $parsed = json_decode($content, true);

        if (! is_array($parsed) || ! isset($parsed['title']) || ! isset($parsed['body'])) {
            Log::error('DeepSeek returned invalid JSON', ['content' => $content]);
            throw new \Exception('Failed to parse article content from DeepSeek response');
        }

        return [
            'title' => $parsed['title'],
            'body' => $parsed['body'],
        ];
    }
}
