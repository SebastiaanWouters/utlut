<?php

use App\Models\Playlist;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Playlists')] class extends Component
{
    #[Computed]
    public function playlists()
    {
        $user = Auth::user();
        if (!$user) return collect();

        $deviceTokenIds = $user->deviceTokens()->pluck('id');

        return Playlist::whereIn('device_token_id', $deviceTokenIds)
            ->with(['items.article'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function delete(int $playlistId): void
    {
        $deviceTokenIds = Auth::user()->deviceTokens()->pluck('id');
        $playlist = Playlist::whereIn('device_token_id', $deviceTokenIds)->findOrFail($playlistId);
        $playlist->delete();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl p-4 md:p-8">
    <div class="flex items-center justify-between">
        <div class="flex flex-col gap-2">
            <flux:heading level="1" size="xl">{{ __('Playlists') }}</flux:heading>
            <flux:text variant="subtle" class="text-sm">{{ __('Manage your playlists and organize your articles.') }}</flux:text>
        </div>

        <flux:button 
            variant="primary" 
            icon="plus"
            href="{{ route('playlists.create') }}"
            wire:navigate
            class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
        >
            {{ __('New Playlist') }}
        </flux:button>
    </div>

    @if ($this->playlists->isEmpty())
        <div class="flex flex-col items-center justify-center gap-4 py-12 text-center">
            <flux:icon name="musical-note" size="xl" class="text-zinc-400 dark:text-zinc-600" />
            <div class="flex flex-col gap-2">
                <flux:heading level="2" size="lg">{{ __('No playlists yet') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Create your first playlist to get started.') }}</flux:text>
            </div>
            <flux:button 
                variant="primary" 
                icon="plus"
                href="{{ route('playlists.create') }}"
                wire:navigate
                class="mt-2 transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
            >
                {{ __('Create Playlist') }}
            </flux:button>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->playlists as $playlist)
                <div class="group relative flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-6 transition-all duration-200 hover:border-zinc-300 hover:shadow-lg dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex flex-1 flex-col gap-1">
                            <flux:heading level="3" size="lg" class="line-clamp-1">
                                <a 
                                    href="{{ route('playlists.show', $playlist) }}" 
                                    wire:navigate
                                    class="transition-colors duration-200 hover:text-zinc-600 dark:hover:text-zinc-300"
                                >
                                    {{ $playlist->name }}
                                </a>
                            </flux:heading>
                            <flux:text variant="subtle" class="text-sm">
                                {{ $playlist->items->count() }} {{ __('item') }}{{ $playlist->items->count() !== 1 ? 's' : '' }}
                            </flux:text>
                        </div>

                        <flux:dropdown position="bottom" align="end">
                            <flux:button 
                                variant="ghost" 
                                icon="ellipsis-vertical" 
                                icon-only
                                class="shrink-0 transition-all duration-200"
                            />

                            <flux:menu>
                                <flux:menu.item 
                                    :href="route('playlists.show', $playlist)" 
                                    icon="eye"
                                    wire:navigate
                                >
                                    {{ __('View') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item 
                                    icon="trash" 
                                    wire:click="delete({{ $playlist->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this playlist?') }}"
                                    class="text-red-600 dark:text-red-400"
                                >
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    @if ($playlist->items->isNotEmpty())
                        <div class="flex flex-col gap-2">
                            @foreach ($playlist->items->take(3) as $item)
                                <div class="flex items-center gap-3 text-sm">
                                    <flux:icon name="musical-note" size="sm" class="shrink-0 text-zinc-400 dark:text-zinc-500" />
                                    <span class="line-clamp-1 flex-1 text-zinc-600 dark:text-zinc-400">
                                        {{ $item->article->title ?: $item->article->url }}
                                    </span>
                                </div>
                            @endforeach
                            @if ($playlist->items->count() > 3)
                                <flux:text variant="subtle" class="text-xs">
                                    {{ __('and :count more', ['count' => $playlist->items->count() - 3]) }}
                                </flux:text>
                            @endif
                        </div>
                    @endif

                    <div class="mt-auto flex items-center gap-2">
                        <flux:button 
                            variant="ghost" 
                            size="sm"
                            :href="route('playlists.show', $playlist)"
                            wire:navigate
                            class="flex-1 transition-all duration-200"
                        >
                            {{ __('Open') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
