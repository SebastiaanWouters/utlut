<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="h-full overflow-y-auto overscroll-contain pb-20 [-webkit-overflow-scrolling:touch]">
        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
