<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<section
    class="page-content w-full"
    x-data
    x-bind:style="$store.player.currentTrack ? 'padding-bottom: max(6rem, env(safe-area-inset-bottom, 4rem))' : ''"
>
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div class="mt-6">
            <flux:radio.group 
                x-data 
                variant="segmented" 
                x-model="$flux.appearance"
                class="transition-all duration-200"
            >
                <flux:radio value="light" icon="sun" class="transition-all duration-200">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon" class="transition-all duration-200">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop" class="transition-all duration-200">{{ __('System') }}</flux:radio>
            </flux:radio.group>
        </div>
    </x-settings.layout>
</section>
