<?php

use App\Models\DeviceToken;
use App\Models\Playlist;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Create Playlist')] class extends Component {
    public string $name = '';
    public ?int $deviceTokenId = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $deviceToken = $user->deviceTokens()->first();
        if ($deviceToken) {
            $this->deviceTokenId = $deviceToken->id;
        }
    }

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'deviceTokenId' => ['required', 'exists:device_tokens,id'],
        ]);

        $playlist = Playlist::create([
            'device_token_id' => $validated['deviceTokenId'],
            'name' => $validated['name'],
        ]);

        $this->redirect(route('playlists.show', $playlist), navigate: true);
    }
}; ?>

<div class="mx-auto flex h-full w-full max-w-lg flex-1 flex-col gap-6 px-4 py-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="flex items-center gap-3">
        <flux:button icon="chevron-left" variant="ghost" size="sm" :href="route('playlists.index')" wire:navigate />
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('New Playlist') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Create a playlist to organize articles') }}</p>
        </div>
    </div>

    <!-- Form -->
    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
        <form wire:submit="create" class="flex flex-col gap-5">
            <flux:input
                wire:model="name"
                :label="__('Name')"
                type="text"
                required
                autofocus
                placeholder="{{ __('Morning reads, Tech articles...') }}"
            />

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button
                    variant="ghost"
                    :href="route('playlists.index')"
                    wire:navigate
                >
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button
                    variant="primary"
                    type="submit"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>{{ __('Create') }}</span>
                    <span wire:loading class="flex items-center gap-2">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Creating...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </div>
</div>
