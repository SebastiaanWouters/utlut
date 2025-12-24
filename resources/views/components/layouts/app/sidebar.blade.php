<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="h-screen overflow-hidden bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
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
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
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
                show: false,
                init() {
                    if (this.dismissed || this.isStandalone) return;
                    window.addEventListener('beforeinstallprompt', (e) => {
                        e.preventDefault();
                        this.deferredPrompt = e;
                        this.show = true;
                    });
                },
                async install() {
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
            x-cloak
            class="fixed bottom-20 left-4 right-4 z-40 lg:bottom-4 lg:left-auto lg:right-4 lg:w-80"
        >
            <div class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon.device-phone-mobile class="size-5 text-zinc-600 dark:text-zinc-300" />
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Install Utlut') }}</h3>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Add to home screen for offline playback and lock screen controls.') }}</p>
                    <div class="mt-3 flex gap-2">
                        <button
                            @click="install()"
                            class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        >
                            {{ __('Install') }}
                        </button>
                        <button
                            @click="dismiss()"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-zinc-600 transition-colors hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700"
                        >
                            {{ __('Not now') }}
                        </button>
                    </div>
                </div>
                <button
                    @click="dismiss()"
                    class="-mr-1 -mt-1 rounded-lg p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                >
                    <flux:icon.x-mark class="size-4" />
                </button>
            </div>
        </div>

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

            <div class="flex h-16 items-center gap-3 px-4 sm:gap-4 sm:px-6">
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

        @fluxScripts
    </body>
</html>
