@props([
    'showText' => true,
    'iconOnly' => false,
    'size' => 'default'
])

@php
    $sizes = [
        'sm' => ['container' => 'size-7', 'icon' => 'size-4', 'text' => 'text-xs'],
        'default' => ['container' => 'size-8', 'icon' => 'size-5', 'text' => 'text-sm'],
        'lg' => ['container' => 'size-9', 'icon' => 'size-6', 'text' => 'text-base'],
        'xl' => ['container' => 'size-12', 'icon' => 'size-8', 'text' => 'text-lg'],
    ];
    $s = $sizes[$size] ?? $sizes['default'];
@endphp

@if($iconOnly)
    <x-app-logo-icon {{ $attributes->merge(['class' => "{$s['icon']} fill-current"]) }} />
@else
    <div {{ $attributes->merge(['class' => 'flex items-center']) }}>
        <div class="flex aspect-square {{ $s['container'] }} items-center justify-center rounded-lg bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="{{ $s['icon'] }} fill-current" />
        </div>
        @if($showText)
            <div class="ms-2 grid flex-1 text-start {{ $s['text'] }}">
                <span class="truncate leading-tight font-semibold">Utlut</span>
            </div>
        @endif
    </div>
@endif
