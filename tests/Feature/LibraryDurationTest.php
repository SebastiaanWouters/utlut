<?php

use App\Models\Article;
use App\Models\ArticleAudio;
use App\Models\DeviceToken;
use App\Models\User;

test('library displays duration for articles with audio', function () {
    $user = User::factory()->create();
    $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);

    // Article with duration
    $article1 = Article::factory()->create([
        'device_token_id' => $deviceToken->id,
        'title' => 'Article with Duration',
        'url' => 'https://example.com/article1',
        'extraction_status' => 'ready',
        'audio_url' => 'https://example.com/audio1.mp3',
    ]);
    ArticleAudio::factory()->create([
        'article_id' => $article1->id,
        'duration_seconds' => 345,
        'status' => 'ready',
    ]);

    // Article without duration
    $article2 = Article::factory()->create([
        'device_token_id' => $deviceToken->id,
        'title' => 'Article Processing',
        'url' => 'https://example.com/article2',
        'extraction_status' => 'ready',
    ]);
    ArticleAudio::factory()->create([
        'article_id' => $article2->id,
        'status' => 'pending',
        'progress_percent' => 45,
    ]);

    // Article with long duration
    $article3 = Article::factory()->create([
        'device_token_id' => $deviceToken->id,
        'title' => 'Long Article',
        'url' => 'https://example.com/article3',
        'extraction_status' => 'ready',
        'audio_url' => 'https://example.com/audio3.mp3',
    ]);
    ArticleAudio::factory()->create([
        'article_id' => $article3->id,
        'duration_seconds' => 1245,
        'status' => 'ready',
    ]);

    $response = $this
        ->actingAs($user)
        ->get('/library')
        ->assertStatus(200);

    // Verify durations are displayed correctly
    $response->assertSee('5:45'); // 345 seconds
    $response->assertSee('20:45'); // 1245 seconds
    $response->assertSeeText('Article with Duration');
    $response->assertSeeText('Long Article');
});

test('library formats duration correctly', function () {
    $user = User::factory()->create();
    $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);

    $article = Article::factory()->create([
        'device_token_id' => $deviceToken->id,
        'title' => 'Test Article',
        'url' => 'https://example.com/test',
        'extraction_status' => 'ready',
        'audio_url' => 'https://example.com/test.mp3',
    ]);

    ArticleAudio::factory()->create([
        'article_id' => $article->id,
        'duration_seconds' => 125,
        'status' => 'ready',
    ]);

    $response = $this
        ->actingAs($user)
        ->get('/library')
        ->assertStatus(200);

    $response->assertSee('2:05'); // 2 minutes 5 seconds
});
