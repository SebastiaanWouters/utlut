<?php

use App\Models\Playlist;
use App\Models\Article;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

new #[Title('Now Playing')] #[Layout('components.layouts.app')] class extends Component {
    public string $token = '';

    public function mount(): void
    {
        $this->token = Auth::user()->deviceTokens()->first()?->token ?? '';
    }
}; ?>

<div class="flex flex-col h-full bg-zinc-50 dark:bg-zinc-950 overflow-hidden" x-data="player('{{ $token }}')">
    <div x-show="!isOnline" class="bg-red-500 text-white text-xs py-1 px-3 text-center font-medium animate-pulse" x-cloak>
        {{ __('Offline Mode - Only cached articles will play') }}
    </div>
    <div class="flex-1 flex flex-col md:flex-row p-6 gap-8 overflow-hidden">
        <!-- Main Player Area -->
        <div class="flex-1 flex flex-col items-center justify-center gap-8">
            <div class="w-full max-w-md aspect-square bg-zinc-200 dark:bg-zinc-800 rounded-2xl shadow-2xl flex items-center justify-center overflow-hidden group relative">
                <flux:icon name="musical-note" size="xl" class="text-zinc-400 dark:text-zinc-600 group-hover:scale-110 transition-transform duration-500" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            </div>

            <div class="w-full max-w-md flex flex-col gap-1 text-center">
                <flux:heading level="1" size="xl" class="truncate" x-text="currentTrack ? (currentTrack.title || currentTrack.url) : '{{ __('Not Playing') }}'"></flux:heading>
                <flux:text variant="subtle" class="truncate" x-text="currentTrack ? currentTrack.url : ''"></flux:text>
            </div>

            <div class="w-full max-w-md flex flex-col gap-2">
                <div class="relative h-1.5 w-full bg-zinc-200 dark:bg-zinc-800 rounded-full cursor-pointer group" @click="seek($event)">
                    <div class="absolute inset-y-0 left-0 bg-zinc-900 dark:bg-zinc-100 rounded-full transition-all duration-100" :style="{ width: progress + '%' }"></div>
                    <div class="absolute top-1/2 -translate-y-1/2 size-3 bg-zinc-900 dark:bg-zinc-100 rounded-full opacity-0 group-hover:opacity-100 transition-opacity shadow-lg" :style="{ left: progress + '%' }"></div>
                </div>
                <div class="flex justify-between text-xs text-zinc-500 font-medium tabular-nums">
                    <span x-text="formatTime(currentTime)">0:00</span>
                    <span x-text="formatTime(duration)">0:00</span>
                </div>
            </div>

            <div class="flex items-center gap-8">
                <button class="p-2 text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100 hover:scale-110 transition-transform disabled:opacity-50 disabled:cursor-not-allowed" @click="prev" x-bind:disabled="currentIndex <= 0">
                    <flux:icon name="backward" />
                </button>
                <button class="size-16 rounded-full bg-zinc-900 dark:bg-zinc-100 text-zinc-100 dark:text-zinc-900 flex items-center justify-center hover:scale-105 active:scale-95 transition-all shadow-xl" @click="togglePlay" x-bind:disabled="!currentTrack">
                    <span x-show="isPlaying" x-cloak>
                        <flux:icon name="pause" size="lg" />
                    </span>
                    <span x-show="!isPlaying">
                        <flux:icon name="play" size="lg" />
                    </span>
                </button>
                <button class="p-2 text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100 hover:scale-110 transition-transform disabled:opacity-50 disabled:cursor-not-allowed" @click="next" x-bind:disabled="currentIndex >= queue.length - 1">
                    <flux:icon name="forward" />
                </button>
            </div>
        </div>

        <!-- Queue Sidebar -->
        <div class="w-full md:w-80 flex flex-col gap-4 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-4 overflow-hidden shadow-sm">
            <flux:heading level="2" size="lg" class="px-2">{{ __('Queue') }}</flux:heading>
            
            <div class="flex-1 overflow-y-auto flex flex-col gap-1 pr-2 custom-scrollbar">
                <template x-for="(track, index) in queue" :key="track.id">
                    <div 
                        class="group flex items-center gap-3 p-3 rounded-xl transition-all cursor-pointer"
                        :class="currentIndex === index ? 'bg-zinc-100 dark:bg-zinc-800 ring-1 ring-zinc-200 dark:ring-zinc-700' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                        @click="playTrack(index)"
                    >
                        <div class="size-10 bg-zinc-200 dark:bg-zinc-700 rounded-lg flex items-center justify-center shrink-0">
                            <flux:icon name="musical-note" size="sm" class="text-zinc-400" />
                        </div>
                        <div class="flex-1 flex flex-col gap-0.5 overflow-hidden">
                            <h3 class="text-sm font-medium truncate" x-bind:class="currentIndex === index ? 'text-zinc-900 dark:text-zinc-50' : 'text-zinc-600 dark:text-zinc-400'" x-text="track.title || track.url"></h3>
                            <p class="text-xs text-zinc-500 truncate" x-text="track.url"></p>
                        </div>
                        <flux:button icon="x-mark" variant="ghost" size="xs" class="opacity-0 group-hover:opacity-100 text-zinc-400 hover:text-red-500" @click.stop="removeFromQueue(index)" />
                    </div>
                </template>
                <div x-show="queue.length === 0" class="flex-1 flex flex-col items-center justify-center gap-2 text-zinc-400 py-12 text-center">
                    <flux:icon name="musical-note" size="lg" />
                    <flux:text variant="subtle">{{ __('Queue is empty') }}</flux:text>
                </div>
            </div>
        </div>
    </div>

