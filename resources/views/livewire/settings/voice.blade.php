<?php

use App\Enums\TtsVoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $tts_voice = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->tts_voice = Auth::user()->tts_voice?->value ?? TtsVoice::Alloy->value;
    }

    /**
     * Update the voice settings for the currently authenticated user.
     */
    public function updateVoiceSettings(): void
    {
        $validated = $this->validate([
            'tts_voice' => ['required', 'string', 'in:' . implode(',', array_column(TtsVoice::cases(), 'value'))],
        ]);

        Auth::user()->update($validated);

        $this->dispatch('voice-updated');
    }

    /**
     * Get available voices for the select.
     *
     * @return array<string, string>
     */
    public function getVoicesProperty(): array
    {
        return TtsVoice::options();
    }

    /**
     * Get the description for the currently selected voice.
     */
    public function getSelectedVoiceDescriptionProperty(): string
    {
        $voice = TtsVoice::tryFrom($this->tts_voice);

        return $voice ? $voice->description() : '';
    }
}; ?>

<section
    class="page-content w-full"
    x-data
    x-bind:class="$store.player.currentTrack ? 'pb-24' : 'pb-2'"
>
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Voice')" :subheading="__('Choose the voice used for article audio playback')">
        <form wire:submit="updateVoiceSettings" class="flex flex-col gap-6 w-full mt-6">
            <div class="flex flex-col gap-2">
                <flux:select
                    wire:model.live="tts_voice"
                    :label="__('TTS Voice')"
                    class="transition-all duration-200"
                >
                    @foreach ($this->voices as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($this->selectedVoiceDescription)
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $this->selectedVoiceDescription }}
                    </flux:text>
                @endif
            </div>

            <flux:callout variant="info" icon="information-circle" class="text-sm">
                <flux:callout.text>
                    {{ __('Changes will apply to new articles. Existing audio will not be regenerated.') }}
                </flux:callout.text>
            </flux:callout>

            <div class="flex items-center gap-4">
                <flux:button
                    variant="primary"
                    type="submit"
                    class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                    data-test="update-voice-button"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>{{ __('Save') }}</span>
                    <span wire:loading class="flex items-center gap-2">
                        <flux:icon.loading variant="mini" />
                        {{ __('Saving...') }}
                    </span>
                </flux:button>

                <x-action-message class="text-sm text-zinc-600 dark:text-zinc-400 transition-opacity duration-200" on="voice-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
