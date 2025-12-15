<script lang="ts">
	import {
		queue,
		shuffleEnabled,
		repeatMode
	} from '$lib/stores/audioStore';
	import { audioManager } from '$lib/audio/AudioManager';
	import { getArticle } from '$lib/db/database';
	import type { Article } from '$lib/db/database';
	import { GripVertical, X, Play, Shuffle, Repeat, Repeat1, Loader2 } from 'lucide-svelte';

	let articles = $state<Article[]>([]);
	let isLoadingQueue = $state(false);

	$effect(() => {
		loadArticles($queue);
	});

	// Parallel loading with Promise.all
	async function loadArticles(ids: string[]) {
		if (ids.length === 0) {
			articles = [];
			return;
		}

		isLoadingQueue = true;
		try {
			const results = await Promise.all(ids.map((id) => getArticle(id)));
			articles = results.filter((a): a is Article => a !== undefined);
		} finally {
			isLoadingQueue = false;
		}
	}

	async function handleRemove(articleId: string, e: Event) {
		e.stopPropagation();
		await audioManager?.removeFromQueue(articleId);
	}

	async function handlePlay(articleId: string) {
		// Play this item from the queue (removes it and items before it)
		await audioManager?.playFromQueue(articleId);
	}

	let draggedIndex = $state<number | null>(null);
	let localQueue = $state<string[]>([]);
	let isDragging = $state(false);

	// Sync localQueue with store queue when not dragging
	$effect(() => {
		if (!isDragging) {
			localQueue = [...$queue];
		}
	});

	function handleDragStart(e: DragEvent, index: number) {
		isDragging = true;
		draggedIndex = index;
		localQueue = [...$queue];
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
		}
	}

	function handleDragOver(e: DragEvent, index: number) {
		e.preventDefault();
		if (draggedIndex !== null && draggedIndex !== index) {
			// Update local state only during drag for smooth UI
			const [removed] = localQueue.splice(draggedIndex, 1);
			localQueue.splice(index, 0, removed);
			draggedIndex = index;
		}
	}

	function handleDragEnd() {
		// Only save to store once when drag ends
		if (isDragging && localQueue.length > 0) {
			audioManager?.reorderQueue(localQueue);
		}
		draggedIndex = null;
		isDragging = false;
	}
</script>

<div class="flex flex-col gap-2">
	<!-- Mode indicators -->
	{#if $shuffleEnabled || $repeatMode !== 'off'}
		<div class="flex items-center gap-3 text-xs text-zinc-400 px-2 mb-1">
			{#if $shuffleEnabled}
				<span class="flex items-center gap-1 text-blue-400">
					<Shuffle size={12} />
					Shuffle on
				</span>
			{/if}
			{#if $repeatMode !== 'off'}
				<span class="flex items-center gap-1 text-blue-400">
					{#if $repeatMode === 'one'}
						<Repeat1 size={12} />
						Repeat one
					{:else}
						<Repeat size={12} />
						Repeat all
					{/if}
				</span>
			{/if}
		</div>
	{/if}

	{#if isLoadingQueue}
		<div class="text-center py-12 text-zinc-500">
			<Loader2 size={24} class="animate-spin mx-auto mb-2" />
			<p class="text-sm">Loading queue...</p>
		</div>
	{:else if articles.length === 0}
		<div class="text-center py-12 text-zinc-500">
			<p>Queue is empty</p>
			<p class="text-sm mt-1">Add articles to play next</p>
		</div>
	{:else}
		{#each articles as article, index (article.id)}
			{@const isUpNext = index === 0}
			<div
				role="listitem"
				class="flex items-center gap-2 p-3 rounded-lg transition-all duration-200 {isUpNext
					? 'bg-zinc-800/50 border border-zinc-700'
					: 'bg-zinc-900 border border-zinc-800 hover:bg-zinc-800/50'} {draggedIndex === index
					? 'opacity-50'
					: ''}"
				draggable="true"
				ondragstart={(e) => handleDragStart(e, index)}
				ondragover={(e) => handleDragOver(e, index)}
				ondragend={handleDragEnd}
			>
				<button class="cursor-grab text-zinc-500 hover:text-zinc-300 touch-none">
					<GripVertical size={18} />
				</button>

				<button onclick={() => handlePlay(article.id)} class="flex-1 text-left min-w-0">
					<p class="font-medium truncate">
						{article.title}
					</p>
					<p class="text-xs text-zinc-500">
						{#if isUpNext}
							<span class="text-blue-400">Up next</span>
						{:else}
							{article.language.toUpperCase()}
						{/if}
					</p>
				</button>

				<!-- Play button -->
				<button
					onclick={() => handlePlay(article.id)}
					class="text-zinc-400 hover:text-white p-1 transition-colors"
					aria-label="Play now"
				>
					<Play size={18} />
				</button>

				<button
					onclick={(e) => handleRemove(article.id, e)}
					class="text-zinc-500 hover:text-red-400 p-1 transition-colors"
					aria-label="Remove from queue"
				>
					<X size={18} />
				</button>
			</div>
		{/each}
	{/if}
</div>
