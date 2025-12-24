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

<div class="flex h-full flex-col" x-data="{
    draggedIndex: null,
    dragOverIndex: null,
    hoverTime: null,
    hoverPosition: 0,
    showHoverTime: false,

    // Queue Sheet State
    showQueueSheet: false,
    sheetTouchStartY: 0,
    sheetCurrentY: 0,
    sheetTranslateY: 0,
    isDraggingSheet: false,

    handleDragStart(e, index) {
        this.draggedIndex = index;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', index);
        e.target.classList.add('opacity-50');
    },

    handleDragEnd(e) {
        e.target.classList.remove('opacity-50');
        this.draggedIndex = null;
        this.dragOverIndex = null;
    },

    handleDragOver(e, index) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        this.dragOverIndex = index;
    },

    handleDragLeave(e) {
        this.dragOverIndex = null;
    },

    handleDrop(e, toIndex) {
        e.preventDefault();
        const fromIndex = this.draggedIndex;
        if (fromIndex !== null && fromIndex !== toIndex) {
            $store.player.moveInQueue(fromIndex, toIndex);
        }
        this.draggedIndex = null;
        this.dragOverIndex = null;
    },

    updateHoverTime(e) {
        const rect = e.currentTarget.getBoundingClientRect();
        const percent = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        this.hoverPosition = percent * 100;
        this.hoverTime = $store.player.formatTime(percent * $store.player.duration);
    },

    // Sheet Touch Handlers
    handleSheetTouchStart(e) {
        this.sheetTouchStartY = e.touches[0].clientY;
        this.isDraggingSheet = true;
    },

    handleSheetTouchMove(e) {
        if (!this.isDraggingSheet) return;
        const deltaY = e.touches[0].clientY - this.sheetTouchStartY;
        // Only allow dragging down
        this.sheetTranslateY = Math.max(0, deltaY);
    },

    handleSheetTouchEnd() {
        this.isDraggingSheet = false;
        // If dragged more than 150px, close the sheet
        if (this.sheetTranslateY > 150) {
            this.showQueueSheet = false;
        }
        this.sheetTranslateY = 0;
    }
}">
    <!-- Offline Banner -->
    <div x-show="!$store.player.isOnline" class="border-b border-zinc-200 bg-zinc-100 py-2 text-center text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400" x-cloak>
        {{ __('Offline — Playing cached articles only') }}
    </div>

    <div class="flex flex-1 flex-col overflow-hidden lg:flex-row">
        <!-- Main Player Area -->
        <div class="flex min-h-0 flex-1 flex-col items-center justify-center px-4 py-4 md:px-6 md:py-10 lg:py-14">
            <div class="flex w-full max-w-md flex-col items-center gap-6">
                <!-- Album Art -->
                <div class="relative aspect-square w-full max-w-[180px]">
                    <div class="absolute -inset-1 rounded-[22px] bg-gradient-to-br from-zinc-200 to-zinc-300 opacity-50 blur-xl dark:from-zinc-700 dark:to-zinc-800"></div>
                    <div class="relative flex h-full w-full items-center justify-center rounded-[18px] bg-gradient-to-br from-zinc-100 to-zinc-200 shadow-xl dark:from-zinc-700 dark:to-zinc-800">
                        <!-- Loading overlay -->
                        <div
                            x-show="$store.player.isLoading"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="absolute inset-0 z-10 flex items-center justify-center rounded-[18px] bg-white/60 backdrop-blur-sm dark:bg-black/40"
                            x-cloak
                        >
                            <div class="size-10 animate-spin rounded-full border-[3px] border-zinc-300 border-t-zinc-900 dark:border-zinc-600 dark:border-t-zinc-100"></div>
                        </div>
                        <div class="flex size-16 items-center justify-center rounded-full bg-white/60 dark:bg-black/20">
                            <flux:icon name="musical-note" class="size-8 text-zinc-400 dark:text-zinc-500" />
                        </div>
                    </div>
                </div>

                <!-- Track Info -->
                <div class="flex w-full flex-col gap-1.5 text-center">
                    <h1 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 sm:text-xl" x-text="$store.player.currentTrack ? ($store.player.currentTrack.title || $store.player.currentTrack.url) : '{{ __('Not Playing') }}'"></h1>
                    <p class="truncate text-sm text-zinc-500 dark:text-zinc-400" x-text="$store.player.currentTrack ? $store.player.getHostname($store.player.currentTrack.url) : '{{ __('Select an article to play') }}'"></p>
                </div>

                <!-- Progress -->
                <div class="flex w-full flex-col gap-2">
                    <div
                        class="group relative h-2 w-full cursor-pointer rounded-full bg-zinc-200 dark:bg-zinc-700"
                        @click="$store.player.seek($event)"
                        @mouseenter="showHoverTime = true"
                        @mouseleave="showHoverTime = false"
                        @mousemove="updateHoverTime($event)"
                    >
                        <!-- Buffered -->
                        <div class="absolute inset-y-0 left-0 rounded-full bg-zinc-300 transition-all duration-300 dark:bg-zinc-600" :style="{ width: $store.player.buffered + '%' }"></div>
                        <!-- Progress -->
                        <div class="absolute inset-y-0 left-0 rounded-full bg-zinc-900 transition-all duration-150 ease-out dark:bg-zinc-100" :style="{ width: $store.player.progress + '%' }"></div>
                        <!-- Scrubber -->
                        <div
                            class="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-900 opacity-0 shadow-lg ring-4 ring-white transition-all duration-150 group-hover:opacity-100 group-hover:scale-110 dark:bg-zinc-100 dark:ring-zinc-800"
                            :style="{ left: $store.player.progress + '%' }"
                        ></div>
                        <!-- Hover Time Tooltip -->
                        <div
                            x-show="showHoverTime && $store.player.duration > 0"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="pointer-events-none absolute -top-9 -translate-x-1/2 rounded-md bg-zinc-900 px-2 py-1 text-xs font-medium tabular-nums text-white shadow-lg dark:bg-zinc-100 dark:text-zinc-900"
                            :style="{ left: hoverPosition + '%' }"
                            x-text="hoverTime"
                            x-cloak
                        ></div>
                    </div>
                    <div class="flex items-center justify-between text-xs tabular-nums text-zinc-400 dark:text-zinc-500">
                        <span x-text="$store.player.formatTime($store.player.currentTime)">0:00</span>
                        <!-- Speed Control -->
                        <flux:dropdown position="top" align="center">
                            <button class="flex items-center gap-1 rounded-md px-2 py-0.5 font-medium transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300">
                                <span x-text="$store.player.playbackRate + 'x'">1x</span>
                                <flux:icon name="chevron-up-down" class="size-3 opacity-50" />
                            </button>
                            <flux:menu class="min-w-[80px]">
                                <flux:menu.item @click="$store.player.setPlaybackRate(0.5)">0.5x</flux:menu.item>
                                <flux:menu.item @click="$store.player.setPlaybackRate(0.75)">0.75x</flux:menu.item>
                                <flux:menu.item @click="$store.player.setPlaybackRate(1)">1x</flux:menu.item>
                                <flux:menu.item @click="$store.player.setPlaybackRate(1.25)">1.25x</flux:menu.item>
                                <flux:menu.item @click="$store.player.setPlaybackRate(1.5)">1.5x</flux:menu.item>
                                <flux:menu.item @click="$store.player.setPlaybackRate(1.75)">1.75x</flux:menu.item>
                                <flux:menu.item @click="$store.player.setPlaybackRate(2)">2x</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                        <span x-text="$store.player.formatTime($store.player.duration)">0:00</span>
                    </div>
                </div>

                <!-- Main Controls -->
                <div class="flex items-center justify-center gap-3">
                    <!-- Previous Track -->
                    <button
                        class="flex size-11 items-center justify-center rounded-full text-zinc-400 transition-all duration-200 hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent dark:hover:bg-zinc-700 dark:hover:text-zinc-100"
                        @click="$store.player.prev()"
                        x-bind:disabled="$store.player.currentIndex <= 0 && !$store.player.shuffleEnabled"
                        title="{{ __('Previous') }}"
                    >
                        <flux:icon name="backward" class="size-5" />
                    </button>

                    <!-- Skip Back 15s -->
                    <button
                        class="relative flex size-10 items-center justify-center rounded-full text-zinc-400 transition-all duration-200 hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-zinc-700 dark:hover:text-zinc-100"
                        @click="$store.player.skipBackward(15)"
                        x-bind:disabled="!$store.player.currentTrack"
                        title="{{ __('Back 15 seconds') }}"
                    >
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 1 0 9-9"/>
                            <path d="M3 7v5h5"/>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-[9px] font-bold">15</span>
                    </button>

                    <!-- Play/Pause -->
                    <button
                        class="relative flex size-16 items-center justify-center rounded-full bg-zinc-900 text-white shadow-lg transition-all duration-200 hover:scale-[1.04] hover:shadow-xl active:scale-[0.98] disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-900"
                        @click="$store.player.togglePlay()"
                        x-bind:disabled="!$store.player.currentTrack"
                    >
                        <!-- Loading Spinner -->
                        <span x-show="$store.player.isLoading" class="absolute inset-0 flex items-center justify-center" x-cloak>
                            <div class="size-6 animate-spin rounded-full border-2 border-white/30 border-t-white dark:border-zinc-900/30 dark:border-t-zinc-900"></div>
                        </span>
                        <!-- Pause Icon -->
                        <span x-show="$store.player.isPlaying && !$store.player.isLoading" x-cloak>
                            <flux:icon name="pause" class="size-7" />
                        </span>
                        <!-- Play Icon -->
                        <span x-show="!$store.player.isPlaying && !$store.player.isLoading">
                            <flux:icon name="play" class="ml-1 size-7" />
                        </span>
                    </button>

                    <!-- Skip Forward 15s -->
                    <button
                        class="relative flex size-10 items-center justify-center rounded-full text-zinc-400 transition-all duration-200 hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-zinc-700 dark:hover:text-zinc-100"
                        @click="$store.player.skipForward(15)"
                        x-bind:disabled="!$store.player.currentTrack"
                        title="{{ __('Forward 15 seconds') }}"
                    >
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 1 1-9-9"/>
                            <path d="M21 7v5h-5"/>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-[9px] font-bold">15</span>
                    </button>

                    <!-- Next Track -->
                    <button
                        class="flex size-11 items-center justify-center rounded-full text-zinc-400 transition-all duration-200 hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent dark:hover:bg-zinc-700 dark:hover:text-zinc-100"
                        @click="$store.player.next()"
                        x-bind:disabled="$store.player.currentIndex >= $store.player.queue.length - 1 && $store.player.repeatMode === 'off' && !$store.player.shuffleEnabled"
                        title="{{ __('Next') }}"
                    >
                        <flux:icon name="forward" class="size-5" />
                    </button>
                </div>

                <!-- Secondary Controls: Shuffle & Repeat -->
                <div class="flex items-center justify-center gap-4">
                    <!-- Shuffle -->
                    <button
                        class="flex size-10 items-center justify-center rounded-full transition-all duration-200"
                        :class="$store.player.shuffleEnabled
                            ? 'bg-zinc-900 text-white shadow-md dark:bg-zinc-100 dark:text-zinc-900'
                            : 'text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300'"
                        @click="$store.player.toggleShuffle()"
                        title="{{ __('Shuffle') }}"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 3h5v5"/>
                            <path d="M4 20L21 3"/>
                            <path d="M21 16v5h-5"/>
                            <path d="M15 15l6 6"/>
                            <path d="M4 4l5 5"/>
                        </svg>
                    </button>

                    <!-- Repeat -->
                    <button
                        class="relative flex size-10 items-center justify-center rounded-full transition-all duration-200"
                        :class="$store.player.repeatMode !== 'off'
                            ? 'bg-zinc-900 text-white shadow-md dark:bg-zinc-100 dark:text-zinc-900'
                            : 'text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300'"
                        @click="$store.player.cycleRepeatMode()"
                        :title="$store.player.repeatMode === 'off' ? '{{ __('Repeat off') }}' : ($store.player.repeatMode === 'all' ? '{{ __('Repeat all') }}' : '{{ __('Repeat one') }}')"
                    >
                        <!-- Repeat All / Off Icon -->
                        <svg x-show="$store.player.repeatMode !== 'one'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m17 2 4 4-4 4"/>
                            <path d="M3 11v-1a4 4 0 0 1 4-4h14"/>
                            <path d="m7 22-4-4 4-4"/>
                            <path d="M21 13v1a4 4 0 0 1-4 4H3"/>
                        </svg>
                        <!-- Repeat One Icon -->
                        <svg x-show="$store.player.repeatMode === 'one'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x-cloak>
                            <path d="m17 2 4 4-4 4"/>
                            <path d="M3 11v-1a4 4 0 0 1 4-4h14"/>
                            <path d="m7 22-4-4 4-4"/>
                            <path d="M21 13v1a4 4 0 0 1-4 4H3"/>
                            <path d="M11 10h1v4"/>
                        </svg>
                        <!-- "1" badge for repeat one -->
                        <span
                            x-show="$store.player.repeatMode === 'one'"
                            class="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-zinc-100 text-[10px] font-bold text-zinc-900 ring-2 ring-white dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800"
                            x-cloak
                        >1</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Queue Panel (Desktop Only) -->
        <div class="queue-panel hidden min-h-0 flex-col border-zinc-200/50 bg-zinc-50/80 backdrop-blur-sm dark:border-zinc-700/40 dark:bg-zinc-800/50 lg:flex lg:w-80 lg:rounded-tl-2xl lg:border-l xl:w-96">
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 sm:px-5 sm:py-4">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-zinc-200/60 dark:bg-zinc-700/50">
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
            <div class="queue-scrollbar min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-2 pb-[env(safe-area-inset-bottom)] pt-2 sm:px-3 sm:pt-3">
                <div class="space-y-1">
                    <template x-for="(track, index) in $store.player.queue" :key="track.id">
                        <div
                            class="queue-item group relative flex items-center gap-2 rounded-xl px-2 py-2.5 transition-all duration-200 sm:gap-3 sm:px-3 sm:py-3"
                            :class="[
                                !$store.player.isArticleReady(track) ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                                ($store.player.currentIndex === index)
                                    ? 'queue-item-active bg-white shadow-sm ring-1 ring-zinc-900/5 dark:bg-zinc-800 dark:ring-zinc-100/5'
                                    : ($store.player.isArticleReady(track) ? 'hover:bg-white/70 active:bg-white dark:hover:bg-zinc-800/50 dark:active:bg-zinc-800/70' : ''),
                                dragOverIndex === index && draggedIndex !== index ? 'ring-2 ring-zinc-400 dark:ring-zinc-500' : ''
                            ]"
                            @click="$store.player.isArticleReady(track) && $store.player.playTrack(index)"
                            draggable="true"
                            @dragstart="handleDragStart($event, index)"
                            @dragend="handleDragEnd($event)"
                            @dragover="handleDragOver($event, index)"
                            @dragleave="handleDragLeave($event)"
                            @drop="handleDrop($event, index)"
                        >
                            <!-- Drag Handle -->
                            <div
                                class="flex size-6 shrink-0 cursor-grab items-center justify-center rounded text-zinc-300 opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing dark:text-zinc-600 sm:size-7"
                                @click.stop
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="9" cy="6" r="1.5"/>
                                    <circle cx="15" cy="6" r="1.5"/>
                                    <circle cx="9" cy="12" r="1.5"/>
                                    <circle cx="15" cy="12" r="1.5"/>
                                    <circle cx="9" cy="18" r="1.5"/>
                                    <circle cx="15" cy="18" r="1.5"/>
                                </svg>
                            </div>

                            <!-- Track Number / Playing Indicator -->
                            <div
                                class="relative flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-semibold transition-all duration-200 sm:size-9"
                                :class="!$store.player.isArticleReady(track)
                                    ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'
                                    : ($store.player.currentIndex === index)
                                        ? 'bg-zinc-900 text-white shadow-md dark:bg-zinc-100 dark:text-zinc-900'
                                        : 'bg-zinc-100 text-zinc-500 group-hover:bg-zinc-200/80 dark:bg-zinc-700/60 dark:text-zinc-400 dark:group-hover:bg-zinc-700'"
                            >
                                <!-- Processing Spinner for non-ready tracks -->
                                <template x-if="!$store.player.isArticleReady(track)">
                                    <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </template>
                                <!-- Playing Animation -->
                                <template x-if="$store.player.isArticleReady(track) && $store.player.currentIndex === index && $store.player.isPlaying">
                                    <div class="flex items-end gap-[3px]">
                                        <span class="eq-bar h-2 w-[3px] origin-bottom rounded-full bg-current"></span>
                                        <span class="eq-bar animation-delay-100 h-3 w-[3px] origin-bottom rounded-full bg-current"></span>
                                        <span class="eq-bar animation-delay-200 h-2.5 w-[3px] origin-bottom rounded-full bg-current"></span>
                                    </div>
                                </template>
                                <!-- Paused Icon for Current Track -->
                                <template x-if="$store.player.isArticleReady(track) && $store.player.currentIndex === index && !$store.player.isPlaying">
                                    <flux:icon name="pause" class="size-3.5" />
                                </template>
                                <!-- Track Number -->
                                <template x-if="$store.player.isArticleReady(track) && $store.player.currentIndex !== index">
                                    <span x-text="index + 1" class="tabular-nums"></span>
                                </template>
                            </div>

                            <!-- Track Info -->
                            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="line-clamp-1 text-[13px] font-medium leading-tight transition-colors sm:text-sm"
                                        :class="!$store.player.isArticleReady(track)
                                            ? 'text-zinc-400 dark:text-zinc-500'
                                            : ($store.player.currentIndex === index ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-900 group-hover:text-zinc-900 dark:text-zinc-100 dark:group-hover:text-zinc-100')"
                                        x-text="track.title || track.url"
                                    ></span>
                                    <span
                                        x-show="!$store.player.isArticleReady(track)"
                                        class="shrink-0 rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
                                        x-cloak
                                    >{{ __('Processing') }}</span>
                                </div>
                                <span
                                    class="line-clamp-1 text-[11px] text-zinc-400 transition-colors sm:text-xs dark:text-zinc-500"
                                    x-text="$store.player.getHostname(track.url)"
                                ></span>
                            </div>

                            <!-- Remove Button -->
                            <button
                                class="flex size-7 shrink-0 items-center justify-center rounded-lg text-zinc-400 opacity-0 transition-all duration-200 hover:bg-zinc-100 hover:text-red-500 group-hover:opacity-100 focus:opacity-100 active:scale-95 dark:hover:bg-zinc-700 dark:hover:text-red-400 sm:size-8"
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

    {{-- Mobile Queue FAB Button --}}
    <button
        x-show="$store.player.queue.length > 0"
        x-cloak
        @click="showQueueSheet = true"
        class="fixed bottom-6 right-4 z-40 flex items-center gap-2.5 rounded-full bg-zinc-900 py-3 pl-4 pr-5 text-white shadow-xl transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl active:scale-[0.98] lg:hidden dark:bg-white dark:text-zinc-900"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
    >
        <div class="relative">
            <flux:icon name="queue-list" class="size-5" />
            {{-- Playing indicator dot --}}
            <span
                x-show="$store.player.isPlaying"
                class="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-emerald-400 dark:bg-emerald-500"
            >
                <span class="absolute inset-0 animate-ping rounded-full bg-emerald-400 opacity-75"></span>
            </span>
        </div>
        <span class="text-sm font-semibold tabular-nums" x-text="$store.player.queue.length + ' ' + ($store.player.queue.length === 1 ? '{{ __('track') }}' : '{{ __('tracks') }}')"></span>
    </button>

    {{-- Mobile Queue Bottom Sheet --}}
    <div
        x-show="showQueueSheet"
        x-cloak
        class="fixed inset-0 z-50 lg:hidden"
        @keydown.escape.window="showQueueSheet = false"
    >
        {{-- Backdrop --}}
        <div
            x-show="showQueueSheet"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="showQueueSheet = false"
            class="absolute inset-0 bg-black/60 backdrop-blur-sm"
        ></div>

        {{-- Sheet --}}
        <div
            x-show="showQueueSheet"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            @touchstart.passive="handleSheetTouchStart($event)"
            @touchmove.passive="handleSheetTouchMove($event)"
            @touchend="handleSheetTouchEnd()"
            :style="{ transform: sheetTranslateY > 0 ? `translateY(${sheetTranslateY}px)` : '' }"
            class="absolute inset-x-0 bottom-0 flex max-h-[85vh] flex-col overflow-hidden rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl transition-transform dark:bg-zinc-900"
            :class="{ 'duration-0': isDraggingSheet, 'duration-300': !isDraggingSheet }"
        >
            {{-- Drag Handle --}}
            <div class="flex flex-col items-center pt-3 pb-2">
                <div class="h-1.5 w-12 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
            </div>

            {{-- Sheet Header --}}
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 pb-4 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="queue-list" class="size-5 text-zinc-600 dark:text-zinc-400" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Up Next') }}</h2>
                        <p class="text-xs text-zinc-500" x-text="$store.player.queue.length + ' ' + ($store.player.queue.length === 1 ? '{{ __('track') }}' : '{{ __('tracks') }}')"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        x-show="$store.player.queue.length > 0"
                        @click="$store.player.clearQueue()"
                        class="flex size-10 items-center justify-center rounded-xl text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-red-500 dark:hover:bg-zinc-800 dark:hover:text-red-400"
                    >
                        <flux:icon name="trash" class="size-5" />
                    </button>
                    <button
                        @click="showQueueSheet = false"
                        class="flex size-10 items-center justify-center rounded-xl text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                    >
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>
            </div>

            {{-- Sheet Queue List --}}
            <div class="flex-1 overflow-y-auto overscroll-contain px-3 py-3">
                <div class="space-y-1">
                    <template x-for="(track, index) in $store.player.queue" :key="'sheet-' + track.id">
                        <div
                            class="group flex items-center gap-3 rounded-2xl px-3 py-3 transition-all duration-200"
                            :class="[
                                !$store.player.isArticleReady(track) ? 'opacity-60' : 'cursor-pointer active:bg-zinc-100 dark:active:bg-zinc-800',
                                ($store.player.currentIndex === index)
                                    ? 'bg-zinc-100 dark:bg-zinc-800'
                                    : ''
                            ]"
                            @click="$store.player.isArticleReady(track) && $store.player.playTrack(index)"
                        >
                            {{-- Track Number / Playing Indicator --}}
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl text-sm font-semibold"
                                :class="!$store.player.isArticleReady(track)
                                    ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'
                                    : ($store.player.currentIndex === index)
                                        ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                        : 'bg-zinc-200/60 text-zinc-500 dark:bg-zinc-700/60 dark:text-zinc-400'"
                            >
                                {{-- Processing Spinner --}}
                                <template x-if="!$store.player.isArticleReady(track)">
                                    <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </template>
                                {{-- Playing Animation --}}
                                <template x-if="$store.player.isArticleReady(track) && $store.player.currentIndex === index && $store.player.isPlaying">
                                    <div class="flex items-end gap-[3px]">
                                        <span class="eq-bar h-2 w-[3px] origin-bottom rounded-full bg-current"></span>
                                        <span class="eq-bar animation-delay-100 h-3 w-[3px] origin-bottom rounded-full bg-current"></span>
                                        <span class="eq-bar animation-delay-200 h-2.5 w-[3px] origin-bottom rounded-full bg-current"></span>
                                    </div>
                                </template>
                                {{-- Paused Icon --}}
                                <template x-if="$store.player.isArticleReady(track) && $store.player.currentIndex === index && !$store.player.isPlaying">
                                    <flux:icon name="pause" class="size-4" />
                                </template>
                                {{-- Track Number --}}
                                <template x-if="$store.player.isArticleReady(track) && $store.player.currentIndex !== index">
                                    <span x-text="index + 1" class="tabular-nums"></span>
                                </template>
                            </div>

                            {{-- Track Info --}}
                            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span
                                    class="line-clamp-1 text-sm font-medium"
                                    :class="$store.player.currentIndex === index ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-700 dark:text-zinc-300'"
                                    x-text="track.title || track.url"
                                ></span>
                                <span class="line-clamp-1 text-xs text-zinc-500 dark:text-zinc-500" x-text="$store.player.getHostname(track.url)"></span>
                            </div>

                            {{-- Remove Button --}}
                            <button
                                @click.stop="$store.player.removeFromQueue(index)"
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl text-zinc-400 transition-colors hover:bg-zinc-200 hover:text-red-500 dark:hover:bg-zinc-700 dark:hover:text-red-400"
                            >
                                <flux:icon name="x-mark" class="size-4" />
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Empty State --}}
                <div
                    x-show="$store.player.queue.length === 0"
                    class="flex flex-col items-center justify-center gap-4 py-16"
                >
                    <div class="flex size-16 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="queue-list" class="size-8 text-zinc-400" />
                    </div>
                    <div class="text-center">
                        <p class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Your queue is empty') }}</p>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('Add articles from your library') }}</p>
                    </div>
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

        /* Drag and drop styling */
        .queue-item[draggable="true"] {
            touch-action: none;
        }

        .queue-item.dragging {
            opacity: 0.5;
        }
    </style>
</div>
