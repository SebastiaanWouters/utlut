<?php

use App\Jobs\GenerateArticleAudio;
use App\Models\Article;
use App\Models\ArticleAudio;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\AudioChunker;
use App\Services\AudioProgressEstimator;
use App\Services\NagaTts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('POST /api/token issues token and stores hash', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/token', ['name' => 'My Phone']);

    $response->assertSuccessful()
        ->assertJsonStructure(['ok', 'token']);

    $token = $response->json('token');
    $hash = hash('sha256', $token);

    $this->assertDatabaseHas('device_tokens', [
        'user_id' => $user->id,
        'name' => 'My Phone',
        'token_hash' => $hash,
    ]);
});

test('POST /api/token requires a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/token', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('POST /api/save requires bearer token and stores/dedupes article', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = 'test-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
        'name' => 'Test Device',
    ]);

    $data = [
        'url' => 'https://laravel.com',
        'title' => 'Laravel',
        'body' => 'Laravel is a web application framework...',
    ];

    // No token
    $this->postJson('/api/save', $data)
        ->assertStatus(401);

    // With token
    $this->withToken($token)
        ->postJson('/api/save', $data)
        ->assertSuccessful()
        ->assertJson(['ok' => true]);

    $this->assertDatabaseHas('articles', [
        'device_token_id' => $deviceToken->id,
        'url' => 'https://laravel.com',
    ]);

    Queue::assertPushed(GenerateArticleAudio::class);

    // Deduplication
    $this->withToken($token)
        ->postJson('/api/save', $data)
        ->assertSuccessful();

    expect(Article::where('device_token_id', $deviceToken->id)->count())->toBe(1);
    Queue::assertPushed(GenerateArticleAudio::class, 2);
});

test('GET /api/articles/batch returns articles by IDs', function () {
    $user = User::factory()->create();
    $token = 'test-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
        'name' => 'Test Device',
    ]);

    $articles = Article::factory()->count(5)->create(['device_token_id' => $deviceToken->id]);
    $ids = $articles->take(3)->pluck('id')->join(',');

    $response = $this->withToken($token)
        ->getJson("/api/articles/batch?ids={$ids}");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');

    // Test with preserve_order
    $reversedIds = $articles->take(3)->pluck('id')->reverse()->join(',');
    $response = $this->withToken($token)
        ->getJson("/api/articles/batch?ids={$reversedIds}&preserve_order=1");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');

    $responseData = $response->json('data');
    expect($responseData[0]['id'])->toBe($articles[2]->id);
    expect($responseData[1]['id'])->toBe($articles[1]->id);
    expect($responseData[2]['id'])->toBe($articles[0]->id);
});

test('POST /api/save validates URL', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = 'test-token';
    DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);

    $this->withToken($token)
        ->postJson('/api/save', ['url' => 'invalid-url', 'title' => 'T', 'body' => 'B'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);

    Queue::assertNotPushed(GenerateArticleAudio::class);
});

test('API endpoints return 401 with invalid token', function () {
    $this->withToken('invalid-token')
        ->getJson('/api/articles')
        ->assertStatus(401);
});

test('POST /api/articles/{id}/tts dispatches job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $token = 'test-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);
    $article = Article::factory()->create(['device_token_id' => $deviceToken->id]);

    $this->withToken($token)
        ->postJson("/api/articles/{$article->id}/tts")
        ->assertSuccessful()
        ->assertJson(['status' => 'pending']);

    Queue::assertPushed(GenerateArticleAudio::class, function ($job) use ($article) {
        return $job->article->id === $article->id;
    });
});

test('POST /api/articles/{id}/tts is idempotent', function () {
    Queue::fake();

    $user = User::factory()->create();
    $token = 'test-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);
    $article = Article::factory()->create(['device_token_id' => $deviceToken->id]);

    // First call
    $this->withToken($token)
        ->postJson("/api/articles/{$article->id}/tts")
        ->assertOk()
        ->assertJson(['status' => 'pending']);

    Queue::assertPushed(GenerateArticleAudio::class, 1);

    // Second call while pending
    $article->audio()->create(['status' => 'pending', 'provider' => 'test']);

    $this->withToken($token)
        ->postJson("/api/articles/{$article->id}/tts")
        ->assertOk()
        ->assertJson(['status' => 'pending']);

    Queue::assertPushed(GenerateArticleAudio::class, 1); // Should not push again
});

test('GET /api/articles/{id}/audio returns audio when ready', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $token = 'test-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
        'name' => 'Test Device',
    ]);
    $article = Article::factory()->create(['device_token_id' => $deviceToken->id]);

    $audioContent = 'mock audio content';
    Storage::disk('public')->put('audio/test.mp3', $audioContent);

    ArticleAudio::factory()->create([
        'article_id' => $article->id,
        'status' => 'ready',
        'audio_path' => 'audio/test.mp3',
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/articles/{$article->id}/audio");

    $response->assertSuccessful();
    // In local mode, it uses response()->download() which might have different headers in tests
    // but we can check the content.
    expect($response->streamedContent())->toBe($audioContent);
});

test('GET /api/articles/{id}/audio returns 409 if not ready', function () {
    $user = User::factory()->create();
    $token = 'test-token';
    $deviceToken = DeviceToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);
    $article = Article::factory()->create(['device_token_id' => $deviceToken->id]);

    $this->withToken($token)
        ->getJson("/api/articles/{$article->id}/audio")
        ->assertStatus(409)
        ->assertJson(['ok' => false, 'status' => 'pending']);
});

test('GenerateArticleAudio job updates article with audio url', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['device_token_id' => $deviceToken->id]);

    $ttsMock = Mockery::mock(NagaTts::class);
    $ttsMock->shouldReceive('generate')
        ->once()
        ->andReturn('fake audio content');

    $job = new GenerateArticleAudio($article);
    $job->handle($ttsMock, new AudioChunker, new AudioProgressEstimator);

    $this->assertDatabaseHas('article_audio', [
        'article_id' => $article->id,
        'status' => 'ready',
        'audio_path' => "audio/{$article->id}.mp3",
    ]);
});
