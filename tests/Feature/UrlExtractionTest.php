<?php

use App\Jobs\ExtractArticleContent;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('add from url modal displays for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('library'))
        ->assertOk()
        ->assertSee('Add URL');
});

test('add from url validates url is required', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('app.library')
        ->set('addUrl', '')
        ->call('addFromUrl')
        ->assertHasErrors(['addUrl' => 'required']);
});

test('add from url validates url format', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('app.library')
        ->set('addUrl', 'not-a-url')
        ->call('addFromUrl')
        ->assertHasErrors(['addUrl' => 'url']);
});

test('add from url creates article and dispatches extraction job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $url = 'https://example.com/test-article';

    Volt::actingAs($user)
        ->test('app.library')
        ->set('addUrl', $url)
        ->call('addFromUrl')
        ->assertHasNoErrors()
        ->assertSet('addUrl', '');

    $this->assertDatabaseHas('articles', [
        'url' => $url,
        'extraction_status' => 'extracting',
    ]);

    Queue::assertPushed(ExtractArticleContent::class, fn ($job) => $job->article->url === $url);
});

test('add from url updates existing article and re-dispatches extraction job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $deviceToken = $user->deviceTokens()->create([
        'name' => 'Web',
        'token' => 'test-token',
        'token_hash' => hash('sha256', 'test-token'),
    ]);

    $url = 'https://example.com/existing-article';
    $article = Article::create([
        'device_token_id' => $deviceToken->id,
        'url' => $url,
        'title' => 'Old Title',
        'body' => 'Old body content.',
        'extraction_status' => 'ready',
    ]);

    Volt::actingAs($user)
        ->test('app.library')
        ->set('addUrl', $url)
        ->call('addFromUrl')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('articles', 1);
    $this->assertDatabaseHas('articles', [
        'id' => $article->id,
        'extraction_status' => 'extracting',
    ]);

    Queue::assertPushed(ExtractArticleContent::class);
});

test('add from url creates device token if none exists', function () {
    Queue::fake();

    $user = User::factory()->create();
    $url = 'https://example.com/new-article';

    $this->assertDatabaseMissing('device_tokens', ['user_id' => $user->id]);

    Volt::actingAs($user)
        ->test('app.library')
        ->set('addUrl', $url)
        ->call('addFromUrl')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('device_tokens', [
        'user_id' => $user->id,
        'name' => 'Web',
    ]);

    $this->assertDatabaseHas('articles', [
        'url' => $url,
        'extraction_status' => 'extracting',
    ]);

    Queue::assertPushed(ExtractArticleContent::class);
});

test('retry extraction dispatches new extraction job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $deviceToken = $user->deviceTokens()->create([
        'name' => 'Web',
        'token' => 'test-token',
        'token_hash' => hash('sha256', 'test-token'),
    ]);

    $article = Article::create([
        'device_token_id' => $deviceToken->id,
        'url' => 'https://example.com/failed-article',
        'extraction_status' => 'failed',
    ]);

    Volt::actingAs($user)
        ->test('app.library')
        ->call('retryExtraction', $article);

    $article->refresh();
    expect($article->extraction_status)->toBe('extracting');

    Queue::assertPushed(ExtractArticleContent::class, fn ($job) => $job->article->id === $article->id);
});
