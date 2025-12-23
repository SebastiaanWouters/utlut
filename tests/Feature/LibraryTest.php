<?php

use App\Models\Article;
use App\Models\ArticleAudio;
use App\Models\DeviceToken;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('library'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the library', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('library'));
    $response->assertStatus(200);
});

test('library component is rendered', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('library'))
        ->assertSeeLivewire('app.library');
});

test('user can delete their own article', function () {
    $user = User::factory()->create();
    $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['device_token_id' => $deviceToken->id]);

    $this->actingAs($user);

    Volt::test('app.library')
        ->call('deleteArticle', $article->id)
        ->assertHasNoErrors();

    expect($article->fresh())->toBeNull();
});

test('user cannot delete another users article', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherDeviceToken = DeviceToken::factory()->create(['user_id' => $otherUser->id]);
    $article = Article::factory()->create(['device_token_id' => $otherDeviceToken->id]);

    $this->actingAs($user);

    Volt::test('app.library')
        ->call('deleteArticle', $article->id);

    expect($article->fresh())->not->toBeNull();
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('deleting article removes it from playlists', function () {
    $user = User::factory()->create();
    $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['device_token_id' => $deviceToken->id]);
    $playlist = Playlist::factory()->create(['device_token_id' => $deviceToken->id]);
    $playlistItem = PlaylistItem::factory()->create([
        'playlist_id' => $playlist->id,
        'article_id' => $article->id,
    ]);

    $this->actingAs($user);

    Volt::test('app.library')
        ->call('deleteArticle', $article->id)
        ->assertHasNoErrors();

    expect($article->fresh())->toBeNull();
    expect($playlistItem->fresh())->toBeNull();
});

test('deleting article cleans up audio file from storage', function () {
    Storage::fake('public');
    Storage::disk('public')->put('audio/test-file.mp3', 'audio content');

    $user = User::factory()->create();
    $deviceToken = DeviceToken::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['device_token_id' => $deviceToken->id]);
    ArticleAudio::factory()->create([
        'article_id' => $article->id,
        'audio_path' => 'audio/test-file.mp3',
    ]);

    Storage::disk('public')->assertExists('audio/test-file.mp3');

    $this->actingAs($user);

    Volt::test('app.library')
        ->call('deleteArticle', $article->id)
        ->assertHasNoErrors();

    expect($article->fresh())->toBeNull();
    Storage::disk('public')->assertMissing('audio/test-file.mp3');
});
