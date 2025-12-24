<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="h-screen overflow-hidden bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="pt-[env(safe-area-inset-top)] border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('library') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item icon="book-open" :href="route('library')" :current="request()->routeIs('library')" wire:navigate>{{ __('Library') }}</flux:navlist.item>
                    <flux:navlist.item icon="play" :href="route('player')" :current="request()->routeIs('player')" wire:navigate>{{ __('Now Playing') }}</flux:navlist.item>
                    <flux:navlist.item icon="musical-note" :href="route('playlists.index')" :current="request()->routeIs('playlists.*')" wire:navigate>{{ __('Playlists') }}</flux:navlist.item>
                    <flux:navlist.item icon="cog" :href="route('setup')" :current="request()->routeIs('setup')" wire:navigate>{{ __('Setup') }}</flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                    data-test="sidebar-menu-button"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="!px-4 pt-[env(safe-area-inset-top)] lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end" class="-mr-2">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{-- PWA Install Prompt --}}
        <div
            x-data="{
                deferredPrompt: null,
                dismissed: localStorage.getItem('pwa-install-dismissed') === 'true',
                isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone,
                isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
                show: false,
                init() {
                    if (this.dismissed || this.isStandalone) return;

                    if (this.isIOS) {
                        this.show = true;
                        return;
                    }

                    window.addEventListener('beforeinstallprompt', (e) => {
                        e.preventDefault();
                        this.deferredPrompt = e;
                        this.show = true;
                    });
                },
                async install() {
                    if (this.isIOS) {
                        this.dismiss();
                        return;
                    }
                    if (!this.deferredPrompt) return;
                    this.deferredPrompt.prompt();
                    const { outcome } = await this.deferredPrompt.userChoice;
                    if (outcome === 'accepted') {
                        this.show = false;
                    }
                    this.deferredPrompt = null;
                },
                dismiss() {
                    this.show = false;
                    this.dismissed = true;
                    localStorage.setItem('pwa-install-dismissed', 'true');
                }
            }"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            x-cloak
            class="fixed bottom-0 left-4 right-4 z-40 pb-[calc(1rem+env(safe-area-inset-bottom))] lg:bottom-4 lg:left-auto lg:right-4 lg:w-[340px] lg:pb-0"
        >
            <div class="group relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-zinc-900 via-zinc-900 to-zinc-800 p-5 shadow-2xl shadow-black/40 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950">
                {{-- Animated audio wave background --}}
                <div class="pointer-events-none absolute inset-0 overflow-hidden opacity-20">
                    <div class="absolute -bottom-2 left-0 right-0 flex h-16 items-end justify-around gap-1 px-4">
                        @for ($i = 0; $i < 24; $i++)
                            <div
                                class="w-1.5 rounded-full bg-gradient-to-t from-violet-500 to-fuchsia-400"
                                style="height: {{ rand(20, 100) }}%; animation: pwa-wave {{ 0.4 + ($i * 0.05) }}s ease-in-out infinite alternate; animation-delay: {{ $i * 0.05 }}s;"
                            ></div>
                        @endfor
                    </div>
                </div>

                {{-- Gradient orb accent --}}
                <div class="pointer-events-none absolute -right-8 -top-8 size-32 rounded-full bg-gradient-to-br from-violet-600/30 via-fuchsia-500/20 to-transparent blur-2xl"></div>

                {{-- Content --}}
                <div class="relative flex items-start gap-4">
                    {{-- App icon --}}
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 to-fuchsia-500 shadow-lg shadow-violet-500/25">
                        <flux:icon.musical-note class="size-7 text-white" />
                    </div>

                    <div class="flex-1 pt-0.5">
                        <h3 class="text-base font-semibold tracking-tight text-white">{{ __('Get the Sundo App') }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-zinc-400">{{ __('Offline playback & lock screen controls') }}</p>
                    </div>

                    {{-- Close button --}}
                    <button
                        @click="dismiss()"
                        class="absolute -right-1 -top-1 rounded-full p-1.5 text-zinc-500 transition-all hover:bg-white/10 hover:text-zinc-300"
                    >
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>

                {{-- Actions --}}
                <div class="relative mt-4 flex items-center gap-3">
                    <template x-if="isIOS">
                        <div class="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 text-sm text-zinc-300">
                            <flux:icon.arrow-up-on-square class="size-4 text-violet-400" />
                            <span>{{ __('Tap Share → Add to Home') }}</span>
                        </div>
                    </template>
                    <template x-if="!isIOS">
                        <button
                            @click="install()"
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/25 transition-all hover:scale-[1.02] hover:shadow-xl hover:shadow-violet-500/30 active:scale-[0.98]"
                        >
                            <flux:icon.arrow-down-tray class="size-4" />
                            {{ __('Install Now') }}
                        </button>
                    </template>
                    <button
                        @click="dismiss()"
                        class="px-3 py-2.5 text-sm font-medium text-zinc-500 transition-colors hover:text-zinc-300"
                    >
                        <span x-text="isIOS ? '{{ __('Got it') }}' : '{{ __('Maybe later') }}'"></span>
                    </button>
                </div>
            </div>
        </div>

        <style>
            @keyframes pwa-wave {
                0% { transform: scaleY(0.3); }
                100% { transform: scaleY(1); }
            }
        </style>

        {{ $slot }}

        {{-- Mini Player Bar --}}
        @unless(request()->routeIs('player'))
        <div
            x-data
            x-show="$store.player.currentTrack"
            x-cloak
            class="fixed inset-x-0 bottom-0 z-50 border-t border-zinc-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-lg dark:border-zinc-700 dark:bg-zinc-900/95 lg:left-64"
        >
            {{-- Progress bar at top edge --}}
            <div class="absolute inset-x-0 top-0 h-0.5 bg-zinc-200 dark:bg-zinc-700">
                <div
                    class="h-full bg-zinc-900 transition-all duration-150 ease-out dark:bg-zinc-100"
                    :style="{ width: $store.player.progress + '%' }"
                ></div>
            </div>

            <div class="flex h-16 items-center gap-3 px-4 sm:gap-4">
                {{-- Track Info (clickable to navigate to full player) --}}
                <a
                    href="{{ route('player') }}"
                    wire:navigate
                    class="flex min-w-0 flex-1 items-center gap-3"
                >
                    {{-- Mini album art / icon --}}
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="musical-note" class="size-5 text-zinc-400 dark:text-zinc-500" />
                    </div>

                    {{-- Track title and time --}}
                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <div class="flex items-center gap-2">
                            <span
                                class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100"
                                x-text="$store.player.currentTrack?.title || $store.player.currentTrack?.url || '{{ __('Now Playing') }}'"
                            ></span>
                            {{-- Speed indicator when not 1x --}}
                            <span
                                x-show="$store.player.playbackRate !== 1"
                                x-text="$store.player.playbackRate + 'x'"
                                class="shrink-0 rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300"
                                x-cloak
                            ></span>
                        </div>
                        <span
                            class="truncate text-xs text-zinc-500 dark:text-zinc-400"
                            x-text="$store.player.formatTime($store.player.currentTime) + ' / ' + $store.player.formatTime($store.player.duration)"
                        ></span>
                    </div>
                </a>

                {{-- Controls --}}
                <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                    {{-- Previous --}}
                    <button
                        class="flex size-9 items-center justify-center rounded-full text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 disabled:opacity-30 disabled:hover:bg-transparent dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        @click="$store.player.prev()"
                        :disabled="$store.player.currentIndex <= 0"
                    >
                        <flux:icon name="backward" class="size-5" />
                    </button>

                    {{-- Play/Pause --}}
                    <button
                        class="flex size-11 items-center justify-center rounded-full bg-zinc-900 text-white shadow-md transition-all hover:scale-[1.02] active:scale-[0.98] dark:bg-zinc-100 dark:text-zinc-900"
                        @click="$store.player.togglePlay()"
                    >
                        <template x-if="$store.player.isPlaying">
                            <flux:icon name="pause" class="size-5" />
                        </template>
                        <template x-if="!$store.player.isPlaying">
                            <flux:icon name="play" class="ml-0.5 size-5" />
                        </template>
                    </button>

                    {{-- Next --}}
                    <button
                        class="flex size-9 items-center justify-center rounded-full text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 disabled:opacity-30 disabled:hover:bg-transparent dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        @click="$store.player.next()"
                        :disabled="$store.player.currentIndex >= $store.player.queue.length - 1"
                    >
                        <flux:icon name="forward" class="size-5" />
                    </button>
                </div>
            </div>
        </div>
        @endunless

        {{-- Toast Notification Container --}}
        <div
            x-data="{
                toasts: [],
                add(message, type = 'success') {
                    const id = Date.now();
                    this.toasts.push({ id, message, type });
                    setTimeout(() => this.remove(id), 3000);
                },
                remove(id) {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }
            }"
            @toast.window="add($event.detail.message, $event.detail.type)"
            class="pointer-events-none fixed bottom-24 left-1/2 z-[60] -translate-x-1/2 space-y-2 lg:bottom-6 lg:left-auto lg:right-6 lg:translate-x-0"
        >
            <template x-for="toast in toasts" :key="toast.id">
                <div
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                    class="pointer-events-auto flex items-center gap-2 rounded-full bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-lg dark:bg-zinc-100 dark:text-zinc-900"
                >
                    <svg class="size-4 text-emerald-400 dark:text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                    <span x-text="toast.message"></span>
                </div>
            </template>
        </div>

        @fluxScripts
    </body>
</html>
