<?php

use App\Models\User;

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
