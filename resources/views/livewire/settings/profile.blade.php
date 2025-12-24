<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id)
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section
    class="page-content w-full"
    x-data
    x-bind:class="$store.player.currentTrack ? 'pb-24' : 'pb-2'"
>
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="flex flex-col gap-6 w-full mt-6">
            <flux:input 
                wire:model="name" 
                :label="__('Name')" 
                type="text" 
                required 
                autofocus 
                autocomplete="name"
                class="transition-all duration-200"
            />

            <div class="flex flex-col gap-4">
                <flux:input 
                    wire:model="email" 
                    :label="__('Email')" 
                    type="email" 
                    required 
                    autocomplete="email"
                    class="transition-all duration-200"
                />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
                    <div class="flex flex-col gap-2">
                        <flux:text class="text-sm dark:text-zinc-300">
                            {{ __('Your email address is unverified.') }}

                            <flux:link 
                                class="text-sm cursor-pointer font-medium text-zinc-900 dark:text-zinc-100 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors duration-200" 
                                wire:click.prevent="resendVerificationNotification"
                            >
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="text-sm font-medium text-green-600 dark:text-green-400 transition-colors duration-200">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <flux:button 
                    variant="primary" 
                    type="submit" 
                    class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]" 
                    data-test="update-profile-button"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>{{ __('Save') }}</span>
                    <span wire:loading class="flex items-center gap-2">
                        <flux:icon.loading variant="mini" />
                        {{ __('Saving...') }}
                    </span>
                </flux:button>

                <x-action-message class="text-sm text-zinc-600 dark:text-zinc-400 transition-opacity duration-200" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
