<?php

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('can render', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create();

    Volt::actingAs($user)
        ->test('playlists.show', ['playlist' => $playlist])
        ->assertSee($playlist->name);
});
