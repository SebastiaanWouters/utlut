@props(['progress' => 0, 'etaSeconds' => null])

<span class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
    {{-- Progress circle --}}
    <svg class="size-3" viewBox="0 0 16 16">
        <circle
            cx="8" cy="8" r="6"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            class="opacity-25"
        />
        <circle
            cx="8" cy="8" r="6"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-dasharray="{{ 2 * 3.14159 * 6 }}"
            stroke-dashoffset="{{ 2 * 3.14159 * 6 * (1 - $progress / 100) }}"
            transform="rotate(-90 8 8)"
            class="transition-all duration-300"
        />
    </svg>

    <span>{{ $progress }}%</span>

    @if($etaSeconds !== null && $etaSeconds > 0)
        <span class="text-zinc-400 dark:text-zinc-500">
            (~{{ $etaSeconds < 60 ? $etaSeconds . 's' : (int) ceil($etaSeconds / 60) . 'm' }})
        </span>
    @endif
</span>
