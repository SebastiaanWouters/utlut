import { existsSync, mkdirSync, writeFileSync, unlinkSync } from 'fs';
import { join } from 'path';
import { chunkText } from '$lib/utils/textChunker';
import { preprocessForTTSWithLanguage } from '$lib/utils/ttsPreprocessor';
import {
	getNextPendingJob,
	startJob,
	updateJobProgress,
	completeJob,
	failJob,
	getExpiredJobs,
	deleteJob,
	type AudioJob
} from '$lib/server/db/jobQueue';

const NAGA_API = 'https://api.naga.ac/v1/audio/speech';
const AUDIO_DIR = join(process.cwd(), 'data', 'audio');

// Ensure audio directory exists
if (!existsSync(AUDIO_DIR)) {
	mkdirSync(AUDIO_DIR, { recursive: true });
}

export interface ProcessResult {
	processed: number;
	failed: number;
	errors: string[];
}

export async function processNextJob(apiKey: string, model: string = 'gpt-4o-mini-tts:free'): Promise<boolean> {
	const job = getNextPendingJob();
	if (!job) {
		return false;
	}

	try {
		await processJob(job, apiKey, model);
		return true;
	} catch (error) {
		console.error(`Error processing job ${job.id}:`, error);
		const errorMessage = error instanceof Error ? error.message : 'Unknown error';
		failJob(job.id, errorMessage);
		return true; // We did process a job (even though it failed)
	}
}

export async function processJobs(
	apiKey: string,
	model: string = 'gpt-4o-mini-tts:free',
	maxJobs: number = 5
): Promise<ProcessResult> {
	const result: ProcessResult = {
		processed: 0,
		failed: 0,
		errors: []
	};

	for (let i = 0; i < maxJobs; i++) {
		const job = getNextPendingJob();
		if (!job) {
			break;
		}

		try {
			await processJob(job, apiKey, model);
			result.processed++;
		} catch (error) {
			result.failed++;
			const errorMessage = error instanceof Error ? error.message : 'Unknown error';
			result.errors.push(`Job ${job.id}: ${errorMessage}`);
			failJob(job.id, errorMessage);
		}
	}

	return result;
}

async function processJob(job: AudioJob, apiKey: string, model: string): Promise<void> {
	// Mark job as processing
	startJob(job.id);

	// Preprocess content for TTS
	const language = job.language === 'nl' ? 'nl' : 'en';
	const processedContent = preprocessForTTSWithLanguage(job.content, language);

	// Chunk the preprocessed content
	const chunks = chunkText(processedContent);
	const totalChunks = chunks.length;

	// Update with total chunks
	updateJobProgress(job.id, 0, totalChunks);

	// Process each chunk
	const audioChunks: ArrayBuffer[] = [];

	for (let i = 0; i < chunks.length; i++) {
		const chunk = chunks[i];
		const audioBuffer = await generateAudioChunk(chunk, job.voice, apiKey, model);
		audioChunks.push(audioBuffer);

		// Update progress after each chunk
		updateJobProgress(job.id, i + 1, totalChunks);
	}

	// Combine all audio chunks
	const combinedAudio = combineAudioChunks(audioChunks);

	// Save to temp file
	const audioPath = join(AUDIO_DIR, `${job.id}.mp3`);
	writeFileSync(audioPath, combinedAudio);

	// Estimate duration (rough estimate: ~150 words per minute for TTS)
	const wordCount = job.content.split(/\s+/).length;
	const estimatedDuration = (wordCount / 150) * 60; // in seconds

	// Mark job as completed
	completeJob(job.id, audioPath, estimatedDuration);
}

async function generateAudioChunk(
	text: string,
	voice: string,
	apiKey: string,
	model: string
): Promise<ArrayBuffer> {
	const response = await fetch(NAGA_API, {
		method: 'POST',
		headers: {
			'Authorization': `Bearer ${apiKey}`,
			'Content-Type': 'application/json'
		},
		body: JSON.stringify({
			model,
			input: text,
			voice: voice || 'alloy',
			response_format: 'mp3',
			speed: 1.0
		})
	});

	if (!response.ok) {
		const errorText = await response.text();
		console.error('Naga API error:', errorText);
		throw new Error(`TTS API error: ${response.status} ${response.statusText}`);
	}

	return response.arrayBuffer();
}

function combineAudioChunks(chunks: ArrayBuffer[]): Buffer {
	const totalLength = chunks.reduce((sum, chunk) => sum + chunk.byteLength, 0);
	const combined = new Uint8Array(totalLength);

	let offset = 0;
	for (const chunk of chunks) {
		combined.set(new Uint8Array(chunk), offset);
		offset += chunk.byteLength;
	}

	return Buffer.from(combined);
}

export function cleanupExpiredAudio(): number {
	const expiredJobs = getExpiredJobs();
	let cleaned = 0;

	for (const job of expiredJobs) {
		if (job.audio_path && existsSync(job.audio_path)) {
			try {
				unlinkSync(job.audio_path);
				cleaned++;
			} catch (error) {
				console.error(`Failed to delete audio file ${job.audio_path}:`, error);
			}
		}
		deleteJob(job.id);
	}

	return cleaned;
}

export function getAudioPath(jobId: string): string {
	return join(AUDIO_DIR, `${jobId}.mp3`);
}
