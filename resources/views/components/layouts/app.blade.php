<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="h-full overflow-y-auto" style="padding-bottom: calc(6rem + env(safe-area-inset-bottom, 0px));">
        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
