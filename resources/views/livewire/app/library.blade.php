<?php

use App\Models\Article;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new #[Title('Library')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'all';

    #[Computed]
    public function articles()
    {
        $deviceTokenIds = Auth::user()->deviceTokens()->pluck('id');

        return Article::whereIn('device_token_id', $deviceTokenIds)
            ->when($this->search, function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('url', 'like', "%{$this->search}%");
            })
            ->when($this->status === 'ready', fn($q) => $q->whereNotNull('audio_url'))
            ->when($this->status === 'pending', fn($q) => $q->whereNull('audio_url'))
            ->latest()
            ->paginate(20);
    }

    #[Computed]
    public function playlists()
    {
        $deviceTokenIds = Auth::user()->deviceTokens()->pluck('id');
        return Playlist::whereIn('device_token_id', $deviceTokenIds)->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function generateAudio(Article $article): void
    {
        \App\Jobs\GenerateArticleAudio::dispatch($article);
    }

    public function addToPlaylist(Article $article, Playlist $playlist): void
    {
        $position = ($playlist->items()->max('position') ?? 0) + 1;
        
        $playlist->items()->create([
            'article_id' => $article->id,
            'position' => $position,
        ]);
    }

    public function play(Article $article): void
    {
        $this->dispatch('play-article', articleId: $article->id);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl p-4 md:p-8">
    <div class="flex items-center justify-between">
        <div class="flex flex-col gap-2">
            <flux:heading level="1" size="xl">{{ __('Library') }}</flux:heading>
            <flux:text variant="subtle" class="text-sm">{{ __('Your collection of articles and saved web pages.') }}</flux:text>
        </div>
    </div>

    <div class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                icon="magnifying-glass" 
                placeholder="{{ __('Search articles...') }}" 
            />
        </div>
        <div class="w-full md:w-48">
            <flux:select wire:model.live="status">
                <flux:select.option value="all">{{ __('All Status') }}</flux:select.option>
                <flux:select.option value="ready">{{ __('Ready') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    @if ($this->articles->isEmpty())
        <div class="flex flex-col items-center justify-center gap-4 py-12 text-center">
            <flux:icon name="document-text" size="xl" class="text-zinc-400 dark:text-zinc-600" />
            <div class="flex flex-col gap-2">
                <flux:heading level="2" size="lg">{{ __('No articles found') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Start saving articles using the iOS Shortcut.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-2">
            @foreach ($this->articles as $article)
                <div wire:key="article-{{ $article->id }}" class="group flex items-center gap-4 rounded-lg border border-zinc-200 bg-white p-4 transition-all hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800/50">
                    <div class="flex-1 flex flex-col gap-1 overflow-hidden">
                        <div class="flex items-center gap-2">
                            <flux:heading level="3" size="md" class="truncate">{{ $article->title ?: $article->url }}</flux:heading>
                            @if ($article->audio_url)
                                <flux:badge variant="success" size="sm" class="lowercase text-[10px]">{{ __('Ready') }}</flux:badge>
                            @else
                                <flux:badge variant="warning" size="sm" class="lowercase text-[10px]">{{ __('Pending') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text variant="subtle" class="truncate text-xs">{{ $article->url }}</flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($article->audio_url)
                            <flux:button icon="play" variant="ghost" size="sm" wire:click="play({{ $article->id }})">{{ __('Play') }}</flux:button>
                        @else
                            <flux:button icon="bolt" variant="ghost" size="sm" wire:click="generateAudio({{ $article->id }})">{{ __('Generate') }}</flux:button>
                        @endif

                        <flux:dropdown position="bottom" align="end">
                            <flux:button icon="ellipsis-vertical" variant="ghost" size="sm" icon-only />
                            <flux:menu>
                                <flux:menu.item icon="play" wire:click="play({{ $article->id }})">{{ __('Play Now') }}</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.group heading="{{ __('Add to Playlist') }}">
                                    @foreach ($this->playlists as $playlist)
                                        <flux:menu.item wire:click="addToPlaylist({{ $article->id }}, {{ $playlist->id }})">
                                            {{ $playlist->name }}
                                        </flux:menu.item>
                                    @endforeach
                                    @if ($this->playlists->isEmpty())
                                        <flux:menu.item disabled>{{ __('No playlists') }}</flux:menu.item>
                                    @endif
                                </flux:menu.group>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $this->articles->links() }}
        </div>
    @endif
</div>

