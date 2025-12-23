<?php

use App\Jobs\ExtractArticleContent;
use App\Models\Article;
use App\Models\DeviceToken;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Services\AudioProgressEstimator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new #[Title('Library')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'all';
    public string $addUrl = '';
    public ?string $extractError = null;

    #[Computed]
    public function articles()
    {
        $deviceTokenIds = Auth::user()->deviceTokens()->pluck('id');

        return Article::with('audio')
            ->whereIn('device_token_id', $deviceTokenIds)
            ->when($this->search, function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('url', 'like', "%{$this->search}%");
            })
            ->when($this->status === 'ready', fn ($q) => $q->whereNotNull('audio_url'))
            ->when($this->status === 'pending', fn ($q) => $q->whereNull('audio_url'))
            ->latest()
            ->paginate(20);
    }

    #[Computed]
    public function playlists()
    {
        $deviceTokenIds = Auth::user()->deviceTokens()->pluck('id');
        return Playlist::whereIn('device_token_id', $deviceTokenIds)->get();
    }

    #[Computed]
    public function hasProcessingArticles(): bool
    {
        $deviceTokenIds = Auth::user()->deviceTokens()->pluck('id');

        return Article::whereIn('device_token_id', $deviceTokenIds)
            ->where(function ($q) {
                $q->where('extraction_status', 'extracting')
                    ->orWhere(function ($q2) {
                        $q2->where('extraction_status', 'ready')
                            ->whereNull('audio_url')
                            ->whereDoesntHave('audio', fn ($q3) => $q3->where('status', 'failed'));
                    });
            })
            ->exists();
    }

    #[Computed]
    public function optimalPollingInterval(): int
    {
        $deviceTokenIds = Auth::user()->deviceTokens()->pluck('id');
        $estimator = app(AudioProgressEstimator::class);

        $processingArticles = Article::with('audio')
            ->whereIn('device_token_id', $deviceTokenIds)
            ->where(function ($q) {
                $q->where('extraction_status', 'extracting')
                    ->orWhere(function ($q2) {
                        $q2->where('extraction_status', 'ready')
                            ->whereNull('audio_url')
                            ->whereDoesntHave('audio', fn ($q3) => $q3->where('status', 'failed'));
                    });
            })
            ->get();

        if ($processingArticles->isEmpty()) {
            return 5000;
        }

        $minInterval = 5000;
        foreach ($processingArticles as $article) {
            if ($article->audio) {
                $interval = $estimator->getOptimalPollingInterval($article->audio);
                $minInterval = min($minInterval, $interval);
            }
        }

        return $minInterval;
    }

    public function calculateEta(Article $article): ?int
    {
        if (! $article->audio?->processing_started_at || ! $article->audio?->estimated_duration_ms) {
            return null;
        }

        $elapsedMs = now()->diffInMilliseconds($article->audio->processing_started_at);
        return max(0, (int) (($article->audio->estimated_duration_ms - $elapsedMs) / 1000));
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

    public function retryExtraction(Article $article): void
    {
        $article->update(['extraction_status' => 'extracting']);
        ExtractArticleContent::dispatch($article);
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
        $this->redirect(route('player'), navigate: true);
    }

    public function addFromUrl(): void
    {
        $this->validate([
            'addUrl' => ['required', 'url', 'max:2048'],
        ]);

        $this->extractError = null;

        try {
            $deviceToken = Auth::user()->deviceTokens()->first();

            if (! $deviceToken) {
                $rawToken = Str::random(32);
                $deviceToken = Auth::user()->deviceTokens()->create([
                    'name' => 'Web',
                    'token' => $rawToken,
                    'token_hash' => hash('sha256', $rawToken),
                ]);
            }

            // Check if article already exists and is extracting (to avoid duplicate jobs)
            $existingStatus = Article::where('device_token_id', $deviceToken->id)
                ->where('url', $this->addUrl)
                ->value('extraction_status');

            $article = Article::updateOrCreate(
                [
                    'device_token_id' => $deviceToken->id,
                    'url' => $this->addUrl,
                ],
                [
                    'extraction_status' => 'extracting',
                ]
            );

            // Only dispatch if not already extracting (avoid duplicate concurrent jobs)
            if ($existingStatus !== 'extracting') {
                ExtractArticleContent::dispatch($article);
            }

            $this->addUrl = '';
            $this->modal('add-from-url')->close();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->extractError = 'Failed to add article. Please try again.';
        }
    }

    public function resetAddModal(): void
    {
        $this->addUrl = '';
        $this->extractError = null;
    }

    public function deleteArticle(int $articleId): void
    {
        $deviceTokenIds = Auth::user()->deviceTokens()->pluck('id');
        $article = Article::whereIn('device_token_id', $deviceTokenIds)->findOrFail($articleId);

        if ($article->audio?->audio_path) {
            Storage::disk('public')->delete($article->audio->audio_path);
        }

        $article->delete();
    }
}; ?>

