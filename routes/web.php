<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('library') : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', 'library');
    Route::redirect('settings', 'settings/profile');

    Volt::route('setup', 'app.setup')->name('setup');
    Volt::route('library', 'app.library')->name('library');
    Volt::route('player', 'app.player')->name('player');
    Volt::route('playlists', 'playlists.index')->name('playlists.index');
    Volt::route('playlists/create', 'playlists.create')->name('playlists.create');
    Volt::route('playlists/{playlist}', 'playlists.show')->name('playlists.show');
    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');
    Volt::route('settings/voice', 'settings.voice')->name('voice.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});
