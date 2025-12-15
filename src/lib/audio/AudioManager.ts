import {
	currentArticle,
	playerStatus,
	playerError,
	currentTime,
	duration,
	volume,
	playbackRate,
	queue,
	repeatMode,
	shuffleEnabled,
	bufferedPercent,
	generationProgress,
	isPlaying,
	audioJobs,
	updateAudioJob
} from '$lib/stores/audioStore';
import { getArticle, getAudioTrack, getQueueState, saveQueueState, getSettings } from '$lib/db/database';
import { generateArticleAudio, generateIntroAudio } from '$lib/services/ttsService';
import { jobPollingService } from '$lib/services/jobPollingService';
import { get } from 'svelte/store';

class AudioManager {
	private audio: HTMLAudioElement | null = null;
	private nextAudio: HTMLAudioElement | null = null;
	private introAudio: HTMLAudioElement | null = null;
	private chimeAudio: HTMLAudioElement | null = null;
	private objectUrls: string[] = [];
	private saveTimeout: ReturnType<typeof setTimeout> | null = null;
	private isLoadingArticle: boolean = false;
	private currentLoadId: number = 0;
	private boundBeforeUnload: () => void;
	private boundVisibilityChange: () => void;

	constructor() {
		this.boundBeforeUnload = this.handleBeforeUnload.bind(this);
		this.boundVisibilityChange = this.handleVisibilityChange.bind(this);
		if (typeof window !== 'undefined') {
			this.audio = new Audio();
			this.nextAudio = new Audio();
			this.setupAudioListeners();
			this.loadQueueState();
			window.addEventListener('beforeunload', this.boundBeforeUnload);
			document.addEventListener('visibilitychange', this.boundVisibilityChange);
		}
	}

	private handleBeforeUnload() {
		// Save position synchronously to localStorage for fast recovery
		this.savePositionToLocalStorage();
	}

	private handleVisibilityChange() {
		// Save when page becomes hidden (tab switch, app backgrounded on mobile)
		if (document.visibilityState === 'hidden') {
			this.savePositionToLocalStorage();
			this.savePosition();
		}
	}

	private savePositionToLocalStorage() {
		try {
			const article = get(currentArticle);
			if (article && this.audio) {
				localStorage.setItem('reader_playback_time', JSON.stringify({
					articleId: article.id,
					time: this.audio.currentTime,
					timestamp: Date.now()
				}));
			}
		} catch {
			// Ignore localStorage errors
		}
	}

	private getPositionFromLocalStorage(): { articleId: string; time: number } | null {
		try {
			const saved = localStorage.getItem('reader_playback_time');
			if (saved) {
				const data = JSON.parse(saved);
				// Only use if saved within last hour
				if (Date.now() - data.timestamp < 60 * 60 * 1000) {
					return { articleId: data.articleId, time: data.time };
				}
			}
		} catch {
			// Ignore errors
		}
		return null;
	}

	private setupAudioListeners() {
		if (!this.audio) return;

		this.audio.addEventListener('timeupdate', () => {
			currentTime.set(this.audio!.currentTime);
			this.updateBufferedPercent();
			this.updatePositionState();
			this.debouncedSavePosition();
		});

		this.audio.addEventListener('durationchange', () => {
			duration.set(this.audio!.duration);
		});

		this.audio.addEventListener('ended', () => {
			this.playNext();
		});

		this.audio.addEventListener('play', () => {
			playerStatus.set('playing');
			playerError.set(null);
			this.updateMediaSession();
		});

		this.audio.addEventListener('pause', () => {
			// Only set to paused if not in loading/buffering state
			const status = get(playerStatus);
			if (status !== 'loading' && status !== 'buffering') {
				playerStatus.set('paused');
			}
		});

		this.audio.addEventListener('loadstart', () => {
			// Don't override loading status if we're generating TTS
			const status = get(playerStatus);
			if (status !== 'loading') {
				playerStatus.set('buffering');
			}
		});

		this.audio.addEventListener('canplay', () => {
			// Transition from loading/buffering to paused (ready to play)
			const status = get(playerStatus);
			if (status === 'loading' || status === 'buffering') {
				playerStatus.set('paused');
			}
		});

		this.audio.addEventListener('waiting', () => {
			playerStatus.set('buffering');
		});

		this.audio.addEventListener('playing', () => {
			playerStatus.set('playing');
			playerError.set(null);
		});

		this.audio.addEventListener('error', (e) => {
			playerStatus.set('error');
			const errorMsg = this.audio?.error?.message || 'Playback error';
			playerError.set(errorMsg);
			this.handlePlaybackError();
		});

		this.audio.addEventListener('progress', () => {
			this.updateBufferedPercent();
		});
	}

