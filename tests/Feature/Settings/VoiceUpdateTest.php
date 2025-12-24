<?php

use App\Enums\TtsVoice;
use App\Models\User;
use Livewire\Volt\Volt;

test('voice settings page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('voice.edit'))->assertOk();
});

test('voice setting can be updated', function () {
    $user = User::factory()->create(['tts_voice' => 'alloy']);

    $this->actingAs($user);

    $response = Volt::test('settings.voice')
        ->set('tts_voice', 'nova')
        ->call('updateVoiceSettings');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->tts_voice)->toBe(TtsVoice::Nova);
});

test('voice setting validates against available voices', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.voice')
        ->set('tts_voice', 'invalid-voice')
        ->call('updateVoiceSettings');

    $response->assertHasErrors(['tts_voice']);
});

test('voice settings page shows all available voices', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.voice');

    foreach (TtsVoice::cases() as $voice) {
        $response->assertSee($voice->label());
    }
});

test('voice settings loads current user voice', function () {
    $user = User::factory()->create(['tts_voice' => 'coral']);

    $this->actingAs($user);

    $response = Volt::test('settings.voice');

    expect($response->get('tts_voice'))->toBe('coral');
});

test('voice settings requires authentication', function () {
    $this->get(route('voice.edit'))
        ->assertRedirect(route('login'));
});