@script
<script>
    Alpine.data('player', (token) => ({
        audio: new Audio(),
        isPlaying: false,
        currentTime: 0,
        duration: 0,
        progress: 0,
        queue: [],
        currentIndex: -1,
        currentTrack: null,
        token: token,
        isOnline: navigator.onLine,

        init() {
            window.addEventListener('online', () => this.isOnline = true);
            window.addEventListener('offline', () => this.isOnline = false);

            this.audio.addEventListener('timeupdate', () => {
                this.currentTime = this.audio.currentTime;
                this.progress = (this.currentTime / this.duration) * 100;
                this.updatePositionState();
            });

            this.audio.addEventListener('loadedmetadata', () => {
                this.duration = this.audio.duration;
            });

            this.audio.addEventListener('ended', () => {
                this.next();
            });

            window.addEventListener('play-playlist', async (e) => {
                const response = await fetch(`/api/playlists/${e.detail.playlistId}`, {
                    headers: { 'Authorization': `Bearer ${this.token}` }
                });
                const data = await response.json();
                this.queue = data.playlist.items.map(item => item.article);
                if (this.queue.length > 0) {
                    this.playTrack(0);
                }
            });

            if ('mediaSession' in navigator) {
                navigator.mediaSession.setActionHandler('play', () => { this.audio.play(); this.isPlaying = true; });
                navigator.mediaSession.setActionHandler('pause', () => { this.audio.pause(); this.isPlaying = false; });
                navigator.mediaSession.setActionHandler('previoustrack', () => this.prev());
                navigator.mediaSession.setActionHandler('nexttrack', () => this.next());
                navigator.mediaSession.setActionHandler('seekto', (e) => { if (e.seekTime !== undefined) this.audio.currentTime = e.seekTime; });
            }
        },

        togglePlay() {
            if (!this.currentTrack) return;
            if (this.isPlaying) {
                this.audio.pause();
            } else {
                this.audio.play();
            }
            this.isPlaying = !this.isPlaying;
        },

            async playTrack(index) {
                this.currentIndex = index;
                this.currentTrack = this.queue[index];
                
                // Try to get from cache first
                if (window.AudioCache) {
                    const cachedResponse = await window.AudioCache.getAudio(this.currentTrack.id, this.token);
                    if (cachedResponse) {
                        const blob = await cachedResponse.blob();
                        this.audio.src = URL.createObjectURL(blob);
                    } else {
                        this.audio.src = `/api/articles/${this.currentTrack.id}/audio?token=${this.token}`;
                    }
                } else {
                    this.audio.src = `/api/articles/${this.currentTrack.id}/audio?token=${this.token}`;
                }

                this.audio.play();
                this.isPlaying = true;
                this.updateMetadata();

                if (window.MetadataDB) {
                    window.MetadataDB.set(this.currentTrack);
                }
            },

        next() {
            if (this.currentIndex < this.queue.length - 1) {
                this.playTrack(this.currentIndex + 1);
            }
        },

        prev() {
            if (this.currentIndex > 0) {
                this.playTrack(this.currentIndex - 1);
            }
        },

        seek(e) {
            if (!this.duration) return;
            const rect = e.currentTarget.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const percent = x / rect.width;
            this.audio.currentTime = percent * this.duration;
        },

        removeFromQueue(index) {
            this.queue.splice(index, 1);
            if (index === this.currentIndex) {
                this.audio.pause();
                this.currentTrack = null;
                this.isPlaying = false;
                this.currentIndex = -1;
            } else if (index < this.currentIndex) {
                this.currentIndex--;
            }
        },

        formatTime(seconds) {
            if (!seconds || isNaN(seconds)) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },

        updateMetadata() {
            if ('mediaSession' in navigator && this.currentTrack) {
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: this.currentTrack.title || this.currentTrack.url,
                    artist: 'Utlut',
                    album: 'Articles',
                    artwork: [{ src: '/apple-touch-icon.png', sizes: '180x180', type: 'image/png' }]
                });
            }
        },

        updatePositionState() {
            if ('mediaSession' in navigator && 'setPositionState' in navigator.mediaSession && this.duration > 0) {
                navigator.mediaSession.setPositionState({ duration: this.duration, playbackRate: 1, position: this.currentTime });
            }
        }
    }))
</script>
@endscript

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(0,0,0,0.1);
        border-radius: 10px;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.1);
    }
</style>
</div>

