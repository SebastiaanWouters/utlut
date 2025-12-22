<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('can render', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('playlists.create')
        ->assertSee('Create Playlist');
});
