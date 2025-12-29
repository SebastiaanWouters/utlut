<?php

use App\Jobs\ExtractArticleContent;
use App\Jobs\GenerateArticleAudio;
use App\Models\Article;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('unauthenticated request fails', function () {
    Queue::fake();
    $response = $this->postJson('/api/save', [
        'url' => 'https://example.com',
    ]);

    $response->assertStatus(401)
        ->assertJson(['ok' => false, 'error' => 'No token provided']);

    Queue::assertNotPushed(ExtractArticleContent::class);
    Queue::assertNotPushed(GenerateArticleAudio::class);
});

test('invalid token fails', function () {
    Queue::fake();
    $response = $this->withToken('invalid-token')->postJson('/api/save', [
        'url' => 'https://example.com',
    ]);

    $response->assertStatus(401)
        ->assertJson(['ok' => false, 'error' => 'Invalid token']);

    Queue::assertNotPushed(ExtractArticleContent::class);
    Queue::assertNotPushed(GenerateArticleAudio::class);
});

test('url only request triggers content extraction', function () {
    Queue::fake();

    $user = User::factory()->create();
    $token = 'valid-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);

    $response = $this->withToken($token)->postJson('/api/save', [
        'url' => 'https://example.com/article-to-extract',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'title' => 'https://example.com/article-to-extract',
            'extraction_status' => 'extracting',
        ])
        ->assertJsonStructure(['id', 'ok', 'title', 'extraction_status']);

    $this->assertDatabaseHas('articles', [
        'device_token_id' => $deviceToken->id,
        'url' => 'https://example.com/article-to-extract',
        'extraction_status' => 'extracting',
    ]);

    Queue::assertPushed(ExtractArticleContent::class);
    Queue::assertNotPushed(GenerateArticleAudio::class);
});

test('duplicate url while extracting does not dispatch another job', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = 'valid-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);

    // Article already exists and is currently extracting
    $article = Article::create([
        'device_token_id' => $deviceToken->id,
        'url' => 'https://example.com/extracting',
        'extraction_status' => 'extracting',
    ]);

    // User submits same URL again (e.g., via iOS shortcut)
    $response = $this->withToken($token)->postJson('/api/save', [
        'url' => 'https://example.com/extracting',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'id' => $article->id,
            'extraction_status' => 'extracting',
        ]);

    $this->assertDatabaseCount('articles', 1);

    // Should NOT dispatch another job since one is already running
    Queue::assertNotPushed(ExtractArticleContent::class);
    Queue::assertNotPushed(GenerateArticleAudio::class);
});

test('validation errors', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = 'valid-token';
    DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);

    $response = $this->withToken($token)->postJson('/api/save', [
        'url' => 'not-a-url',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['url']);

    Queue::assertNotPushed(ExtractArticleContent::class);
    Queue::assertNotPushed(GenerateArticleAudio::class);
});
