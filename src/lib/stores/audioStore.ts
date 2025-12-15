import { writable, derived, get } from 'svelte/store';
import type { Article } from '$lib/db/database';

// Player Status - single source of truth for playback state
export type PlayerStatus =
	| 'idle' // No track loaded
	| 'loading' // Fetching article/generating TTS
	| 'buffering' // Audio buffering (waiting for data)
	| 'playing' // Actively playing
	| 'paused' // User paused
	| 'error'; // Playback error

export const playerStatus = writable<PlayerStatus>('idle');
export const playerError = writable<string | null>(null);

// Repeat and Shuffle modes
export type RepeatMode = 'off' | 'all' | 'one';
export const repeatMode = writable<RepeatMode>('off');
export const shuffleEnabled = writable(false);

// Core playback state
export const currentArticle = writable<Article | null>(null);
export const currentTime = writable(0);
export const duration = writable(0);
export const volume = writable(1);
export const playbackRate = writable(1);
export const queue = writable<string[]>([]);

// Generation progress (for TTS)
export const generationProgress = writable(0);

// Buffered amount (0-100%)
export const bufferedPercent = writable(0);

// Derived convenience stores for backward compatibility
export const isPlaying = derived(playerStatus, ($status) => $status === 'playing');
export const isLoading = derived(
	playerStatus,
	($status) => $status === 'loading' || $status === 'buffering'
);
export const isIdle = derived(playerStatus, ($status) => $status === 'idle');
export const isGenerating = derived(playerStatus, ($status) => $status === 'loading');
export const hasError = derived(playerStatus, ($status) => $status === 'error');

// hasNext is true if there are items in the queue
export const hasNext = derived(queue, ($queue) => {
	return $queue.length > 0;
});

// hasPrevious - allows restarting the current track
export const hasPrevious = derived(currentArticle, ($currentArticle) => {
	return $currentArticle !== null;
});

// Current article ID from the currentArticle store
export const currentArticleId = derived(currentArticle, ($currentArticle) => {
	return $currentArticle?.id || null;
});

export const progress = derived([currentTime, duration], ([$currentTime, $duration]) => {
	return $duration > 0 ? ($currentTime / $duration) * 100 : 0;
});

// Status label for UI display
export const statusLabel = derived(
	[playerStatus, generationProgress],
	([$status, $progress]) => {
		switch ($status) {
			case 'idle':
				return '';
			case 'loading':
				return $progress > 0 ? `Generating... ${$progress}%` : 'Loading...';
			case 'buffering':
				return 'Buffering...';
			case 'playing':
				return 'Playing';
			case 'paused':
				return 'Paused';
			case 'error':
				return 'Error';
			default:
				return '';
		}
	}
);

export function formatTime(seconds: number): string {
	const mins = Math.floor(seconds / 60);
	const secs = Math.floor(seconds % 60);
	return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// ===== Audio Job Tracking for Async Generation =====

export type JobStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'downloading';

export interface AudioJobStatus {
	jobId: string;
	articleId: string;
	status: JobStatus;
	progress: number;
	error?: string;
}

// Map of articleId -> job status
export const audioJobs = writable<Map<string, AudioJobStatus>>(new Map());

// Derived: list of article IDs currently generating
export const generatingArticles = derived(audioJobs, ($jobs) => {
	return Array.from($jobs.entries())
		.filter(([_, job]) => job.status === 'pending' || job.status === 'processing')
		.map(([articleId]) => articleId);
});

// Derived: count of articles generating
export const generatingCount = derived(generatingArticles, ($articles) => $articles.length);

// Helper to update a specific job
export function updateAudioJob(articleId: string, update: Partial<AudioJobStatus>): void {
	audioJobs.update((jobs) => {
		const existing = jobs.get(articleId);
		if (existing) {
			jobs.set(articleId, { ...existing, ...update });
		} else if (update.jobId) {
			jobs.set(articleId, {
				jobId: update.jobId,
				articleId,
				status: update.status || 'pending',
				progress: update.progress || 0,
				error: update.error
			});
		}
		return new Map(jobs);
	});
}

// Helper to remove a job (when audio is downloaded/ready)
export function removeAudioJob(articleId: string): void {
	audioJobs.update((jobs) => {
		jobs.delete(articleId);
		return new Map(jobs);
	});
}

// Helper to get job status for a specific article
export function getAudioJobStatus(articleId: string): AudioJobStatus | undefined {
	return get(audioJobs).get(articleId);
}
