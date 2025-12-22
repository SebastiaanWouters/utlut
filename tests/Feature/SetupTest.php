<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('setup page is accessible to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('setup'))
        ->assertStatus(200)
        ->assertSeeLivewire('app.setup');
});

test('setup page creates a token if one does not exist', function () {
    $user = User::factory()->create();

    expect($user->deviceTokens)->toHaveCount(0);

    $this->actingAs($user);

    Volt::test('app.setup')
        ->assertSet('token', fn ($token) => ! empty($token));

    expect($user->fresh()->deviceTokens)->toHaveCount(1);
});

test('setup page uses existing token if it exists', function () {
    $user = User::factory()->create();
    $deviceToken = $user->deviceTokens()->create([
        'token' => 'test-token',
        'token_hash' => hash('sha256', 'test-token'),
        'name' => 'Existing Device',
    ]);

    $this->actingAs($user);

    Volt::test('app.setup')
        ->assertSet('token', 'test-token');

    expect($user->fresh()->deviceTokens)->toHaveCount(1);
});