	private updateBufferedPercent() {
		if (!this.audio || !this.audio.duration) return;
		if (this.audio.buffered.length > 0) {
			const bufferedEnd = this.audio.buffered.end(this.audio.buffered.length - 1);
			const percent = (bufferedEnd / this.audio.duration) * 100;
			bufferedPercent.set(Math.min(100, percent));
		}
	}

	private updatePositionState() {
		if ('mediaSession' in navigator && this.audio?.duration) {
			try {
				navigator.mediaSession.setPositionState({
					duration: this.audio.duration,
					playbackRate: this.audio.playbackRate,
					position: this.audio.currentTime
				});
			} catch {
				// Ignore errors (e.g., invalid state)
			}
		}
	}

	private handlePlaybackError() {
		const q = get(queue);

		// Auto-skip to next after 3 seconds if there are more tracks in queue
		if (q.length > 0) {
			setTimeout(() => {
				if (get(playerStatus) === 'error') {
					this.playNext();
				}
			}, 3000);
		}
	}

	// Retry current article after error
	async retry() {
		const article = get(currentArticle);
		if (article) {
			playerError.set(null);
			await this.loadArticle(article.id, true);
		}
	}

	// Clear error and reset to paused state
	clearError() {
		if (get(playerStatus) === 'error') {
			playerError.set(null);
			playerStatus.set('paused');
		}
	}

	private async loadQueueState() {
		try {
			const state = await getQueueState();
			queue.set(state.articleIds);

			// Load the currently playing article if there was one
			if (state.currentArticleId) {
				await this.loadArticle(state.currentArticleId, false);

				// Check localStorage for more recent position (written on beforeunload)
				const localStoragePosition = this.getPositionFromLocalStorage();
				let targetTime = state.currentTime;

				if (localStoragePosition && localStoragePosition.articleId === state.currentArticleId) {
					// Use localStorage time if it's more recent
					targetTime = localStoragePosition.time;
				}

				if (this.audio && targetTime > 0) {
					this.audio.currentTime = targetTime;
				}
				// Resume playing if was playing before page refresh
				if (state.isPlaying && this.audio) {
					await this.audio.play();
				}
			}
		} catch (err) {
			console.error('Failed to load queue state:', err);
		}
	}

	private debouncedSavePosition() {
		// Always save to localStorage immediately for page refresh recovery
		this.savePositionToLocalStorage();

		// Debounce IndexedDB saves to avoid excessive writes
		if (this.saveTimeout) {
			clearTimeout(this.saveTimeout);
		}
		this.saveTimeout = setTimeout(() => {
			this.savePosition();
		}, 2000);
	}

	private async savePosition() {
		try {
			const article = get(currentArticle);
			const state = {
				articleIds: [...get(queue)], // Spread to plain array for IndexedDB
				currentArticleId: article?.id ?? null,
				currentTime: this.audio?.currentTime || 0,
				isPlaying: get(isPlaying)
			};
			await saveQueueState(state);
		} catch (err) {
			console.error('Failed to save position:', err);
		}
	}

