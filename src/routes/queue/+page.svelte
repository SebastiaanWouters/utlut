<script lang="ts">
	import Queue from '$lib/components/Queue.svelte';
	import { queue } from '$lib/stores/audioStore';
	import { audioManager } from '$lib/audio/AudioManager';
	import { ListMusic, Trash2 } from 'lucide-svelte';

	async function clearQueue() {
		if ($queue.length > 0 && confirm('Clear the entire queue?')) {
			await audioManager?.clearQueue();
		}
	}
</script>

<svelte:head>
	<title>Queue | Reader</title>
</svelte:head>

<div class="min-h-screen">
	<header class="sticky top-0 bg-zinc-950/90 backdrop-blur border-b border-zinc-800 px-4 pb-3 pt-safe-offset-3 flex items-center justify-between z-10">
		<div class="flex items-center gap-2">
			<ListMusic size={24} class="text-blue-500" />
			<h1 class="text-lg font-semibold">Queue</h1>
			{#if $queue.length > 0}
				<span class="text-sm text-zinc-500">({$queue.length})</span>
			{/if}
		</div>
		{#if $queue.length > 0}
			<button onclick={clearQueue} class="p-2 hover:bg-zinc-800 rounded-lg text-zinc-400 hover:text-red-400">
				<Trash2 size={20} />
			</button>
		{/if}
	</header>

	<div class="p-4">
		<Queue />
	</div>
</div>
