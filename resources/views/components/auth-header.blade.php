@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2 text-center">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <flux:subheading class="text-sm dark:text-zinc-300">{{ $description }}</flux:subheading>
</div>