	private async loadArticle(articleId: string, autoplay: boolean = true): Promise<boolean> {
		if (!this.audio) return false;

		// Generate unique ID for this load operation
		const loadId = ++this.currentLoadId;

		// Stop any currently playing audio first
		this.stopAllAudio();

		try {
			this.isLoadingArticle = true;
			playerStatus.set('loading');
			playerError.set(null);
			generationProgress.set(0);

			const article = await getArticle(articleId);

			// Check if another load started while we were fetching
			if (loadId !== this.currentLoadId) {
				return false;
			}

			if (!article) {
				playerStatus.set('error');
				playerError.set('Article not found');
				return false;
			}

			currentArticle.set(article);

			let track = await getAudioTrack(articleId);
			if (!track) {
				// Check if there's an active async job for this article
				const jobStatus = get(audioJobs).get(articleId);

				if (jobStatus && (jobStatus.status === 'pending' || jobStatus.status === 'processing' || jobStatus.status === 'downloading')) {
					// Wait for the async job to complete
					const audioBlob = await this.waitForAsyncJob(articleId, loadId);

					if (!audioBlob || loadId !== this.currentLoadId) {
						return false;
					}

					track = { audioBlob, duration: 0, articleId, id: '', createdAt: 0 };
				} else {
					// No async job - generate on demand (fallback)
					// Also create a job for tracking in case client disconnects
					try {
						const settings = await getSettings();
						const response = await fetch('/api/jobs', {
							method: 'POST',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify({
								articleId,
								title: article.title,
								content: article.content,
								language: article.language,
								voice: settings.voice
							})
						});

						if (response.ok) {
							const { jobId } = await response.json();
							jobPollingService.startPolling(articleId, jobId);

							// Wait for the job to complete
							const audioBlob = await this.waitForAsyncJob(articleId, loadId);

							if (!audioBlob || loadId !== this.currentLoadId) {
								return false;
							}

							track = { audioBlob, duration: 0, articleId, id: '', createdAt: 0 };
						} else {
							// Fallback to direct generation if job creation fails
							const audioBlob = await generateArticleAudio(articleId);

							if (loadId !== this.currentLoadId) {
								return false;
							}

							track = { audioBlob, duration: 0, articleId, id: '', createdAt: 0 };
						}
					} catch {
						// Fallback to direct generation
						const audioBlob = await generateArticleAudio(articleId);

						if (loadId !== this.currentLoadId) {
							return false;
						}

						track = { audioBlob, duration: 0, articleId, id: '', createdAt: 0 };
					}
				}
			}

			// Final check before setting audio source
			if (loadId !== this.currentLoadId) {
				return false;
			}

			// Clean up old object URLs
			this.cleanupObjectUrls();

			const url = URL.createObjectURL(track.audioBlob);
			this.objectUrls.push(url);
			this.audio.src = url;
			this.audio.playbackRate = get(playbackRate);

			// Reset buffered percent for new track
			bufferedPercent.set(0);

			if (autoplay) {
				const settings = await getSettings();

				// Play chime if enabled (check load ID before and after)
				if (settings.transitionChime && loadId === this.currentLoadId) {
					await this.playChime();
				}

				// Check again after chime
				if (loadId !== this.currentLoadId) {
					return false;
				}

				// Play intro if enabled
				if (settings.announceArticles && loadId === this.currentLoadId) {
					await this.playIntro(article.title, article.language);
				}

				// Final check before playing main audio
				if (loadId !== this.currentLoadId) {
					return false;
				}

				await this.audio.play();
			} else {
				playerStatus.set('paused');
			}

			// Preload next article
			this.preloadNext();
			generationProgress.set(0);

			return true;
		} catch (err) {
			// Only set error if this is still the active load
			if (loadId === this.currentLoadId) {
				console.error('Failed to load article:', err);
				playerStatus.set('error');
				playerError.set(err instanceof Error ? err.message : 'Failed to load article');
				generationProgress.set(0);
			}
			return false;
		} finally {
			if (loadId === this.currentLoadId) {
				this.isLoadingArticle = false;
			}
		}
	}

	private async playChime(): Promise<void> {
		return new Promise((resolve) => {
			if (!this.chimeAudio) {
				this.chimeAudio = new Audio('/sounds/chime.mp3');
			}
			this.chimeAudio.onended = () => resolve();
			this.chimeAudio.onerror = () => resolve();
			this.chimeAudio.play().catch(() => resolve());
		});
	}

	private async playIntro(title: string, language: 'en' | 'nl'): Promise<void> {
		try {
			const introBlob = await generateIntroAudio(title, language);
			return new Promise((resolve) => {
				if (!this.introAudio) {
					this.introAudio = new Audio();
				}
				const url = URL.createObjectURL(introBlob);
				this.introAudio.src = url;
				this.introAudio.onended = () => {
					URL.revokeObjectURL(url);
					setTimeout(resolve, 500); // 0.5s pause after intro
				};
				this.introAudio.onerror = () => resolve();
				// Set playing state when intro starts
				this.introAudio.play().then(() => {
					playerStatus.set('playing');
				}).catch(() => resolve());
			});
		} catch {
			// Ignore intro errors
		}
	}

	private async preloadNext() {
		const q = get(queue);

		// Preload first 2 tracks in queue for smoother playback
		const preloadCount = Math.min(2, q.length);

		for (let i = 0; i < preloadCount; i++) {
			const nextId = q[i];
			const track = await getAudioTrack(nextId);

			if (track) {
				// First track uses the preload audio element
				if (i === 0 && this.nextAudio) {
					const url = URL.createObjectURL(track.audioBlob);
					this.objectUrls.push(url);
					this.nextAudio.src = url;
					this.nextAudio.load();
				}
			}
			// If no track, let it be - it will be generated when needed
		}
	}

