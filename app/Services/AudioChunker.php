<?php

namespace App\Services;

class AudioChunker
{
    /**
     * Maximum chunk size in characters.
     */
    public const CHUNK_SIZE = 4000;

    /**
     * Split text into chunks at sentence boundaries.
     *
     * @return array<string>
     */
    public function chunk(string $text): array
    {
        if (strlen($text) <= self::CHUNK_SIZE) {
            return [$text];
        }

        $chunks = [];
        $remaining = $text;

        while (strlen($remaining) > 0) {
            if (strlen($remaining) <= self::CHUNK_SIZE) {
                $chunks[] = $remaining;

                break;
            }

            $chunk = substr($remaining, 0, self::CHUNK_SIZE);

            // Find last sentence boundary within chunk size
            $lastPeriod = strrpos($chunk, '. ');
            $lastQuestion = strrpos($chunk, '? ');
            $lastExclaim = strrpos($chunk, '! ');

            $breakPoint = max(
                $lastPeriod !== false ? $lastPeriod : 0,
                $lastQuestion !== false ? $lastQuestion : 0,
                $lastExclaim !== false ? $lastExclaim : 0
            );

            if ($breakPoint === 0 || $breakPoint < self::CHUNK_SIZE * 0.5) {
                // No good sentence boundary, break at space
                $lastSpace = strrpos($chunk, ' ');
                $breakPoint = $lastSpace !== false ? $lastSpace : self::CHUNK_SIZE;
            } else {
                $breakPoint += 2; // Include punctuation and space
            }

            $chunks[] = trim(substr($remaining, 0, $breakPoint));
            $remaining = trim(substr($remaining, $breakPoint));
        }

        return $chunks;
    }
}
