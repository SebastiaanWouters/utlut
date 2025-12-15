<script lang="ts">
	import { goto } from '$app/navigation';
	import {
		currentArticle,
		progress,
		bufferedPercent,
		statusLabel,
		playerStatus,
		playerError,
		hasNext,
		hasPrevious,
		generationProgress
	} from '$lib/stores/audioStore';
	import { audioManager } from '$lib/audio/AudioManager';
	import { Play, Pause, Loader2, SkipBack, SkipForward, AlertCircle } from 'lucide-svelte';

	// Swipe gesture state
	let startY = $state(0);
	let currentY = $state(0);
	let isDragging = $state(false);
	let transform = $state('');

	function togglePlay(e: MouseEvent) {
		e.stopPropagation();
		console.log('MiniPlayer togglePlay clicked, status:', $playerStatus);
		audioManager?.togglePlay();
	}

	function playPrevious(e: MouseEvent) {
		e.stopPropagation();
		audioManager?.playPrevious();
	}

	function playNext(e: MouseEvent) {
		e.stopPropagation();
		audioManager?.playNext();
	}

	function handleTouchStart(e: TouchEvent) {
		// Don't start drag if touching a button
		if ((e.target as HTMLElement).closest('button')) return;
		startY = e.touches[0].clientY;
		currentY = startY;
		isDragging = true;
	}

	function handleTouchMove(e: TouchEvent) {
		if (!isDragging) return;
		currentY = e.touches[0].clientY;
		const diff = startY - currentY;
		// Only allow upward swipe
		if (diff > 0) {
			transform = `translateY(-${Math.min(diff * 0.5, 50)}px)`;
		}
	}

	function handleTouchEnd() {
		if (!isDragging) return;
		isDragging = false;
		const diff = startY - currentY;
		if (diff > 50) {
			// Swipe up threshold reached - navigate to full player
			goto('/player');
		}
		transform = '';
	}

	// Derived states
	const isBuffering = $derived($playerStatus === 'buffering');
	const isLoading = $derived($playerStatus === 'loading');
	const isPlaying = $derived($playerStatus === 'playing');
	const hasError = $derived($playerStatus === 'error');
	const showSpinner = $derived(isLoading || isBuffering);
</script>

{#if $currentArticle}
	<div
		class="bg-zinc-900 border-t border-zinc-800 transition-transform duration-150"
		style:transform
		ontouchstart={handleTouchStart}
		ontouchmove={handleTouchMove}
		ontouchend={handleTouchEnd}
	>
		<!-- Progress bar with buffered indicator -->
		<div class="h-0.5 bg-zinc-800 relative">
			<!-- Buffered progress -->
			<div
				class="absolute h-full bg-zinc-600 transition-all duration-300"
				style="width: {$bufferedPercent}%"
			></div>
			<!-- Played progress -->
			<div
				class="absolute h-full bg-blue-500 transition-all duration-200"
				style="width: {$progress}%"
			></div>
			<!-- Generation progress (when loading) -->
			{#if isLoading && $generationProgress > 0}
				<div
					class="absolute h-full bg-green-500/50 transition-all duration-300"
					style="width: {$generationProgress}%"
				></div>
			{/if}
		</div>

		<div class="flex items-center gap-2 px-3 py-2">
			<!-- Track info (clickable to go to player) -->
			<a href="/player" class="flex-1 min-w-0">
				<p class="text-sm font-medium truncate">{$currentArticle.title}</p>
				<p class="text-xs text-zinc-500 flex items-center gap-1">
					{#if hasError}
						<AlertCircle size={12} class="text-red-400" />
						<span class="text-red-400">Error</span>
					{:else}
						{$statusLabel}
					{/if}
				</p>
			</a>

			<!-- Controls (outside anchor to avoid click conflicts) -->
			<div class="flex items-center gap-1" style="touch-action: manipulation;">
				<!-- Previous button -->
				<button
					type="button"
					onclick={playPrevious}
					class="w-8 h-8 rounded-full hover:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:text-white disabled:opacity-30 disabled:hover:bg-transparent"
					disabled={!$hasPrevious || showSpinner}
					aria-label="Previous"
				>
					<SkipBack size={18} />
				</button>

				<!-- Play/Pause button -->
				<button
					type="button"
					onclick={togglePlay}
					class="w-10 h-10 rounded-full bg-white text-black flex items-center justify-center disabled:opacity-70"
					disabled={isLoading}
					aria-label={isPlaying ? 'Pause' : 'Play'}
				>
					{#if showSpinner}
						<Loader2 size={20} class="animate-spin" />
					{:else if hasError}
						<AlertCircle size={20} class="text-red-500" />
					{:else if isPlaying}
						<Pause size={20} />
					{:else}
						<Play size={20} class="ml-0.5" />
					{/if}
				</button>

				<!-- Next button -->
				<button
					type="button"
					onclick={playNext}
					class="w-8 h-8 rounded-full hover:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:text-white disabled:opacity-30 disabled:hover:bg-transparent"
					disabled={!$hasNext || showSpinner}
					aria-label="Next"
				>
					<SkipForward size={18} />
				</button>
			</div>
		</div>
	</div>
{/if}