	// Wait for an async job to complete and return the audio blob
	private async waitForAsyncJob(articleId: string, loadId: number): Promise<Blob | null> {
		return new Promise((resolve) => {
			// Update progress based on job status
			const updateProgress = () => {
				const job = get(audioJobs).get(articleId);
				if (job) {
					generationProgress.set(job.progress);
				}
			};

			// Initial progress update
			updateProgress();

			// Subscribe to job status changes
			const unsubscribe = audioJobs.subscribe((jobs) => {
				// Check if load was cancelled
				if (loadId !== this.currentLoadId) {
					unsubscribe();
					resolve(null);
					return;
				}

				const job = jobs.get(articleId);

				if (!job) {
					// Job removed - audio should now be in IndexedDB
					unsubscribe();
					getAudioTrack(articleId).then((track) => {
						if (track) {
							resolve(track.audioBlob);
						} else {
							resolve(null);
						}
					});
					return;
				}

				// Update progress
				generationProgress.set(job.progress);

				if (job.status === 'failed') {
					unsubscribe();
					resolve(null);
				}
			});

			// Also set a timeout to prevent infinite waiting
			setTimeout(() => {
				unsubscribe();
				resolve(null);
			}, 5 * 60 * 1000); // 5 minute timeout
		});
	}

	private cleanupObjectUrls() {
		for (const url of this.objectUrls) {
			URL.revokeObjectURL(url);
		}
		this.objectUrls = [];
	}

	// Stop all audio sources to prevent multiple playing at once
	private stopAllAudio() {
		if (this.audio) {
			this.audio.pause();
		}
		if (this.introAudio) {
			this.introAudio.pause();
			this.introAudio.src = '';
		}
		if (this.chimeAudio) {
			this.chimeAudio.pause();
			this.chimeAudio.currentTime = 0;
		}
	}

	private updateMediaSession() {
		if (!('mediaSession' in navigator)) return;

		const article = get(currentArticle);
		if (!article) return;

		// Update metadata
		navigator.mediaSession.metadata = new MediaMetadata({
			title: article.title,
			artist: 'Article Reader',
			album: article.language === 'nl' ? 'Dutch Articles' : 'English Articles'
		});

		// Update playback state
		navigator.mediaSession.playbackState = get(isPlaying) ? 'playing' : 'paused';

		// Register all action handlers with error handling
		const handlers: Record<string, MediaSessionActionHandler | null> = {
			play: () => this.play(),
			pause: () => this.pause(),
			stop: () => this.clearQueue(),
			previoustrack: () => this.playPrevious(),
			nexttrack: () => this.playNext(),
			seekbackward: () => this.seekRelative(-15),
			seekforward: () => this.seekRelative(15),
			seekto: (details) => {
				if (details?.seekTime !== undefined) {
					this.seek(details.seekTime);
				}
			}
		};

		for (const [action, handler] of Object.entries(handlers)) {
			try {
				navigator.mediaSession.setActionHandler(action as MediaSessionAction, handler);
			} catch {
				// Action not supported on this platform
			}
		}
	}

	async play() {
		if (!this.audio) return;

		// If no source is loaded, start playing from queue if available
		if (!this.audio.src) {
			const q = get(queue);
			if (q.length > 0) {
				// Pop first item from queue and play it
				const nextId = q[0];
				queue.update((items) => items.slice(1));
				await this.loadArticle(nextId, true);
				return; // loadArticle will start playing
			}
			return;
		}

		await this.audio.play();
	}

	pause() {
		// Stop all audio sources
		if (this.audio) {
			this.audio.pause();
		}
		if (this.introAudio) {
			this.introAudio.pause();
		}
		if (this.chimeAudio) {
			this.chimeAudio.pause();
		}
		// Explicitly set status to paused (don't rely on pause event which may not fire)
		const status = get(playerStatus);
		if (status === 'playing' || status === 'buffering') {
			playerStatus.set('paused');
		}
	}

	async togglePlay() {
		if (get(isPlaying)) {
			this.pause();
		} else {
			await this.play();
		}
	}

	seek(time: number) {
		if (this.audio) {
			this.audio.currentTime = Math.max(0, Math.min(time, this.audio.duration || 0));
		}
	}

	seekRelative(delta: number) {
		if (this.audio) {
			this.seek(this.audio.currentTime + delta);
		}
	}

	setVolume(vol: number) {
		if (this.audio) {
			this.audio.volume = Math.max(0, Math.min(1, vol));
			volume.set(this.audio.volume);
		}
	}

	setPlaybackRate(rate: number) {
		if (this.audio) {
			this.audio.playbackRate = rate;
			playbackRate.set(rate);
		}
	}

