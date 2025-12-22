<?php

use App\Models\Article;
use App\Models\DeviceToken;
use App\Models\Playlist;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->deviceToken = DeviceToken::factory()->create(['user_id' => $this->user->id]);
    $this->token = $this->deviceToken->token;
});

test('can create a playlist', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/playlists', ['name' => 'My Favorites']);

    $response->assertStatus(201)
        ->assertJson(['ok' => true]);

    $this->assertDatabaseHas('playlists', [
        'device_token_id' => $this->deviceToken->id,
        'name' => 'My Favorites',
    ]);
});

test('can rename a playlist', function () {
    $playlist = Playlist::factory()->create(['device_token_id' => $this->deviceToken->id]);

    $this->withToken($this->token)
        ->putJson("/api/playlists/{$playlist->id}", ['name' => 'New Name'])
        ->assertOk();

    expect($playlist->fresh()->name)->toBe('New Name');
});

test('can add items to a playlist', function () {
    $playlist = Playlist::factory()->create(['device_token_id' => $this->deviceToken->id]);
    $article = Article::factory()->create();

    $response = $this->withToken($this->token)
        ->postJson("/api/playlists/{$playlist->id}/items", [
            'article_id' => $article->id,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('playlist_items', [
        'playlist_id' => $playlist->id,
        'article_id' => $article->id,
        'position' => 1,
    ]);
});

test('can reorder items in a playlist', function () {
    $playlist = Playlist::factory()->create(['device_token_id' => $this->deviceToken->id]);
    $articles = Article::factory()->count(3)->create();

    $item1 = $playlist->items()->create(['article_id' => $articles[0]->id, 'position' => 1]);
    $item2 = $playlist->items()->create(['article_id' => $articles[1]->id, 'position' => 2]);
    $item3 = $playlist->items()->create(['article_id' => $articles[2]->id, 'position' => 3]);

    // Move item 3 to position 1
    $this->withToken($this->token)
        ->putJson("/api/playlists/{$playlist->id}/items/{$item3->id}", [
            'position' => 1,
        ])
        ->assertOk();

    expect($item3->fresh()->position)->toBe(1);
    expect($item1->fresh()->position)->toBe(2);
    expect($item2->fresh()->position)->toBe(3);

    // Move item 1 (now at 2) to position 3
    $this->withToken($this->token)
        ->putJson("/api/playlists/{$playlist->id}/items/{$item1->id}", [
            'position' => 3,
        ])
        ->assertOk();

    expect($item3->fresh()->position)->toBe(1);
    expect($item2->fresh()->position)->toBe(2);
    expect($item1->fresh()->position)->toBe(3);
});

test('can remove items from a playlist', function () {
    $playlist = Playlist::factory()->create(['device_token_id' => $this->deviceToken->id]);
    $articles = Article::factory()->count(3)->create();

    $item1 = $playlist->items()->create(['article_id' => $articles[0]->id, 'position' => 1]);
    $item2 = $playlist->items()->create(['article_id' => $articles[1]->id, 'position' => 2]);
    $item3 = $playlist->items()->create(['article_id' => $articles[2]->id, 'position' => 3]);

    $this->withToken($this->token)
        ->deleteJson("/api/playlists/{$playlist->id}/items/{$item2->id}")
        ->assertOk();

    $this->assertDatabaseMissing('playlist_items', ['id' => $item2->id]);
    expect($item1->fresh()->position)->toBe(1);
    expect($item3->fresh()->position)->toBe(2);
});

test('cannot access playlist without token', function () {
    $this->postJson('/api/playlists', ['name' => 'Should Fail'])
        ->assertStatus(401);
});

test('cannot access playlist with invalid token', function () {
    $this->withToken('invalid-token')
        ->postJson('/api/playlists', ['name' => 'Should Fail'])
        ->assertStatus(401);
});

test('cannot rename another device\'s playlist', function () {
    $otherDeviceToken = DeviceToken::factory()->create(['user_id' => $this->user->id]);
    $playlist = Playlist::factory()->create(['device_token_id' => $otherDeviceToken->id]);

    $this->withToken($this->token)
        ->putJson("/api/playlists/{$playlist->id}", ['name' => 'Hacked Name'])
        ->assertForbidden();

    expect($playlist->fresh()->name)->not->toBe('Hacked Name');
});

test('cannot add items to another device\'s playlist', function () {
    $otherDeviceToken = DeviceToken::factory()->create(['user_id' => $this->user->id]);
    $playlist = Playlist::factory()->create(['device_token_id' => $otherDeviceToken->id]);
    $article = Article::factory()->create();

    $this->withToken($this->token)
        ->postJson("/api/playlists/{$playlist->id}/items", ['article_id' => $article->id])
        ->assertForbidden();
});

test('reordering handles out of bounds position', function () {
    $playlist = Playlist::factory()->create(['device_token_id' => $this->deviceToken->id]);
    $article = Article::factory()->create();
    $item = $playlist->items()->create(['article_id' => $article->id, 'position' => 1]);

    $this->withToken($this->token)
        ->putJson("/api/playlists/{$playlist->id}/items/{$item->id}", [
            'position' => 999,
        ])
        ->assertOk();

    expect($item->fresh()->position)->toBe(1);
});
