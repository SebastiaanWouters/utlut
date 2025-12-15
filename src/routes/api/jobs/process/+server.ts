import { json, error } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { processJobs, cleanupExpiredAudio } from '$lib/server/workers/audioWorker';
import { getPendingJobCount, getProcessingJobCount } from '$lib/server/db/jobQueue';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = async ({ request }) => {
	if (!env.NAGA_API_KEY) {
		throw error(500, 'Naga API key not configured');
	}

	const body = await request.json().catch(() => ({}));
	const maxJobs = Math.min(body.maxJobs || 5, 10); // Cap at 10 jobs per request

	const model = env.NAGA_TTS_MODEL || 'gpt-4o-mini-tts:free';

	// Process pending jobs
	const result = await processJobs(env.NAGA_API_KEY, model, maxJobs);

	// Also cleanup any expired audio files
	const cleaned = cleanupExpiredAudio();

	return json({
		processed: result.processed,
		failed: result.failed,
		errors: result.errors,
		cleaned,
		pendingCount: getPendingJobCount(),
		processingCount: getProcessingJobCount()
	});
};

// GET endpoint to check status
export const GET: RequestHandler = async () => {
	return json({
		pendingCount: getPendingJobCount(),
		processingCount: getProcessingJobCount()
	});
};
