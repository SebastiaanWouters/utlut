<?php

use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Article;
use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;

new #[Title('Playlist Detail')] class extends Component {
    public Playlist $playlist;
    public bool $showAddModal = false;
    public string $search = '';

    #[Computed]
    public function items()
    {
        return $this->playlist->items()->with('article')->orderBy('position')->get();
    }

    #[Computed]
    public function availableArticles()
    {
        $user = Auth::user();
        if (!$user) return collect();

        $deviceTokenIds = $user->deviceTokens()->pluck('id');
        $existingArticleIds = $this->playlist->items()->pluck('article_id');

        return Article::whereIn('device_token_id', $deviceTokenIds)
            ->whereNotIn('id', $existingArticleIds)
            ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%")->orWhere('url', 'like', "%{$this->search}%"))
            ->latest()
            ->take(10)
            ->get();
    }

    public function addItem(int $articleId): void
    {
        $position = ($this->playlist->items()->max('position') ?? 0) + 1;

        $this->playlist->items()->create([
            'article_id' => $articleId,
            'position' => $position,
        ]);

        $this->showAddModal = false;
        $this->search = '';
    }

    public function removeItem(int $itemId): void
    {
        $item = $this->playlist->items()->findOrFail($itemId);
        $position = $item->position;
        $item->delete();

        $this->playlist->items()->where('position', '>', $position)->decrement('position');
    }

    public function play(): void
    {
        $articleIds = $this->items()->pluck('article_id')->toArray();
        $this->dispatch('play-queue', articleIds: $articleIds);
        $this->redirect(route('player'), navigate: true);
    }

    public function reorder(int $itemId, int $newPosition): void
    {
        $item = $this->playlist->items()->findOrFail($itemId);
        $oldPosition = $item->position;

        if ($oldPosition === $newPosition) {
            return;
        }

        $maxPosition = $this->playlist->items()->max('position');
        if ($newPosition > $maxPosition) {
            $newPosition = $maxPosition;
        }

        \DB::transaction(function () use ($item, $oldPosition, $newPosition) {
            if ($newPosition > $oldPosition) {
                $this->playlist->items()
                    ->whereBetween('position', [$oldPosition + 1, $newPosition])
                    ->decrement('position');
            } else {
                $this->playlist->items()
                    ->whereBetween('position', [$newPosition, $oldPosition - 1])
                    ->increment('position');
            }

            $item->update(['position' => $newPosition]);
        });
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 px-2 py-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <flux:button icon="chevron-left" variant="ghost" size="sm" :href="route('playlists.index')" wire:navigate />
            <div class="flex min-w-0 flex-col">
                <h1 class="truncate text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $playlist->name }}</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $this->items->count() }} {{ __('item') }}{{ $this->items->count() !== 1 ? 's' : '' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:modal.trigger name="add-articles">
                <flux:button icon="plus" variant="ghost">{{ __('Add') }}</flux:button>
            </flux:modal.trigger>
            <flux:button
                icon="play"
                variant="primary"
                :disabled="$this->items->isEmpty()"
                wire:click="play"
            >
                {{ __('Play All') }}
            </flux:button>
        </div>
    </div>

    <!-- Content -->
    @if ($this->items->isEmpty())
        <div class="flex flex-1 flex-col items-center justify-center gap-4 rounded-2xl border border-zinc-200 bg-white p-12 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex size-14 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-700">
                <flux:icon.musical-note class="size-7 text-zinc-400 dark:text-zinc-500" />
            </div>
            <div class="flex flex-col gap-1 text-center">
                <h2 class="text-base font-medium text-zinc-900 dark:text-zinc-100">{{ __('Playlist is empty') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add articles from your library.') }}</p>
            </div>
            <flux:modal.trigger name="add-articles">
                <flux:button variant="primary" icon="plus">{{ __('Add Articles') }}</flux:button>
            </flux:modal.trigger>
        </div>
    @else
        <div class="flex flex-col gap-2" x-data="{ draggedItem: null }">
            @foreach ($this->items as $index => $item)
                <div
                    wire:key="item-{{ $item->id }}"
                    draggable="true"
                    @dragstart="draggedItem = {{ $item->id }}"
                    @dragover.prevent
                    @drop.prevent="
                        if (draggedItem && draggedItem !== {{ $item->id }}) {
                            $wire.reorder(draggedItem, {{ $item->position }});
                            draggedItem = null;
                        }
                    "
                    class="group flex cursor-move items-center gap-4 rounded-xl border bg-white p-3 transition-colors dark:bg-zinc-800 sm:p-4"
                    :class="($store.player.currentTrack && $store.player.currentTrack.id === {{ $item->article->id }}) ? 'border-zinc-900 dark:border-zinc-100' : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600'"
                >
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-medium transition-colors"
                        :class="($store.player.currentTrack && $store.player.currentTrack.id === {{ $item->article->id }})
                            ? 'bg-zinc-900 text-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                            : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400'"
                    >
                        <template x-if="$store.player.currentTrack && $store.player.currentTrack.id === {{ $item->article->id }} && $store.player.isPlaying">
                            <div class="flex items-center gap-px">
                                <span class="h-2.5 w-0.5 animate-[bounce_0.6s_ease-in-out_infinite] rounded-full bg-current"></span>
                                <span class="h-3.5 w-0.5 animate-[bounce_0.6s_ease-in-out_infinite_0.1s] rounded-full bg-current"></span>
                                <span class="h-2 w-0.5 animate-[bounce_0.6s_ease-in-out_infinite_0.2s] rounded-full bg-current"></span>
                            </div>
                        </template>
                        <template x-if="!($store.player.currentTrack && $store.player.currentTrack.id === {{ $item->article->id }} && $store.player.isPlaying)">
                            <span>{{ $index + 1 }}</span>
                        </template>
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <h3 class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $item->article->title ?: $item->article->url }}
                        </h3>
                        @if ($item->article->title)
                            <p class="truncate text-xs text-zinc-400 dark:text-zinc-500">{{ parse_url($item->article->url, PHP_URL_HOST) }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                        <flux:button
                            icon="bars-3"
                            variant="ghost"
                            size="sm"
                            class="cursor-grab text-zinc-400"
                        />
                        <flux:button
                            icon="x-mark"
                            variant="ghost"
                            size="sm"
                            class="text-zinc-400 hover:text-red-500 dark:hover:text-red-400"
                            wire:click="removeItem({{ $item->id }})"
                            wire:confirm="{{ __('Remove this article?') }}"
                        />
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Add Articles Modal -->
    <flux:modal name="add-articles" class="md:w-[500px]">
        <div class="flex flex-col gap-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Add Articles') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Select from your library.') }}</p>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search...') }}"
            />

            <div class="flex max-h-80 flex-col gap-1 overflow-y-auto">
                @forelse ($this->availableArticles as $article)
                    <div wire:key="avail-{{ $article->id }}" class="flex items-center justify-between gap-3 rounded-lg p-2.5 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <h4 class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $article->title ?: $article->url }}</h4>
                            <p class="truncate text-xs text-zinc-400 dark:text-zinc-500">{{ parse_url($article->url, PHP_URL_HOST) }}</p>
                        </div>
                        <flux:button size="sm" icon="plus" variant="ghost" wire:click="addItem({{ $article->id }})">{{ __('Add') }}</flux:button>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center gap-2 py-8">
                        <flux:icon.document-text class="size-6 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No articles found') }}</p>
                    </div>
                @endforelse
            </div>

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
