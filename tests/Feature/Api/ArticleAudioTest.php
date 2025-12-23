<?php

use App\Models\Article;
use App\Models\ArticleAudio;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('audio endpoint returns 409 when audio is not ready', function () {
    $user = User::factory()->create();
    $token = 'valid-token';

    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);

    $article = Article::factory()->create([
        'device_token_id' => $deviceToken->id,
    ]);

    ArticleAudio::factory()->create([
        'article_id' => $article->id,
        'status' => 'pending',
        'audio_path' => 'audio/test.mp3',
    ]);

    $response = $this->withToken($token)->get("/api/articles/{$article->id}/audio");

    $response->assertStatus(409)
        ->assertJson([
            'ok' => false,
            'status' => 'pending',
        ]);
});

test('audio endpoint downloads audio when ready and stored locally', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    $user = User::factory()->create();
    $token = 'valid-token';

    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);

    $article = Article::factory()->create([
        'device_token_id' => $deviceToken->id,
    ]);

    Storage::disk('public')->put('audio/test.mp3', 'fake-mp3');

    ArticleAudio::factory()->create([
        'article_id' => $article->id,
        'status' => 'ready',
        'audio_path' => 'audio/test.mp3',
    ]);

    $response = $this->withToken($token)->get("/api/articles/{$article->id}/audio");

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('inline;');
});
