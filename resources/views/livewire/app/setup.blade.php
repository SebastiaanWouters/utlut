<?php

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Setup')] class extends Component {
    public string $token = '';

    public function mount(): void
    {
        $deviceToken = Auth::user()->deviceTokens()->first();

        if (! $deviceToken) {
            $token = Str::random(40);
            $deviceToken = Auth::user()->deviceTokens()->create([
                'token' => $token,
                'token_hash' => hash('sha256', $token),
                'name' => 'Primary Device',
            ]);
        }

        $this->token = $deviceToken->token;
    }
}; ?>

<div class="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-8">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Setup') }}</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Connect your iOS device to start listening') }}</p>
    </div>

    <!-- Steps -->
    <div class="flex flex-col gap-3">
        <!-- Step 1: Install Shortcut -->
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start gap-4">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                    <span class="text-sm font-medium">1</span>
                </div>
                <div class="flex flex-1 flex-col gap-3">
                    <div>
                        <h2 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Install the Shortcut') }}</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add the iOS Shortcut to send articles from Safari.') }}</p>
                    </div>
                    <flux:button
                        as="a"
                        href="https://www.icloud.com/shortcuts/3b5591d2beb34816a48c3b2c25234335"
                        variant="primary"
                        icon="arrow-down-tray"
                        class="w-fit"
                    >
                        {{ __('Install Shortcut') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <!-- Step 2: Copy Token -->
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start gap-4">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                    <span class="text-sm font-medium">2</span>
                </div>
                <div class="flex flex-1 flex-col gap-3">
                    <div>
                        <h2 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Copy your token') }}</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Authenticate the shortcut on first run.') }}</p>
                    </div>
                    <flux:input
                        wire:model="token"
                        readonly
                        copyable
                    />
                </div>
            </div>
        </div>

        <!-- Step 3: Usage -->
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start gap-4">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                    <span class="text-sm font-medium">3</span>
                </div>
                <div class="flex flex-1 flex-col gap-3">
                    <div>
                        <h2 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('How to use') }}</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Save articles from Safari using the Share menu.') }}</p>
                    </div>
                    <div class="flex flex-col gap-2 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-700/50">
                        <div class="flex items-center gap-3">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-zinc-400 dark:bg-zinc-600 dark:text-zinc-300">
                                <flux:icon.share class="size-3.5" />
                            </div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">Tap <span class="font-medium text-zinc-900 dark:text-zinc-100">Share</span> in Safari</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-zinc-400 dark:bg-zinc-600 dark:text-zinc-300">
                                <flux:icon.bolt class="size-3.5" />
                            </div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">Select <span class="font-medium text-zinc-900 dark:text-zinc-100">Utlut</span> shortcut</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-zinc-400 dark:bg-zinc-600 dark:text-zinc-300">
                                <flux:icon.clipboard class="size-3.5" />
                            </div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">Paste token when prompted</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

