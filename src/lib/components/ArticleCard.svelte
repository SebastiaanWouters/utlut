<script lang="ts">
	import type { Article } from '$lib/db/database';
	import { audioManager } from '$lib/audio/AudioManager';
	import { currentArticle, queue, playerStatus, audioJobs } from '$lib/stores/audioStore';
	import { jobPollingService } from '$lib/services/jobPollingService';
	import {
		Play,
		Pause,
		ListPlus,
		ListStart,
		Trash2,
		MoreVertical,
		Volume2,
		Loader2,
		AlertCircle,
		RefreshCw
	} from 'lucide-svelte';
	import { estimateReadingTime } from '$lib/utils/textChunker';
	import { deleteArticle, getSettings } from '$lib/db/database';
	import { createEventDispatcher } from 'svelte';

	interface Props {
		article: Article;
	}

	let { article }: Props = $props();
	let showMenu = $state(false);

	const dispatch = createEventDispatcher<{ delete: string }>();

	// Derived states
	const isCurrent = $derived($currentArticle?.id === article.id);
	const isPlaying = $derived($playerStatus === 'playing');
	const isLoading = $derived($playerStatus === 'loading' || $playerStatus === 'buffering');
	const isCurrentlyPlaying = $derived(isCurrent && isPlaying);
	const isCurrentlyLoading = $derived(isCurrent && isLoading);
	const isInQueue = $derived($queue.includes(article.id));
	const readTime = $derived(estimateReadingTime(article.content));

	// Job status for async generation
	const jobStatus = $derived($audioJobs.get(article.id));
	const isGenerating = $derived(
		jobStatus?.status === 'pending' ||
			jobStatus?.status === 'processing' ||
			jobStatus?.status === 'downloading'
	);
	const jobFailed = $derived(jobStatus?.status === 'failed');
	const generationProgress = $derived(jobStatus?.progress ?? 0);

	async function handlePlay() {
		if (isCurrentlyPlaying) {
			audioManager?.pause();
		} else {
			await audioManager?.playArticle(article.id);
		}
	}

	async function handlePlayNext() {
		await audioManager?.insertPlayNext(article.id);
		showMenu = false;
	}

	async function handleAddToQueue() {
		await audioManager?.addToQueue(article.id);
		showMenu = false;
	}

	async function handleDelete() {
		if (confirm('Delete this article?')) {
			await deleteArticle(article.id);
			dispatch('delete', article.id);
		}
		showMenu = false;
	}

	async function handleRetryGeneration() {
		try {
			const settings = await getSettings();
			await jobPollingService.retryJob(
				article.id,
				article.title,
				article.content,
				article.language,
				settings.voice
			);
		} catch (err) {
			console.error('Failed to retry audio generation:', err);
		}
	}
</script>

<div class="bg-zinc-900 rounded-xl p-4 border border-zinc-800 relative {isCurrent ? 'ring-1 ring-blue-500/50' : ''}">
	<div class="flex gap-3">
		<button
			onclick={handlePlay}
			class="w-12 h-12 rounded-full bg-blue-600 hover:bg-blue-700 flex items-center justify-center shrink-0 transition-colors disabled:opacity-70"
			disabled={isCurrentlyLoading}
			aria-label={isCurrentlyPlaying ? 'Pause' : 'Play'}
		>
			{#if isCurrentlyLoading}
				<Loader2 size={20} class="animate-spin" />
			{:else if isCurrentlyPlaying}
				<Pause size={20} />
			{:else}
				<Play size={20} class="ml-0.5" />
			{/if}
		</button>

		<div class="flex-1 min-w-0">
			<h3 class="font-medium line-clamp-2 {isCurrent ? 'text-blue-400' : ''}">{article.title}</h3>
			<div class="flex items-center gap-2 mt-1 text-sm text-zinc-500 flex-wrap">
				<span>{article.language.toUpperCase()}</span>
				<span>·</span>
				<span>{readTime} min</span>
				{#if article.audioGenerated && !isGenerating && !jobFailed}
					<span>·</span>
					<Volume2 size={14} class="text-green-500" />
				{/if}
				{#if isCurrentlyPlaying}
					<span>·</span>
					<span class="text-green-400">Playing</span>
				{:else if isCurrentlyLoading}
					<span>·</span>
					<span class="text-yellow-400">Loading...</span>
				{:else if isCurrent}
					<span>·</span>
					<span class="text-blue-400">Paused</span>
				{:else if isInQueue}
					<span>·</span>
					<span class="text-blue-400">In queue</span>
				{/if}
			</div>

			<!-- Generation progress -->
			{#if isGenerating}
				<div class="flex items-center gap-2 mt-2">
					<div class="flex-1 h-1.5 bg-zinc-700 rounded-full overflow-hidden">
						<div
							class="h-full bg-green-500 transition-all duration-300"
							style="width: {generationProgress}%"
						></div>
					</div>
					<span class="text-xs text-green-400 min-w-[60px] text-right">
						{#if jobStatus?.status === 'downloading'}
							Saving...
						{:else if jobStatus?.status === 'pending'}
							Queued
						{:else}
							{generationProgress}%
						{/if}
					</span>
				</div>
			{:else if jobFailed}
				<button
					onclick={handleRetryGeneration}
					class="flex items-center gap-1.5 mt-2 text-sm text-red-400 hover:text-red-300 transition-colors"
				>
					<AlertCircle size={14} />
					<span>Generation failed</span>
					<span class="text-zinc-500">·</span>
					<RefreshCw size={14} />
					<span>Retry</span>
				</button>
			{/if}
		</div>

		<div class="relative">
			<button
				onclick={() => (showMenu = !showMenu)}
				class="w-8 h-8 rounded-full hover:bg-zinc-800 flex items-center justify-center transition-colors"
				aria-label="More options"
			>
				<MoreVertical size={18} />
			</button>

			{#if showMenu}
				<div
					class="absolute right-0 top-full mt-1 bg-zinc-800 rounded-lg shadow-lg border border-zinc-700 py-1 min-w-[160px] z-10"
				>
					<!-- Play Next option -->
					<button
						onclick={handlePlayNext}
						class="w-full px-4 py-2 text-left text-sm hover:bg-zinc-700 flex items-center gap-2"
					>
						<ListStart size={16} />
						Play Next
					</button>

					{#if !isInQueue}
						<button
							onclick={handleAddToQueue}
							class="w-full px-4 py-2 text-left text-sm hover:bg-zinc-700 flex items-center gap-2"
						>
							<ListPlus size={16} />
							Add to Queue
						</button>
					{/if}

					<hr class="my-1 border-zinc-700" />

					<button
						onclick={handleDelete}
						class="w-full px-4 py-2 text-left text-sm hover:bg-zinc-700 flex items-center gap-2 text-red-400"
					>
						<Trash2 size={16} />
						Delete
					</button>
				</div>
			{/if}
		</div>
	</div>
</div>

{#if showMenu}
	<button
		class="fixed inset-0 z-0"
		onclick={() => (showMenu = false)}
		aria-label="Close menu"
	></button>
{/if}
