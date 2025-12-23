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
            str_contains($message, 'timeout') => self::NetworkTimeout,
            str_contains($message, '429') || str_contains($message, 'rate limit') => self::ApiRateLimit,
            str_contains($message, 'quota') => self::ApiQuotaExceeded,
            str_contains($message, '401') || str_contains($message, '403') => self::ApiAuthFailed,
            str_contains($message, 'too long') || str_contains($message, 'too large') => self::ContentTooLong,
            default => self::Unknown,
        };
    }
}
