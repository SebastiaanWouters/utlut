import { json, error } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { getJob } from '$lib/server/db/jobQueue';
import { processNextJob } from '$lib/server/workers/audioWorker';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ params }) => {
	const { id } = params;

	const job = getJob(id);
	if (!job) {
		throw error(404, 'Job not found');
	}

	// If there are pending jobs, try to process one in background
	// This keeps processing moving even if client is just polling
	if (job.status === 'pending' && env.NAGA_API_KEY) {
		const model = env.NAGA_TTS_MODEL || 'gpt-4o-mini-tts:free';
		setTimeout(() => {
			processNextJob(env.NAGA_API_KEY!, model).catch(err => {
				console.error('Background job processing error:', err);
			});
		}, 0);
	}

	return json({
		jobId: job.id,
		articleId: job.article_id,
		status: job.status,
		progress: job.progress,
		error: job.last_error,
		audioDuration: job.audio_duration,
		downloadReady: job.status === 'completed' && !!job.audio_path
	});
};
