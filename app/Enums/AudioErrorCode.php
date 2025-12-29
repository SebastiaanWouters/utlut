<?php

namespace App\Enums;

enum AudioErrorCode: string
{
    case NetworkTimeout = 'network_timeout';
    case ApiRateLimit = 'api_rate_limit';
    case ApiQuotaExceeded = 'api_quota_exceeded';
    case ApiAuthFailed = 'api_auth_failed';
    case ContentTooLong = 'content_too_long';
    case InvalidContent = 'invalid_content';
    case StorageFailed = 'storage_failed';
    case YouTubeVideoUnavailable = 'youtube_video_unavailable';
    case YouTubeAgeRestricted = 'youtube_age_restricted';
    case YouTubeCopyright = 'youtube_copyright';
    case YouTubePrivate = 'youtube_private';
    case YouTubeTimeout = 'youtube_timeout';
    case YouTubeExceedsDuration = 'youtube_exceeds_duration';
    case Unknown = 'unknown';

    /**
     * Get user-friendly error message.
     */
    public function userMessage(): string
    {
        return match ($this) {
            self::NetworkTimeout => 'Connection timed out. Will retry automatically.',
            self::ApiRateLimit => 'Service busy. Will retry in a moment.',
            self::ApiQuotaExceeded => 'Daily limit reached. Try again tomorrow.',
            self::ApiAuthFailed => 'Service configuration error. Contact support.',
            self::ContentTooLong => 'Article too long for audio generation.',
            self::InvalidContent => 'Could not process article content.',
            self::StorageFailed => 'Failed to save audio file. Will retry.',
            self::YouTubeVideoUnavailable => 'Video not found or unavailable.',
            self::YouTubeAgeRestricted => 'Video is age-restricted and cannot be downloaded.',
            self::YouTubeCopyright => 'Video unavailable due to copyright restrictions.',
            self::YouTubePrivate => 'This video is private.',
            self::YouTubeTimeout => 'Download timed out. Will retry automatically.',
            self::YouTubeExceedsDuration => 'Video exceeds maximum duration.',
            self::Unknown => 'Something went wrong. Will retry automatically.',
        };
    }

    /**
     * Check if this error is retryable.
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::NetworkTimeout,
            self::ApiRateLimit,
            self::StorageFailed,
            self::YouTubeTimeout,
            self::Unknown => true,
            default => false,
        };
    }

    /**
     * Get retry delay in seconds for this error type.
     */
    public function retryDelaySeconds(): int
    {
        return match ($this) {
            self::ApiRateLimit => 30,
            self::NetworkTimeout => 10,
            self::StorageFailed => 5,
            self::YouTubeTimeout => 20,
            default => 15,
        };
    }

    /**
     * Create error code from exception.
     */
    public static function fromException(\Exception $e): self
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'timeout') && str_contains($message, 'youtube') => self::YouTubeTimeout,
            str_contains($message, 'timeout') => self::NetworkTimeout,
            str_contains($message, 'video unavailable') || str_contains($message, 'not available') => self::YouTubeVideoUnavailable,
            str_contains($message, 'private video') => self::YouTubePrivate,
            str_contains($message, 'age-restricted') || str_contains($message, 'sign in to confirm your age') => self::YouTubeAgeRestricted,
            str_contains($message, 'copyright') => self::YouTubeCopyright,
            str_contains($message, 'exceeds maximum duration') => self::YouTubeExceedsDuration,
            str_contains($message, '429') || str_contains($message, 'rate limit') => self::ApiRateLimit,
            str_contains($message, 'quota') => self::ApiQuotaExceeded,
            str_contains($message, '401') || str_contains($message, '403') => self::ApiAuthFailed,
            str_contains($message, 'too long') || str_contains($message, 'too large') => self::ContentTooLong,
            default => self::Unknown,
        };
    }
}
