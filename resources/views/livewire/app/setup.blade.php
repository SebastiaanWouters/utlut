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
            $deviceToken = Auth::user()->deviceTokens()->create([
                'token' => Str::random(40),
                'name' => 'Primary Device',
            ]);
        }

        $this->token = $deviceToken->token;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl p-4 md:p-8">
    <div class="flex flex-col gap-2">
        <flux:heading level="1" size="xl">{{ __('Setup your device') }}</flux:heading>
        <flux:text variant="subtle" class="text-sm">{{ __('Get started by installing the iOS Shortcut and configuring your device token.') }}</flux:text>
    </div>

    <section class="flex flex-col gap-8 max-w-2xl">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <flux:heading level="2" size="lg">{{ __('1. Install the Shortcut') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Install the iOS Shortcut to start sending articles to Utlut.') }}</flux:text>
            </div>
            
            <flux:button 
                as="a" 
                href="https://www.icloud.com/shortcuts/3b5591d2beb34816a48c3b2c25234335" 
                variant="primary" 
                icon="arrow-down-tray"
                class="w-fit transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
            >
                {{ __('Install Shortcut') }}
            </flux:button>
        </div>

        <flux:separator class="dark:border-zinc-700" />

        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <flux:heading level="2" size="lg">{{ __('2. Copy your token') }}</flux:heading>
                <flux:text variant="subtle">{{ __('You will need this token to authenticate the shortcut.') }}</flux:text>
            </div>
            
            <flux:input 
                label="{{ __('Your Device Token') }}" 
                wire:model="token" 
                readonly 
                copyable 
                class="transition-all duration-200"
            />
        </div>

        <flux:separator class="dark:border-zinc-700" />

        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <flux:heading level="2" size="lg">{{ __('3. Usage Instructions (Safari Only)') }}</flux:heading>
            </div>
            <div class="flex flex-col gap-3">
                <flux:text class="flex items-center gap-3 text-sm dark:text-zinc-300">
                    <flux:icon name="share" size="sm" class="shrink-0 text-zinc-600 dark:text-zinc-400" />
                    <span>Open Safari and tap the <strong class="font-semibold text-zinc-900 dark:text-zinc-100">Share</strong> button at the bottom.</span>
                </flux:text>
                <flux:text class="flex items-center gap-3 text-sm dark:text-zinc-300">
                    <flux:icon name="bolt" size="sm" class="shrink-0 text-zinc-600 dark:text-zinc-400" />
                    <span>Find and tap the <strong class="font-semibold text-zinc-900 dark:text-zinc-100">Utlut</strong> shortcut you just installed.</span>
                </flux:text>
                <flux:text class="flex items-center gap-3 text-sm dark:text-zinc-300">
                    <flux:icon name="clipboard" size="sm" class="shrink-0 text-zinc-600 dark:text-zinc-400" />
                    <span>When prompted for a token, paste the one you copied above.</span>
                </flux:text>
            </div>
        </div>
    </section>
</div>

