<?php

use App\Models\Article;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unauthenticated request fails', function () {
    $response = $this->postJson('/api/save', [
        'url' => 'https://example.com',
        'title' => 'Example',
        'body' => 'Content',
    ]);

    $response->assertStatus(401)
        ->assertJson(['ok' => false, 'error' => 'No token provided']);
});

test('invalid token fails', function () {
    $response = $this->withToken('invalid-token')->postJson('/api/save', [
        'url' => 'https://example.com',
        'title' => 'Example',
        'body' => 'Content',
    ]);

    $response->assertStatus(401)
        ->assertJson(['ok' => false, 'error' => 'Invalid token']);
});

test('valid token creates article', function () {
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
});

test('duplicate url updates existing article', function () {
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
});

test('validation errors', function () {
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
});
