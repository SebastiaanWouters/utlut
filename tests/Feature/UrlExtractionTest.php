<?php

use App\Jobs\GenerateArticleAudio;
use App\Models\Article;
use App\Models\User;
use App\Services\UrlContentExtractor;
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

test('add from url creates article and dispatches audio job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $url = 'https://example.com/test-article';

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldReceive('extract')
        ->once()
        ->with($url)
        ->andReturn([
            'title' => 'Test Article Title',
            'body' => 'This is the article body content.',
        ]);

    app()->instance(UrlContentExtractor::class, $mockExtractor);

    Volt::actingAs($user)
        ->test('app.library')
        ->set('addUrl', $url)
        ->call('addFromUrl')
        ->assertHasNoErrors()
        ->assertSet('addUrl', '');

    $this->assertDatabaseHas('articles', [
        'url' => $url,
        'title' => 'Test Article Title',
        'body' => 'This is the article body content.',
    ]);

    Queue::assertPushed(GenerateArticleAudio::class);
});

test('add from url updates existing article with same url', function () {
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
    ]);

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldReceive('extract')
        ->once()
        ->with($url)
        ->andReturn([
            'title' => 'New Title',
            'body' => 'New body content.',
        ]);

    app()->instance(UrlContentExtractor::class, $mockExtractor);

    Volt::actingAs($user)
        ->test('app.library')
        ->set('addUrl', $url)
        ->call('addFromUrl')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('articles', 1);
    $this->assertDatabaseHas('articles', [
        'id' => $article->id,
        'title' => 'New Title',
        'body' => 'New body content.',
    ]);

    Queue::assertPushed(GenerateArticleAudio::class);
});

test('add from url handles extraction errors gracefully', function () {
    Queue::fake();

    $user = User::factory()->create();
    $url = 'https://example.com/failing-article';

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldReceive('extract')
        ->once()
        ->with($url)
        ->andThrow(new \Exception('Failed to fetch URL'));

    app()->instance(UrlContentExtractor::class, $mockExtractor);

    Volt::actingAs($user)
        ->test('app.library')
        ->set('addUrl', $url)
        ->call('addFromUrl')
        ->assertSet('extractError', 'Failed to extract article. Please check the URL and try again.')
        ->assertSet('isExtracting', false);

    $this->assertDatabaseMissing('articles', ['url' => $url]);
    Queue::assertNotPushed(GenerateArticleAudio::class);
});

test('add from url creates device token if none exists', function () {
    Queue::fake();

    $user = User::factory()->create();
    $url = 'https://example.com/new-article';

    $this->assertDatabaseMissing('device_tokens', ['user_id' => $user->id]);

    $mockExtractor = mock(UrlContentExtractor::class);
    $mockExtractor->shouldReceive('extract')
        ->once()
        ->with($url)
        ->andReturn([
            'title' => 'New Article',
            'body' => 'Content.',
        ]);

    app()->instance(UrlContentExtractor::class, $mockExtractor);

    Volt::actingAs($user)
        ->test('app.library')
        ->set('addUrl', $url)
        ->call('addFromUrl')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('device_tokens', [
        'user_id' => $user->id,
        'name' => 'Web',
    ]);
});
