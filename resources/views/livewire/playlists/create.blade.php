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

<div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl p-4 md:p-8">
    <div class="flex flex-col gap-2">
        <flux:heading level="1" size="xl">{{ __('Create Playlist') }}</flux:heading>
        <flux:text variant="subtle" class="text-sm">{{ __('Create a new playlist to organize your articles.') }}</flux:text>
    </div>

    <form wire:submit="create" class="flex max-w-2xl flex-col gap-6">
        <flux:input 
            wire:model="name" 
            :label="__('Playlist Name')" 
            type="text" 
            required 
            autofocus 
            placeholder="{{ __('My Playlist') }}"
            class="transition-all duration-200"
        />

        <div class="flex items-center gap-4">
            <flux:button 
                variant="primary" 
                type="submit" 
                class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]" 
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>{{ __('Create') }}</span>
                <span wire:loading class="flex items-center gap-2">
                    <flux:icon.loading variant="mini" />
                    {{ __('Creating...') }}
                </span>
            </flux:button>

            <flux:button 
                variant="ghost" 
                :href="route('playlists.index')"
                wire:navigate
                class="transition-all duration-200"
            >
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>
