<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="h-full overflow-y-auto pb-24">
        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
