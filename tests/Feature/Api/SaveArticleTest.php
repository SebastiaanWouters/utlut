<?php

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
        'title' => 'Example',
        'body' => 'Content',
    ]);

    $response->assertStatus(401)
        ->assertJson(['ok' => false, 'error' => 'No token provided']);

    Queue::assertNotPushed(GenerateArticleAudio::class);
});

test('invalid token fails', function () {
    Queue::fake();
    $response = $this->withToken('invalid-token')->postJson('/api/save', [
        'url' => 'https://example.com',
        'title' => 'Example',
        'body' => 'Content',
    ]);

    $response->assertStatus(401)
        ->assertJson(['ok' => false, 'error' => 'Invalid token']);

    Queue::assertNotPushed(GenerateArticleAudio::class);
});

test('valid token creates article and dispatches job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $token = 'valid-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
        'name' => 'iPhone',
    ]);

    $response = $this->withToken($token)->postJson('/api/save', [
        'url' => 'https://example.com/article',
        'title' => 'Test Article',
        'body' => 'Article body content.',
    ]);

    $response->assertStatus(200)
        ->assertJson(['ok' => true, 'title' => 'Test Article']);

    $this->assertDatabaseHas('articles', [
        'device_token_id' => $deviceToken->id,
        'url' => 'https://example.com/article',
        'title' => 'Test Article',
        'body' => 'Article body content.',
    ]);

    Queue::assertPushed(GenerateArticleAudio::class);
});

test('duplicate url updates existing article', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = 'valid-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);

    $article = Article::create([
        'device_token_id' => $deviceToken->id,
        'url' => 'https://example.com/duplicate',
        'title' => 'Old Title',
        'body' => 'Old Body',
    ]);

    $response = $this->withToken($token)->postJson('/api/save', [
        'url' => 'https://example.com/duplicate',
        'title' => 'New Title',
        'body' => 'New Body',
    ]);

    $response->assertStatus(200)
        ->assertJson(['ok' => true, 'title' => 'New Title']);

    $this->assertDatabaseCount('articles', 1);
    $this->assertDatabaseHas('articles', [
        'id' => $article->id,
        'title' => 'New Title',
        'body' => 'New Body',
    ]);

    Queue::assertPushed(GenerateArticleAudio::class);
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

    Queue::assertNotPushed(GenerateArticleAudio::class);
});
