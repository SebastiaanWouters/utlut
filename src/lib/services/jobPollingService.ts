import { browser } from '$app/environment';
import { saveAudioTrack } from '$lib/db/database';
import {
	audioJobs,
	updateAudioJob,
	removeAudioJob,
	type AudioJobStatus
} from '$lib/stores/audioStore';
import { get } from 'svelte/store';

const POLL_INTERVAL = 2000; // 2 seconds
const STORAGE_KEY = 'activeAudioJobs';

interface JobStatusResponse {
	jobId: string;
	articleId: string;
	status: 'pending' | 'processing' | 'completed' | 'failed';
	progress: number;
	error?: string;
	audioDuration?: number;
	downloadReady: boolean;
}

class JobPollingService {
	private intervals: Map<string, ReturnType<typeof setInterval>> = new Map();
	private downloading: Set<string> = new Set();

	constructor() {
		if (browser) {
			// Restore active jobs from localStorage on init
			this.restoreActiveJobs();
		}
	}

	startPolling(articleId: string, jobId: string): void {
		if (!browser) return;

		// Don't start duplicate polling
		if (this.intervals.has(articleId)) {
			return;
		}

		// Add to store immediately
		updateAudioJob(articleId, {
			jobId,
			articleId,
			status: 'pending',
			progress: 0
		});

		// Save to localStorage
		this.saveActiveJobs();

		// Start polling
		const poll = async () => {
			try {
				const status = await this.fetchJobStatus(jobId);

				if (status.status === 'completed' && status.downloadReady) {
					// Stop polling first
					this.stopPolling(articleId);

					// Download and store audio
					await this.downloadAndStore(articleId, jobId, status.audioDuration);
				} else if (status.status === 'failed') {
					// Update with error and stop polling
					updateAudioJob(articleId, {
						status: 'failed',
						progress: status.progress,
						error: status.error
					});
					this.stopPolling(articleId);
				} else {
					// Update progress
					updateAudioJob(articleId, {
						status: status.status,
						progress: status.progress
					});
				}
			} catch (error) {
				console.error(`Error polling job ${jobId}:`, error);
				// Don't stop polling on transient errors
			}
		};

		// Initial poll immediately
		poll();

		// Set up interval
		const intervalId = setInterval(poll, POLL_INTERVAL);
		this.intervals.set(articleId, intervalId);
	}

	stopPolling(articleId: string): void {
		const intervalId = this.intervals.get(articleId);
		if (intervalId) {
			clearInterval(intervalId);
			this.intervals.delete(articleId);
		}
		this.saveActiveJobs();
	}

	stopAllPolling(): void {
		for (const [articleId, intervalId] of this.intervals) {
			clearInterval(intervalId);
		}
		this.intervals.clear();
	}

	private async fetchJobStatus(jobId: string): Promise<JobStatusResponse> {
		const response = await fetch(`/api/jobs/${jobId}`);
		if (!response.ok) {
			throw new Error(`Failed to fetch job status: ${response.status}`);
		}
		return response.json();
	}

	private async downloadAndStore(
		articleId: string,
		jobId: string,
		audioDuration?: number
	): Promise<void> {
		// Prevent duplicate downloads
		if (this.downloading.has(articleId)) {
			return;
		}
		this.downloading.add(articleId);

		try {
			// Update status to downloading
			updateAudioJob(articleId, { status: 'downloading' });

			// Download audio from server
			const response = await fetch(`/api/jobs/${jobId}/audio`);
			if (!response.ok) {
				throw new Error(`Failed to download audio: ${response.status}`);
			}

			const audioBlob = await response.blob();

			// Get actual duration from audio
			let duration = audioDuration || 0;
			if (!duration) {
				duration = await this.getAudioDuration(audioBlob);
			}

			// Store in IndexedDB
			await saveAudioTrack({
				articleId,
				audioBlob,
				duration
			});

			// Remove from jobs map (audio is now local)
			removeAudioJob(articleId);
		} catch (error) {
			console.error(`Error downloading audio for ${articleId}:`, error);
			updateAudioJob(articleId, {
				status: 'failed',
				error: error instanceof Error ? error.message : 'Download failed'
			});
		} finally {
			this.downloading.delete(articleId);
			this.saveActiveJobs();
		}
	}

	private getAudioDuration(blob: Blob): Promise<number> {
		return new Promise((resolve) => {
			const audio = new Audio();
			const url = URL.createObjectURL(blob);

			audio.addEventListener('loadedmetadata', () => {
				URL.revokeObjectURL(url);
				resolve(audio.duration || 0);
			});

			audio.addEventListener('error', () => {
				URL.revokeObjectURL(url);
				resolve(0);
			});

			audio.src = url;
		});
	}

	private saveActiveJobs(): void {
		if (!browser) return;

		const jobs = get(audioJobs);
		const activeJobs: Record<string, string> = {};

		for (const [articleId, job] of jobs) {
			// Only save jobs that are still being processed
			if (job.status === 'pending' || job.status === 'processing') {
				activeJobs[articleId] = job.jobId;
			}
		}

		localStorage.setItem(STORAGE_KEY, JSON.stringify(activeJobs));
	}

	private restoreActiveJobs(): void {
		if (!browser) return;

		try {
			const stored = localStorage.getItem(STORAGE_KEY);
			if (!stored) return;

			const activeJobs = JSON.parse(stored) as Record<string, string>;

			for (const [articleId, jobId] of Object.entries(activeJobs)) {
				// Resume polling for each active job
				this.startPolling(articleId, jobId);
			}
		} catch (error) {
			console.error('Error restoring active jobs:', error);
			localStorage.removeItem(STORAGE_KEY);
		}
	}

	// Retry a failed job by creating a new one
	async retryJob(articleId: string, title: string, content: string, language: string, voice?: string): Promise<void> {
		// Remove the failed job from the store
		removeAudioJob(articleId);

		// Create a new job
		const response = await fetch('/api/jobs', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ articleId, title, content, language, voice })
		});

		if (!response.ok) {
			throw new Error('Failed to create retry job');
		}

		const { jobId } = await response.json();
		this.startPolling(articleId, jobId);
	}

	// Check if an article has an active job
	hasActiveJob(articleId: string): boolean {
		const jobs = get(audioJobs);
		const job = jobs.get(articleId);
		return job !== undefined && (job.status === 'pending' || job.status === 'processing' || job.status === 'downloading');
	}
}

// Singleton instance
export const jobPollingService = new JobPollingService();
