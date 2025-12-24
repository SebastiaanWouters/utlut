<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section
    class="page-content w-full"
    x-data
    x-bind:class="$store.player.currentTrack ? 'pb-20' : ''"
>
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="flex flex-col gap-6 mt-6">
            <flux:input
                wire:model="current_password"
                :label="__('Current password')"
                type="password"
                required
                autocomplete="current-password"
                class="transition-all duration-200"
            />
            <flux:input
                wire:model="password"
                :label="__('New password')"
                type="password"
                required
                autocomplete="new-password"
                class="transition-all duration-200"
            />
            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm Password')"
                type="password"
                required
                autocomplete="new-password"
                class="transition-all duration-200"
            />

            <div class="flex items-center gap-4">
                <flux:button 
                    variant="primary" 
                    type="submit" 
                    class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]" 
                    data-test="update-password-button"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>{{ __('Save') }}</span>
                    <span wire:loading class="flex items-center gap-2">
                        <flux:icon.loading variant="mini" />
                        {{ __('Saving...') }}
                    </span>
                </flux:button>

                <x-action-message class="text-sm text-zinc-600 dark:text-zinc-400 transition-opacity duration-200" on="password-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