	async playNext(): Promise<boolean> {
		const repeat = get(repeatMode);

		// Repeat one: restart current track
		if (repeat === 'one') {
			this.seek(0);
			await this.play();
			return true;
		}

		// Get the queue (future items only - current is not in queue)
		const q = get(queue);

		// If queue has items, pop the first one and play it
		if (q.length > 0) {
			const nextId = q[0];
			queue.update((items) => items.slice(1)); // Remove first item
			await this.loadArticle(nextId);
			await this.savePosition();
			return true;
		}

		// Queue empty - go idle
		currentArticle.set(null);
		playerStatus.set('idle');
		if (this.audio) {
			this.audio.pause();
			this.audio.src = '';
		}
		await this.savePosition();
		return false;
	}

	async playPrevious(): Promise<boolean> {
		// Without playback history, just restart the current track
		this.seek(0);
		return true;
	}

	// Play an article directly (independent of queue)
	async playArticle(articleId: string) {
		// Simply load and play the article - don't touch the queue
		await this.loadArticle(articleId);
		await this.savePosition();
	}

	async addToQueue(articleId: string) {
		const q = get(queue);
		if (!q.includes(articleId)) {
			queue.update((items) => [...items, articleId]);
			await this.savePosition();
		}
	}

	// Remove an article from the queue - never affects current playback
	async removeFromQueue(articleId: string) {
		const q = get(queue);
		if (q.includes(articleId)) {
			queue.update((items) => items.filter((id) => id !== articleId));
			await this.savePosition();
		}
	}

	// Clear the queue - does NOT stop current playback
	async clearQueue() {
		queue.set([]);
		shuffleEnabled.set(false);
		await this.savePosition();
	}

	// Stop playback completely and clear everything
	async stop() {
		queue.set([]);
		currentArticle.set(null);
		playerStatus.set('idle');
		playerError.set(null);
		shuffleEnabled.set(false);
		if (this.audio) {
			this.audio.pause();
			this.audio.src = '';
		}
		await this.savePosition();
	}

	// Cycle repeat mode: off → all → one → off
	cycleRepeatMode() {
		const current = get(repeatMode);
		const modes: Array<'off' | 'all' | 'one'> = ['off', 'all', 'one'];
		const currentIdx = modes.indexOf(current);
		const nextIdx = (currentIdx + 1) % modes.length;
		repeatMode.set(modes[nextIdx]);
	}

	// Toggle shuffle mode - shuffles the queue
	toggleShuffle() {
		const isShuffled = get(shuffleEnabled);
		const q = get(queue);

		if (!isShuffled && q.length > 1) {
			// Fisher-Yates shuffle on queue
			const shuffled = [...q];
			for (let i = shuffled.length - 1; i > 0; i--) {
				const j = Math.floor(Math.random() * (i + 1));
				[shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
			}
			queue.set(shuffled);
			shuffleEnabled.set(true);
		} else {
			// Just toggle off - queue stays in current order
			shuffleEnabled.set(false);
		}
	}

	// Insert article to play next (at front of queue)
	async insertPlayNext(articleId: string) {
		const q = get(queue);

		if (q.includes(articleId)) {
			// Already in queue - move it to front
			if (q[0] === articleId) return; // Already first
			const newQueue = q.filter((id) => id !== articleId);
			newQueue.unshift(articleId);
			queue.set(newQueue);
		} else {
			// Add new article at front of queue
			queue.update((items) => [articleId, ...items]);
		}
		await this.savePosition();
	}

	// Play a specific item from the queue (removes it and items before it from queue)
	async playFromQueue(articleId: string) {
		const q = get(queue);
		const index = q.indexOf(articleId);

		if (index === -1) return; // Not in queue

		// Remove the item and all items before it from the queue
		queue.update((items) => items.slice(index + 1));

		// Play the selected item
		await this.loadArticle(articleId);
		await this.savePosition();
	}

	// Reorder the queue
	async reorderQueue(newOrder: string[]) {
		const plainOrder = [...newOrder]; // Convert to plain array (might be Svelte proxy)
		queue.set(plainOrder);
		await this.savePosition();
	}

	destroy() {
		this.cleanupObjectUrls();
		if (this.saveTimeout) {
			clearTimeout(this.saveTimeout);
		}
		this.savePosition();
		this.savePositionToLocalStorage();
		if (typeof window !== 'undefined') {
			window.removeEventListener('beforeunload', this.boundBeforeUnload);
			document.removeEventListener('visibilitychange', this.boundVisibilityChange);
		}
	}
}

export const audioManager = typeof window !== 'undefined' ? new AudioManager() : null;
