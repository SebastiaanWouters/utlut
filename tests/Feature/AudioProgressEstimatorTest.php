<?php

use App\Models\ArticleAudio;
use App\Services\AudioProgressEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('estimates duration based on content length', function () {
    $estimator = new AudioProgressEstimator;

    $duration = $estimator->estimateDuration(5000);

    // 5000 chars / 50 chars per second = 100s = 100000ms + 3000ms overhead
    expect($duration)->toBe(103000);
});

it('calculates progress from completed chunks', function () {
    $estimator = new AudioProgressEstimator;

    $audio = ArticleAudio::factory()->create([
        'total_chunks' => 4,
        'completed_chunks' => 2,
        'progress_percent' => 50,
    ]);

    $progress = $estimator->calculateProgress($audio);

    expect($progress)->toBe(50);
});

it('returns progress_percent for single chunk articles', function () {
    $estimator = new AudioProgressEstimator;

    $audio = ArticleAudio::factory()->create([
        'total_chunks' => 1,
        'completed_chunks' => 0,
        'progress_percent' => 75,
    ]);

    $progress = $estimator->calculateProgress($audio);

    expect($progress)->toBe(75);
});

it('calculates ETA in seconds', function () {
    Carbon::setTestNow('2025-01-01 12:00:10');
    $estimator = new AudioProgressEstimator;

    $audio = ArticleAudio::factory()->create([
        'processing_started_at' => Carbon::parse('2025-01-01 12:00:05'), // 5s ago
        'estimated_duration_ms' => 10000, // 10 seconds total
    ]);

    $eta = $estimator->calculateEtaSeconds($audio);

    // Started 5s ago, 10s total, so ~5s remaining
    expect($eta)->toBe(5);

    Carbon::setTestNow();
});

it('returns null ETA when processing not started', function () {
    $estimator = new AudioProgressEstimator;

    $audio = ArticleAudio::factory()->create([
        'processing_started_at' => null,
        'estimated_duration_ms' => 10000,
    ]);

    $eta = $estimator->calculateEtaSeconds($audio);

    expect($eta)->toBeNull();
});

it('returns optimal polling interval based on ETA', function () {
    Carbon::setTestNow('2025-01-01 12:00:10');
    $estimator = new AudioProgressEstimator;

    // Less than 5s remaining -> 1000ms (started 8s ago out of 10s total = 2s remaining)
    $audio = ArticleAudio::factory()->create([
        'processing_started_at' => Carbon::parse('2025-01-01 12:00:02'), // 8s ago
        'estimated_duration_ms' => 10000,
    ]);
    expect($estimator->getOptimalPollingInterval($audio))->toBe(1000);

    // 5-30s remaining -> 2000ms (started 0s ago, 15s total = 15s remaining)
    $audio2 = ArticleAudio::factory()->create([
        'processing_started_at' => Carbon::parse('2025-01-01 12:00:10'), // now
        'estimated_duration_ms' => 15000,
    ]);
    expect($estimator->getOptimalPollingInterval($audio2))->toBe(2000);

    // 30-60s remaining -> 3000ms (started 0s ago, 45s total = 45s remaining)
    $audio3 = ArticleAudio::factory()->create([
        'processing_started_at' => Carbon::parse('2025-01-01 12:00:10'),
        'estimated_duration_ms' => 45000,
    ]);
    expect($estimator->getOptimalPollingInterval($audio3))->toBe(3000);

    // > 60s remaining -> 5000ms (started 0s ago, 120s total = 120s remaining)
    $audio4 = ArticleAudio::factory()->create([
        'processing_started_at' => Carbon::parse('2025-01-01 12:00:10'),
        'estimated_duration_ms' => 120000,
    ]);
    expect($estimator->getOptimalPollingInterval($audio4))->toBe(5000);

    Carbon::setTestNow();
});

it('returns default polling interval when no ETA available', function () {
    $estimator = new AudioProgressEstimator;

    $audio = ArticleAudio::factory()->create([
        'processing_started_at' => null,
        'estimated_duration_ms' => null,
    ]);

    expect($estimator->getOptimalPollingInterval($audio))->toBe(3000);
});