<div class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-8"
    @if($this->hasProcessingArticles) wire:poll.{{ $this->optimalPollingInterval }}ms @endif>
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Library') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your saved articles') }}</p>
        </div>
        <flux:modal.trigger name="add-from-url">
            <flux:button icon="plus" variant="primary">{{ __('Add URL') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <!-- Filters -->
    <div class="flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search...') }}"
            />
        </div>
        <div class="w-full sm:w-40">
            <flux:select wire:model.live="status">
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                <flux:select.option value="ready">{{ __('Ready') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Processing') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <!-- Content -->
    @if ($this->articles->isEmpty())
        <div class="flex flex-1 flex-col items-center justify-center gap-4 rounded-2xl border border-zinc-200 bg-white p-12 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex size-14 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-700">
                <flux:icon.document-text class="size-7 text-zinc-400 dark:text-zinc-500" />
            </div>
            <div class="flex flex-col gap-1 text-center">
                <h2 class="text-base font-medium text-zinc-900 dark:text-zinc-100">{{ __('No articles yet') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Save pages from Safari using the iOS Shortcut.') }}</p>
            </div>
            <flux:button variant="ghost" icon="cog" :href="route('setup')" wire:navigate>
                {{ __('Setup Instructions') }}
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-2">
            @foreach ($this->articles as $article)
                @php
                    $host = parse_url($article->url, PHP_URL_HOST);
                    $host = $host ? Str::replaceStart('www.', '', $host) : $article->url;
                @endphp

                <div wire:key="article-{{ $article->id }}"
                    x-data="{
                        cached: false,
                        caching: false,
                        async checkCached() {
                            if (window.AudioCache) {
                                this.cached = await window.AudioCache.isCached({{ $article->id }}, $store.player.token);
                            }
                        },
                        async download() {
                            if (this.caching || !window.AudioCache) return;
                            this.caching = true;
                            try {
                                this.cached = await window.AudioCache.prefetch({{ $article->id }}, $store.player.token);
                            } finally {
                                this.caching = false;
                            }
                        }
                    }"
                    x-init="checkCached()"
                    class="group flex items-center gap-4 rounded-xl border bg-white p-3 transition-colors dark:bg-zinc-800 sm:p-4"
                    :class="($store.player.currentTrack && $store.player.currentTrack.id === {{ $article->id }}) ? 'border-zinc-900 dark:border-zinc-100' : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600'"
                >
                    <!-- Status Icon -->
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg transition-colors"
                        :class="($store.player.currentTrack && $store.player.currentTrack.id === {{ $article->id }})
                            ? 'bg-zinc-900 text-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                            : '{{ $article->audio_url ? 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-700 dark:text-zinc-500' }}'"
                    >
                        <template x-if="$store.player.currentTrack && $store.player.currentTrack.id === {{ $article->id }} && $store.player.isPlaying">
                            <div class="flex items-center gap-px">
                                <span class="h-2.5 w-0.5 animate-[bounce_0.6s_ease-in-out_infinite] rounded-full bg-current"></span>
                                <span class="h-3.5 w-0.5 animate-[bounce_0.6s_ease-in-out_infinite_0.1s] rounded-full bg-current"></span>
                                <span class="h-2 w-0.5 animate-[bounce_0.6s_ease-in-out_infinite_0.2s] rounded-full bg-current"></span>
                            </div>
                        </template>
                        <template x-if="!($store.player.currentTrack && $store.player.currentTrack.id === {{ $article->id }} && $store.player.isPlaying)">
                            @if ($article->audio_url)
                                <flux:icon.play variant="solid" class="size-4" />
                            @else
                                <flux:icon.clock variant="outline" class="size-4" />
                            @endif
                        </template>
                    </div>

                    <!-- Content -->
                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <div class="flex items-center gap-2">
                            <h3 class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $article->title ?: $article->url }}
                            </h3>
                            @if ($article->extraction_status === 'extracting')
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                    <svg class="size-2.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    {{ __('Extracting') }}
                                </span>
                            @elseif ($article->extraction_status === 'failed')
                                <span class="shrink-0 rounded-md bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-600 dark:bg-red-950/50 dark:text-red-400">
                                    {{ __('Failed') }}
                                </span>
                            @elseif ($article->audio_url)
                                <span x-show="cached" x-cloak class="shrink-0 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                    {{ __('Offline') }}
                                </span>
                            @elseif ($article->audio?->status === 'failed')
                                <x-audio-error-badge
                                    :errorCode="$article->audio->error_code"
                                    :message="$article->audio->error_message"
                                    :nextRetryAt="$article->audio->next_retry_at"
                                    :retryCount="$article->audio->retry_count ?? 0"
                                />
                            @elseif ($article->extraction_status === 'ready')
                                <x-audio-progress-badge
                                    :progress="$article->audio?->progress_percent ?? 0"
                                    :etaSeconds="$this->calculateEta($article)"
                                />
                            @endif
                        </div>
                        <p class="truncate text-xs text-zinc-400 dark:text-zinc-500">{{ $host }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1">
                        @if ($article->extraction_status === 'extracting')
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-medium text-zinc-400 dark:bg-zinc-700 dark:text-zinc-500">
                                <svg class="size-3 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{ __('Extracting...') }}
                            </span>
                        @elseif ($article->extraction_status === 'failed')
                            <flux:button size="sm" variant="ghost" wire:click="retryExtraction({{ $article->id }})">{{ __('Retry') }}</flux:button>
                        @elseif ($article->audio_url)
                            <button
                                wire:click="play({{ $article->id }})"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                                :class="($store.player.currentTrack && $store.player.currentTrack.id === {{ $article->id }})
                                    ? 'bg-zinc-900 text-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                                    : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-600'"
                            >
                                <template x-if="$store.player.currentTrack && $store.player.currentTrack.id === {{ $article->id }}">
                                    <span>{{ __('Playing') }}</span>
                                </template>
                                <template x-if="!($store.player.currentTrack && $store.player.currentTrack.id === {{ $article->id }})">
                                    <span>{{ __('Play') }}</span>
                                </template>
                            </button>
                        @elseif ($article->audio?->status === 'failed')
                            <flux:button size="sm" variant="ghost" wire:click="generateAudio({{ $article->id }})">{{ __('Retry') }}</flux:button>
                        @elseif ($article->extraction_status === 'ready')
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-medium text-zinc-400 dark:bg-zinc-700 dark:text-zinc-500">
                                <svg class="size-3" viewBox="0 0 16 16">
                                    <circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="2" class="opacity-25" />
                                    <circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-dasharray="{{ 2 * 3.14159 * 6 }}"
                                        stroke-dashoffset="{{ 2 * 3.14159 * 6 * (1 - ($article->audio?->progress_percent ?? 0) / 100) }}"
                                        transform="rotate(-90 8 8)" class="transition-all duration-300" />
                                </svg>
                                {{ $article->audio?->progress_percent ?? 0 }}%
                            </span>
                        @endif

                        <flux:dropdown position="bottom" align="end">
                            <flux:button icon="ellipsis-vertical" variant="ghost" size="sm" icon-only class="text-zinc-400" />
                            <flux:menu>
                                @if ($article->audio_url)
                                    <flux:menu.item
                                        icon="play"
                                        wire:click="play({{ $article->id }})"
                                    >
                                        {{ __('Play Now') }}
                                    </flux:menu.item>
                                    <flux:menu.item
                                        icon="queue-list"
                                        x-on:click="$dispatch('add-to-queue', { articleId: {{ $article->id }} })"
                                    >
                                        {{ __('Add to Queue') }}
                                    </flux:menu.item>
                                    <flux:menu.item
                                        icon="arrow-right"
                                        x-on:click="$dispatch('play-next', { articleId: {{ $article->id }} })"
                                    >
                                        {{ __('Play Next') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        x-show="!cached"
                                        icon="arrow-down-tray"
                                        x-on:click="download()"
                                        ::disabled="caching"
                                    >
                                        <span x-show="!caching">{{ __('Download for Offline') }}</span>
                                        <span x-show="caching" x-cloak>{{ __('Downloading...') }}</span>
                                    </flux:menu.item>
                                    <flux:menu.item
                                        x-show="cached"
                                        icon="check-circle"
                                        disabled
                                        x-cloak
                                    >
                                        {{ __('Downloaded') }}
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item
                                        icon="clock"
                                        disabled
                                    >
                                        {{ __('Processing...') }}
                                    </flux:menu.item>
                                @endif
                                @if ($this->playlists->isNotEmpty())
                                    <flux:menu.separator />
                                    <flux:menu.group heading="{{ __('Add to Playlist') }}">
                                        @foreach ($this->playlists as $playlist)
                                            <flux:menu.item wire:click="addToPlaylist({{ $article->id }}, {{ $playlist->id }})">
                                                {{ $playlist->name }}
                                            </flux:menu.item>
                                        @endforeach
                                    </flux:menu.group>
                                @endif
                                <flux:menu.separator />
                                <flux:menu.item
                                    icon="trash"
                                    wire:click="deleteArticle({{ $article->id }})"
                                    wire:confirm="{{ __('Delete this article? This will also remove it from any playlists.') }}"
                                    class="text-red-600 dark:text-red-400"
                                >
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-2">
            {{ $this->articles->links() }}
        </div>
    @endif

    <!-- Add URL Modal -->
    <flux:modal name="add-from-url" class="md:w-[440px]" x-on:close="$wire.resetAddModal()">
        <div class="flex flex-col gap-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Add from URL') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Paste an article URL to convert to audio.') }}</p>
            </div>

            <form wire:submit="addFromUrl" class="flex flex-col gap-4">
                <flux:field>
                    <flux:label>{{ __('URL') }}</flux:label>
                    <flux:input
                        wire:model="addUrl"
                        type="url"
                        placeholder="https://..."
                        autofocus
                    />
                    <flux:error name="addUrl" />
                </flux:field>

                @if ($extractError)
                    <div class="flex items-start gap-3 rounded-lg bg-red-50 p-3 dark:bg-red-950/50">
                        <flux:icon.exclamation-circle class="mt-0.5 size-5 shrink-0 text-red-500 dark:text-red-400" />
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $extractError }}</p>
                    </div>
                @endif

                <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="plus">
                        {{ __('Add Article') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

