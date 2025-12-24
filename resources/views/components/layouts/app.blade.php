<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main
        x-data
        class="h-full overflow-y-auto !p-0"
        x-bind:style="$store.player.currentTrack ? 'padding-bottom: calc(5rem + env(safe-area-inset-bottom, 0px))' : 'padding-bottom: env(safe-area-inset-bottom, 0px)'"
    >
        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
