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

<div class="page-content flex w-full flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Playlists') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Organize articles into queues') }}</p>
        </div>
        <flux:button
            variant="primary"
            icon="plus"
            href="{{ route('playlists.create') }}"
            wire:navigate
        >
            {{ __('New Playlist') }}
        </flux:button>
    </div>

    <!-- Content -->
    @if ($this->playlists->isEmpty())
        <div class="flex flex-1 flex-col items-center justify-center gap-4 rounded-2xl border border-zinc-200 bg-white p-12 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex size-14 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-700">
                <flux:icon.musical-note class="size-7 text-zinc-400 dark:text-zinc-500" />
            </div>
            <div class="flex flex-col gap-1 text-center">
                <h2 class="text-base font-medium text-zinc-900 dark:text-zinc-100">{{ __('No playlists yet') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Create your first playlist to organize articles.') }}</p>
            </div>
            <flux:button
                variant="primary"
                icon="plus"
                href="{{ route('playlists.create') }}"
                wire:navigate
            >
                {{ __('Create Playlist') }}
            </flux:button>
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->playlists as $playlist)
                <a
                    href="{{ route('playlists.show', $playlist) }}"
                    wire:navigate
                    class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 transition-colors hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                                <flux:icon.musical-note class="size-4" />
                            </div>
                            <div class="flex min-w-0 flex-col">
                                <h3 class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $playlist->name }}
                                </h3>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ $playlist->items->count() }} {{ __('item') }}{{ $playlist->items->count() !== 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>

                        <flux:dropdown position="bottom" align="end" @click.prevent @click.stop>
                            <flux:button
                                variant="ghost"
                                icon="ellipsis-vertical"
                                icon-only
                                size="sm"
                                class="shrink-0 text-zinc-400 opacity-0 group-hover:opacity-100"
                            />
                            <flux:menu>
                                <flux:menu.item
                                    icon="trash"
                                    wire:click="delete({{ $playlist->id }})"
                                    wire:confirm="{{ __('Delete this playlist?') }}"
                                    class="text-red-600 dark:text-red-400"
                                >
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    @if ($playlist->items->isNotEmpty())
                        <div class="flex flex-col gap-1 rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-700/50">
                            @foreach ($playlist->items->take(3) as $item)
                                <div class="flex items-center gap-2">
                                    <span class="size-1 shrink-0 rounded-full bg-zinc-300 dark:bg-zinc-500"></span>
                                    <span class="line-clamp-1 flex-1 text-xs text-zinc-600 dark:text-zinc-400">
                                        {{ $item->article->title ?: $item->article->url }}
                                    </span>
                                </div>
                            @endforeach
                            @if ($playlist->items->count() > 3)
                                <p class="pl-3 text-xs text-zinc-400 dark:text-zinc-500">
                                    +{{ $playlist->items->count() - 3 }} {{ __('more') }}
                                </p>
                            @endif
                        </div>
                    @else
                        <div class="flex items-center justify-center rounded-lg border border-dashed border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-600 dark:bg-zinc-700/30">
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Empty') }}</p>
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
