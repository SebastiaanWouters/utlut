<script lang="ts">
	import { goto } from '$app/navigation';
	import {
		currentArticle,
		currentTime,
		duration,
		playbackRate,
		hasNext,
		hasPrevious,
		formatTime,
		playerStatus,
		playerError,
		bufferedPercent,
		generationProgress,
		repeatMode,
		shuffleEnabled
	} from '$lib/stores/audioStore';
	import { audioManager } from '$lib/audio/AudioManager';
	import {
		ChevronDown,
		SkipBack,
		SkipForward,
		Play,
		Pause,
		Rewind,
		FastForward,
		ListMusic,
		Loader2,
		AlertCircle,
		RotateCcw,
		Repeat,
		Repeat1,
		Shuffle
	} from 'lucide-svelte';

	const speeds = [0.75, 1, 1.25, 1.5, 2];

	// Scrubbing state
	let isScrubbing = $state(false);
	let wasPlayingBeforeScrub = $state(false);
	let scrubPosition = $state(0);

	// Derived states
	const isBuffering = $derived($playerStatus === 'buffering');
	const isLoading = $derived($playerStatus === 'loading');
	const isPlaying = $derived($playerStatus === 'playing');
	const hasError = $derived($playerStatus === 'error');
	const showSpinner = $derived(isLoading || isBuffering);

	// Progress display (use scrub position while scrubbing)
	const displayTime = $derived(isScrubbing ? scrubPosition : $currentTime);
	const displayProgress = $derived(
		$duration > 0 ? (displayTime / $duration) * 100 : 0
	);

	function handleScrubStart() {
		isScrubbing = true;
		wasPlayingBeforeScrub = isPlaying;
		scrubPosition = $currentTime;
		if (isPlaying) {
			audioManager?.pause();
		}
	}

	function handleScrubMove(e: Event) {
		if (!isScrubbing) return;
		const input = e.target as HTMLInputElement;
		scrubPosition = parseFloat(input.value);
	}

	function handleScrubEnd(e: Event) {
		const input = e.target as HTMLInputElement;
		const seekTime = parseFloat(input.value);
		audioManager?.seek(seekTime);

		if (wasPlayingBeforeScrub) {
			audioManager?.play();
		}
		isScrubbing = false;
	}

	function cycleSpeed() {
		const currentIndex = speeds.indexOf($playbackRate);
		const nextIndex = (currentIndex + 1) % speeds.length;
		audioManager?.setPlaybackRate(speeds[nextIndex]);
	}

	function handleBack() {
		goto('/');
	}

	function handleRetry() {
		audioManager?.retry();
	}

	function cycleRepeat() {
		audioManager?.cycleRepeatMode();
	}

	function toggleShuffle() {
		audioManager?.toggleShuffle();
	}
</script>

<svelte:head>
	<title>{$currentArticle?.title || 'Player'} | Reader</title>
</svelte:head>

