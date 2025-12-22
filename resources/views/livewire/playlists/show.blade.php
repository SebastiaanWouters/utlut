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

<div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl p-4 md:p-8">
    <div class="flex items-center justify-between">
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-4">
                <flux:button icon="chevron-left" variant="ghost" :href="route('playlists.index')" wire:navigate />
                <flux:heading level="1" size="xl">{{ $playlist->name }}</flux:heading>
            </div>
            <flux:text variant="subtle" class="ms-12 text-sm">{{ $this->items->count() }} {{ __('items') }}</flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button 
                icon="play" 
                variant="primary" 
                :disabled="$this->items->isEmpty()"
                wire:click="play"
            >
                {{ __('Play') }}
            </flux:button>
            <flux:modal.trigger name="add-articles">
                <flux:button icon="plus">{{ __('Add Articles') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($this->items->isEmpty())
        <div class="flex flex-col items-center justify-center gap-4 py-12 text-center">
            <flux:icon name="musical-note" size="xl" class="text-zinc-400 dark:text-zinc-600" />
            <div class="flex flex-col gap-2">
                <flux:heading level="2" size="lg">{{ __('Playlist is empty') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Add some articles from your library.') }}</flux:text>
            </div>
            <flux:modal.trigger name="add-articles">
                <flux:button variant="primary" icon="plus" class="mt-2">{{ __('Add Articles') }}</flux:button>
            </flux:modal.trigger>
        </div>
    @else
        <div class="flex flex-col gap-2" x-data="{ draggedItem: null }">
            @foreach ($this->items as $item)
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
                    class="group flex items-center gap-4 rounded-lg border border-zinc-200 bg-white p-4 transition-all hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 cursor-move"
                >
                    <flux:icon 
                        name="bars-3" 
                        class="shrink-0 text-zinc-400 transition-colors duration-200 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300"
                    />

                    <div class="flex flex-col gap-1 overflow-hidden flex-1">
                        <flux:heading level="3" size="md" class="truncate">{{ $item->article->title ?: $item->article->url }}</flux:heading>
                        @if ($item->article->title)
                            <flux:text variant="subtle" class="truncate text-xs">{{ $item->article->url }}</flux:text>
                        @endif
                    </div>

                    <flux:button 
                        icon="trash" 
                        variant="ghost" 
                        size="sm" 
                        class="shrink-0 text-red-600 transition-all duration-200 hover:scale-110 dark:text-red-400 opacity-0 group-hover:opacity-100" 
                        wire:click="removeItem({{ $item->id }})"
                        wire:confirm="{{ __('Are you sure you want to remove this item?') }}"
                    />
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="add-articles" class="md:w-[600px]">
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <flux:heading level="2" size="lg">{{ __('Add Articles to Playlist') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Search and select articles from your library.') }}</flux:text>
            </div>

            <flux:input 
                wire:model.live.debounce.300ms="search" 
                icon="magnifying-glass" 
                placeholder="{{ __('Search articles...') }}" 
            />

            <div class="flex flex-col gap-2 max-h-[400px] overflow-y-auto">
                @forelse ($this->availableArticles as $article)
                    <div wire:key="avail-{{ $article->id }}" class="flex items-center justify-between gap-4 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <div class="flex flex-col gap-1 overflow-hidden">
                            <flux:heading level="4" size="sm" class="truncate">{{ $article->title ?: $article->url }}</flux:heading>
                            <flux:text variant="subtle" class="truncate text-xs">{{ $article->url }}</flux:text>
                        </div>
                        <flux:button size="sm" icon="plus" wire:click="addItem({{ $article->id }})">{{ __('Add') }}</flux:button>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:text variant="subtle">{{ __('No articles found.') }}</flux:text>
                    </div>
                @endforelse
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
