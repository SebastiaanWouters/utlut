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

<div class="flex h-full flex-col" x-data>
    <!-- Offline Banner -->
    <div x-show="!$store.player.isOnline" class="border-b border-zinc-200 bg-zinc-100 py-2 text-center text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400" x-cloak>
        {{ __('Offline — Playing cached articles only') }}
    </div>

    <div class="flex flex-1 flex-col overflow-hidden lg:flex-row">
        <!-- Main Player Area -->
        <div class="flex flex-1 flex-col items-center justify-center px-6 py-8 md:px-12 md:py-12 lg:py-16">
            <div class="flex w-full max-w-md flex-col items-center gap-8">
                <!-- Album Art -->
                <div class="relative aspect-square w-full max-w-[280px]">
                    <div class="absolute -inset-1 rounded-[28px] bg-gradient-to-br from-zinc-200 to-zinc-300 opacity-50 blur-xl dark:from-zinc-700 dark:to-zinc-800"></div>
                    <div class="relative flex h-full w-full items-center justify-center rounded-[24px] bg-gradient-to-br from-zinc-100 to-zinc-200 shadow-xl dark:from-zinc-700 dark:to-zinc-800">
                        <div class="flex size-24 items-center justify-center rounded-full bg-white/60 dark:bg-black/20">
                            <flux:icon name="musical-note" class="size-12 text-zinc-400 dark:text-zinc-500" />
                        </div>
                    </div>
                </div>

                <!-- Track Info -->
                <div class="flex w-full flex-col gap-1.5 text-center">
                    <h1 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 sm:text-xl" x-text="$store.player.currentTrack ? ($store.player.currentTrack.title || $store.player.currentTrack.url) : '{{ __('Not Playing') }}'"></h1>
                    <p class="truncate text-sm text-zinc-500 dark:text-zinc-400" x-text="$store.player.currentTrack ? $store.player.currentTrack.url : '{{ __('Select an article to play') }}'"></p>
                </div>

                <!-- Progress -->
                <div class="flex w-full flex-col gap-2.5">
                    <div class="group relative h-1.5 w-full cursor-pointer rounded-full bg-zinc-200 dark:bg-zinc-700" @click="$store.player.seek($event)">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-zinc-900 transition-all duration-150 ease-out dark:bg-zinc-100" :style="{ width: $store.player.progress + '%' }"></div>
                        <div class="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-900 opacity-0 shadow-lg ring-4 ring-white transition-opacity duration-150 group-hover:opacity-100 dark:bg-zinc-100 dark:ring-zinc-800" :style="{ left: $store.player.progress + '%' }"></div>
                    </div>
                    <div class="flex justify-between text-xs tabular-nums text-zinc-400 dark:text-zinc-500">
                        <span x-text="$store.player.formatTime($store.player.currentTime)">0:00</span>
                        <span x-text="$store.player.formatTime($store.player.duration)">0:00</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center justify-center gap-6">
                    <button
                        class="flex size-12 items-center justify-center rounded-full text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent dark:hover:bg-zinc-700 dark:hover:text-zinc-100"
                        @click="$store.player.prev()"
                        x-bind:disabled="$store.player.currentIndex <= 0"
                    >
                        <flux:icon name="backward" class="size-6" />
                    </button>
                    <button
                        class="flex size-16 items-center justify-center rounded-full bg-zinc-900 text-white shadow-lg transition-all hover:scale-[1.04] hover:shadow-xl active:scale-[0.98] disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-900"
                        @click="$store.player.togglePlay()"
                        x-bind:disabled="!$store.player.currentTrack"
                    >
                        <span x-show="$store.player.isPlaying" x-cloak>
                            <flux:icon name="pause" class="size-7" />
                        </span>
                        <span x-show="!$store.player.isPlaying">
                            <flux:icon name="play" class="ml-1 size-7" />
                        </span>
                    </button>
                    <button
                        class="flex size-12 items-center justify-center rounded-full text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent dark:hover:bg-zinc-700 dark:hover:text-zinc-100"
                        @click="$store.player.next()"
                        x-bind:disabled="$store.player.currentIndex >= $store.player.queue.length - 1"
                    >
                        <flux:icon name="forward" class="size-6" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Queue Panel -->
        <div class="queue-panel flex w-full flex-col border-t border-zinc-200/80 bg-gradient-to-b from-zinc-50 to-zinc-100/50 dark:border-zinc-700/60 dark:from-zinc-900 dark:to-zinc-900/80 lg:w-80 lg:border-l lg:border-t-0 xl:w-96">
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 sm:px-5 sm:py-4">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-zinc-200/80 dark:bg-zinc-700/60">
                        <flux:icon name="queue-list" class="size-4 text-zinc-500 dark:text-zinc-400" />
                    </div>
                    <h2 class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Up Next') }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex min-w-[1.75rem] items-center justify-center rounded-full bg-zinc-900/5 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-zinc-600 dark:bg-zinc-100/10 dark:text-zinc-400"
                        x-text="$store.player.queue.length + ' ' + ($store.player.queue.length === 1 ? '{{ __('track') }}' : '{{ __('tracks') }}')"
                    ></span>
                    <button
                        x-show="$store.player.queue.length > 0"
                        x-cloak
                        class="rounded-lg p-1.5 text-zinc-400 transition-all duration-200 hover:bg-zinc-200/80 hover:text-zinc-600 active:scale-95 dark:hover:bg-zinc-700/60 dark:hover:text-zinc-300"
                        @click="$store.player.clearQueue()"
                        title="{{ __('Clear queue') }}"
                    >
                        <flux:icon name="trash" class="size-4" />
                    </button>
                </div>
            </div>

            <!-- Divider -->
            <div class="mx-4 h-px bg-gradient-to-r from-transparent via-zinc-200 to-transparent dark:via-zinc-700/60 sm:mx-5"></div>

            <!-- Queue List -->
            <div class="queue-scrollbar flex-1 overflow-y-auto overflow-x-hidden px-2 py-2 sm:px-3 sm:py-3">
                <div class="space-y-1">
                    <template x-for="(track, index) in $store.player.queue" :key="track.id">
                        <div
                            class="queue-item group relative flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 transition-all duration-200 sm:gap-3.5 sm:px-3.5 sm:py-3"
                            :class="($store.player.currentIndex === index)
                                ? 'queue-item-active bg-white shadow-sm ring-1 ring-zinc-900/5 dark:bg-zinc-800 dark:ring-zinc-100/5'
                                : 'hover:bg-white/70 active:bg-white active:scale-[0.99] dark:hover:bg-zinc-800/50 dark:active:bg-zinc-800/70'"
                            @click="$store.player.playTrack(index)"
                        >
                            <!-- Track Number / Playing Indicator -->
                            <div
                                class="relative flex size-9 shrink-0 items-center justify-center rounded-lg text-xs font-semibold transition-all duration-200 sm:size-10"
                                :class="($store.player.currentIndex === index)
                                    ? 'bg-zinc-900 text-white shadow-md dark:bg-zinc-100 dark:text-zinc-900'
                                    : 'bg-zinc-100 text-zinc-500 group-hover:bg-zinc-200/80 dark:bg-zinc-700/60 dark:text-zinc-400 dark:group-hover:bg-zinc-700'"
                            >
                                <!-- Playing Animation -->
                                <template x-if="$store.player.currentIndex === index && $store.player.isPlaying">
                                    <div class="flex items-end gap-[3px]">
                                        <span class="eq-bar h-2.5 w-[3px] origin-bottom rounded-full bg-current"></span>
                                        <span class="eq-bar animation-delay-100 h-4 w-[3px] origin-bottom rounded-full bg-current"></span>
                                        <span class="eq-bar animation-delay-200 h-3 w-[3px] origin-bottom rounded-full bg-current"></span>
                                    </div>
                                </template>
                                <!-- Paused Icon for Current Track -->
                                <template x-if="$store.player.currentIndex === index && !$store.player.isPlaying">
                                    <flux:icon name="pause" class="size-4" />
                                </template>
                                <!-- Track Number -->
                                <template x-if="$store.player.currentIndex !== index">
                                    <span x-text="index + 1" class="tabular-nums"></span>
                                </template>
                            </div>

                            <!-- Track Info -->
                            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span
                                    class="line-clamp-1 text-[13px] font-medium leading-tight text-zinc-900 transition-colors sm:text-sm dark:text-zinc-100"
                                    :class="$store.player.currentIndex === index ? 'text-zinc-900 dark:text-zinc-100' : 'group-hover:text-zinc-900 dark:group-hover:text-zinc-100'"
                                    x-text="track.title || track.url"
                                ></span>
                                <span
                                    class="line-clamp-1 text-[11px] text-zinc-400 transition-colors sm:text-xs dark:text-zinc-500"
                                    x-text="$store.player.getHostname(track.url)"
                                ></span>
                            </div>

                            <!-- Remove Button -->
                            <button
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 opacity-0 transition-all duration-200 hover:bg-zinc-100 hover:text-red-500 group-hover:opacity-100 focus:opacity-100 active:scale-95 dark:hover:bg-zinc-700 dark:hover:text-red-400 sm:size-9"
                                :class="$store.player.currentIndex === index ? 'opacity-70' : ''"
                                @click.stop="$store.player.removeFromQueue(index)"
                                title="{{ __('Remove from queue') }}"
                            >
                                <flux:icon name="x-mark" class="size-4" />
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Empty State -->
                <div
                    x-show="$store.player.queue.length === 0"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="flex h-full min-h-[200px] flex-col items-center justify-center gap-4 px-4 py-12 sm:min-h-[280px] sm:py-16"
                >
                    <div class="relative">
                        <div class="absolute -inset-3 rounded-3xl bg-gradient-to-br from-zinc-200/50 to-zinc-300/30 blur-xl dark:from-zinc-700/30 dark:to-zinc-800/20"></div>
                        <div class="relative flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-zinc-100 to-zinc-200/80 shadow-sm ring-1 ring-zinc-900/5 dark:from-zinc-700 dark:to-zinc-800 dark:ring-zinc-100/5 sm:size-20">
                            <flux:icon name="queue-list" class="size-8 text-zinc-400 dark:text-zinc-500 sm:size-9" />
                        </div>
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 sm:text-base">{{ __('Your queue is empty') }}</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-500 sm:text-sm">{{ __('Add articles from your library to get started') }}</p>
                    </div>
                    <a
                        href="{{ route('library') }}"
                        class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-zinc-900 px-4 py-2 text-xs font-medium text-white shadow-sm transition-all duration-200 hover:bg-zinc-800 hover:shadow-md active:scale-[0.98] dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200 sm:text-sm"
                    >
                        <flux:icon name="plus" class="size-4" />
                        {{ __('Browse Library') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Custom scrollbar for main content */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.08); border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.12); }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.12); }

        /* Queue panel scrollbar */
        .queue-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: transparent transparent;
        }
        .queue-scrollbar:hover {
            scrollbar-color: rgba(0,0,0,0.15) transparent;
        }
        .dark .queue-scrollbar:hover {
            scrollbar-color: rgba(255,255,255,0.15) transparent;
        }
        .queue-scrollbar::-webkit-scrollbar { width: 5px; }
        .queue-scrollbar::-webkit-scrollbar-track { background: transparent; margin: 8px 0; }
        .queue-scrollbar::-webkit-scrollbar-thumb {
            background: transparent;
            border-radius: 10px;
            transition: background 0.2s ease;
        }
        .queue-scrollbar:hover::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); }
        .queue-scrollbar:hover::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.25); }
        .dark .queue-scrollbar:hover::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); }
        .dark .queue-scrollbar:hover::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

        /* Equalizer animation for playing track */
        @keyframes eq {
            0%, 100% { transform: scaleY(0.4); }
            50% { transform: scaleY(1); }
        }

        .eq-bar {
            animation: eq 0.8s ease-in-out infinite;
        }
        .eq-bar.animation-delay-100 {
            animation-delay: 0.15s;
        }
        .eq-bar.animation-delay-200 {
            animation-delay: 0.3s;
        }

        /* Queue item animations */
        .queue-item {
            animation: queue-item-in 0.25s ease-out;
        }
        @keyframes queue-item-in {
            from {
                opacity: 0;
                transform: translateX(-8px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Active item subtle glow */
        .queue-item-active {
            animation: active-pulse 2s ease-in-out infinite;
        }
        @keyframes active-pulse {
            0%, 100% { box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
            50% { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        }
        .dark .queue-item-active {
            animation: active-pulse-dark 2s ease-in-out infinite;
        }
        @keyframes active-pulse-dark {
            0%, 100% { box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
            50% { box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        }

        /* Smooth transitions for queue panel on responsive */
        .queue-panel {
            transition: width 0.3s ease, border 0.2s ease;
        }

        /* Better touch targets on mobile */
        @media (max-width: 640px) {
            .queue-item {
                min-height: 56px;
            }
        }

        /* Landscape mobile optimization */
        @media (max-height: 500px) and (orientation: landscape) {
            .queue-panel {
                max-height: 40vh;
            }
        }
    </style>
</div>