<div class="min-h-dvh bg-gradient-to-b from-zinc-900 to-zinc-950 flex flex-col pt-safe">
	<header class="px-4 py-3 flex items-center justify-between">
		<button onclick={handleBack} class="p-2 -ml-2 hover:bg-zinc-800 rounded-lg">
			<ChevronDown size={28} />
		</button>
		<span class="text-sm text-zinc-400">Now Playing</span>
		<a href="/queue" class="p-2 -mr-2 hover:bg-zinc-800 rounded-lg">
			<ListMusic size={24} />
		</a>
	</header>

	<div class="flex-1 flex flex-col items-center justify-center px-6 py-8">
		{#if $currentArticle}
			<div class="w-full max-w-sm">
				<!-- Album art / Cover -->
				<div class="w-48 h-48 mx-auto bg-zinc-800 rounded-2xl flex items-center justify-center mb-8 relative overflow-hidden">
					<div class="text-5xl">📖</div>

					<!-- Generation progress overlay -->
					{#if isLoading && $generationProgress > 0}
						<div class="absolute inset-0 bg-black/50 flex flex-col items-center justify-center">
							<Loader2 size={48} class="animate-spin mb-2" />
							<p class="text-sm">Generating... {$generationProgress}%</p>
							<div class="w-32 h-1 bg-zinc-700 rounded-full mt-2 overflow-hidden">
								<div
									class="h-full bg-green-500 transition-all duration-300"
									style="width: {$generationProgress}%"
								></div>
							</div>
						</div>
					{/if}

					<!-- Error overlay -->
					{#if hasError}
						<div class="absolute inset-0 bg-black/70 flex flex-col items-center justify-center">
							<AlertCircle size={48} class="text-red-400 mb-2" />
							<p class="text-sm text-red-400 mb-3">{$playerError || 'Playback error'}</p>
							<button
								onclick={handleRetry}
								class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-full text-sm flex items-center gap-2"
							>
								<RotateCcw size={16} />
								Retry
							</button>
						</div>
					{/if}
				</div>

				<h2 class="text-xl font-bold text-center line-clamp-2 mb-2">
					{$currentArticle.title}
				</h2>
				<p class="text-sm text-zinc-500 text-center mb-8">
					{$currentArticle.language === 'nl' ? 'Dutch' : 'English'}
					{#if isBuffering}
						<span class="text-yellow-400">· Buffering...</span>
					{/if}
				</p>

				<!-- Progress bar with buffered indicator -->
				<div class="mb-6">
					<div class="relative h-2">
						<!-- Track background -->
						<div class="absolute inset-0 h-1 top-1/2 -translate-y-1/2 bg-zinc-700 rounded-full"></div>
						<!-- Buffered progress -->
						<div
							class="absolute h-1 top-1/2 -translate-y-1/2 bg-zinc-500 rounded-full transition-all duration-300"
							style="width: {$bufferedPercent}%"
						></div>
						<!-- Played progress -->
						<div
							class="absolute h-1 top-1/2 -translate-y-1/2 bg-blue-500 rounded-full"
							style="width: {displayProgress}%"
						></div>
						<!-- Range input -->
						<input
							type="range"
							min="0"
							max={$duration || 100}
							value={displayTime}
							onmousedown={handleScrubStart}
							ontouchstart={handleScrubStart}
							oninput={handleScrubMove}
							onmouseup={handleScrubEnd}
							ontouchend={handleScrubEnd}
							class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
						/>
						<!-- Thumb indicator -->
						<div
							class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-blue-500 rounded-full shadow-lg pointer-events-none"
							style="left: calc({displayProgress}% - 6px)"
						></div>
					</div>
					<div class="flex justify-between text-xs text-zinc-500 mt-2">
						<span>{formatTime(displayTime)}</span>
						<span>{formatTime($duration)}</span>
					</div>
				</div>

				<!-- Main controls -->
				<div class="flex items-center justify-center gap-4 mb-6">
					<button
						onclick={() => audioManager?.seekRelative(-15)}
						class="p-3 hover:bg-zinc-800 rounded-full disabled:opacity-30"
						disabled={showSpinner}
						aria-label="Rewind 15 seconds"
					>
						<Rewind size={24} />
					</button>

					<button
						onclick={() => audioManager?.playPrevious()}
						class="p-3 hover:bg-zinc-800 rounded-full disabled:opacity-30"
						disabled={!$hasPrevious || showSpinner}
						aria-label="Previous"
					>
						<SkipBack size={28} />
					</button>

					<button
						onclick={() => audioManager?.togglePlay()}
						class="w-16 h-16 bg-white text-black rounded-full flex items-center justify-center disabled:opacity-70"
						disabled={showSpinner && !hasError}
						aria-label={isPlaying ? 'Pause' : 'Play'}
					>
						{#if showSpinner}
							<Loader2 size={32} class="animate-spin" />
						{:else if hasError}
							<AlertCircle size={32} class="text-red-500" />
						{:else if isPlaying}
							<Pause size={32} />
						{:else}
							<Play size={32} class="ml-1" />
						{/if}
					</button>

					<button
						onclick={() => audioManager?.playNext()}
						class="p-3 hover:bg-zinc-800 rounded-full disabled:opacity-30"
						disabled={!$hasNext || showSpinner}
						aria-label="Next"
					>
						<SkipForward size={28} />
					</button>

					<button
						onclick={() => audioManager?.seekRelative(15)}
						class="p-3 hover:bg-zinc-800 rounded-full disabled:opacity-30"
						disabled={showSpinner}
						aria-label="Forward 15 seconds"
					>
						<FastForward size={24} />
					</button>
				</div>

				<!-- Secondary controls: Shuffle, Speed, Repeat -->
				<div class="flex items-center justify-center gap-6">
					<!-- Shuffle button -->
					<button
						onclick={toggleShuffle}
						class="p-2 rounded-full hover:bg-zinc-800 transition-colors {$shuffleEnabled ? 'text-blue-400' : 'text-zinc-400'}"
						aria-label="Shuffle"
						title={$shuffleEnabled ? 'Shuffle on' : 'Shuffle off'}
					>
						<Shuffle size={20} />
					</button>

					<!-- Speed button -->
					<button
						onclick={cycleSpeed}
						class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 rounded-full text-sm font-medium min-w-[60px]"
						aria-label="Playback speed"
					>
						{$playbackRate}×
					</button>

					<!-- Repeat button -->
					<button
						onclick={cycleRepeat}
						class="p-2 rounded-full hover:bg-zinc-800 transition-colors {$repeatMode !== 'off' ? 'text-blue-400' : 'text-zinc-400'}"
						aria-label="Repeat mode: {$repeatMode}"
						title="Repeat: {$repeatMode}"
					>
							{#if $repeatMode === 'one'}
							<Repeat1 size={20} />
						{:else}
							<Repeat size={20} />
						{/if}
					</button>
				</div>
			</div>
		{:else}
			<div class="text-center text-zinc-500">
				<p>No article selected</p>
				<a href="/" class="text-blue-400 hover:underline mt-2 inline-block">
					Go to Library
				</a>
			</div>
		{/if}
	</div>
</div>
