<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div
    class="flex flex-col gap-6 py-6 border shadow-sm rounded-xl border-zinc-200 dark:border-white/10 transition-all duration-200"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="flex flex-col gap-2 px-6">
        <div class="flex items-center gap-2">
            <flux:icon.lock-closed variant="outline" class="size-4 text-zinc-600 dark:text-zinc-400"/>
            <flux:heading size="lg" level="3">{{ __('2FA Recovery Codes') }}</flux:heading>
        </div>
        <flux:text variant="subtle" class="text-sm dark:text-zinc-300">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </flux:text>
    </div>

    <div class="flex flex-col gap-6 px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:button
                x-show="!showRecoveryCodes"
                icon="eye"
                icon:variant="outline"
                variant="primary"
                @click="showRecoveryCodes = true;"
                aria-expanded="false"
                aria-controls="recovery-codes-section"
                class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
            >
                {{ __('View Recovery Codes') }}
            </flux:button>

            <flux:button
                x-show="showRecoveryCodes"
                icon="eye-slash"
                icon:variant="outline"
                variant="primary"
                @click="showRecoveryCodes = false"
                aria-expanded="true"
                aria-controls="recovery-codes-section"
                class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
            >
                {{ __('Hide Recovery Codes') }}
            </flux:button>

            @if (filled($recoveryCodes))
                <flux:button
                    x-show="showRecoveryCodes"
                    icon="arrow-path"
                    variant="filled"
                    wire:click="regenerateRecoveryCodes"
                    class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>{{ __('Regenerate Codes') }}</span>
                    <span wire:loading class="flex items-center gap-2">
                        <flux:icon.loading variant="mini" />
                        {{ __('Regenerating...') }}
                    </span>
                </flux:button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95"
            id="recovery-codes-section"
            class="relative overflow-hidden"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            <div class="flex flex-col gap-3">
                @error('recoveryCodes')
                    <flux:callout variant="danger" icon="x-circle" heading="{{$message}}"/>
                @enderror

                @if (filled($recoveryCodes))
                    <div
                        class="grid gap-1 p-4 font-mono text-sm rounded-lg bg-zinc-100 dark:bg-white/5 border border-zinc-200 dark:border-zinc-700 transition-all duration-200"
                        role="list"
                        aria-label="Recovery codes"
                    >
                        @foreach($recoveryCodes as $code)
                            <div
                                role="listitem"
                                class="select-text text-zinc-900 dark:text-zinc-100 transition-opacity duration-200"
                                wire:loading.class="opacity-50 animate-pulse"
                            >
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>
                    <flux:text variant="subtle" class="text-xs dark:text-zinc-400">
                        {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate Codes above.') }}
                    </flux:text>
                @endif
            </div>
        </div>
    </div>
</div>
