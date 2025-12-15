import { json, error } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { createJob, getJobByArticleId } from '$lib/server/db/jobQueue';
import { processNextJob } from '$lib/server/workers/audioWorker';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = async ({ request }) => {
	const { articleId, title, content, language, voice } = await request.json();

	if (!articleId || !title || !content || !language) {
		throw error(400, 'Missing required fields: articleId, title, content, language');
	}

	// Check if job already exists for this article
	const existingJob = getJobByArticleId(articleId);
	if (existingJob) {
		// Return existing job instead of creating duplicate
		return json({
			jobId: existingJob.id,
			status: existingJob.status,
			existing: true
		});
	}

	// Create new job
	const job = createJob(articleId, title, content, language, voice || 'alloy');

	// Trigger processing in background (fire and forget)
	if (env.NAGA_API_KEY) {
		const model = env.NAGA_TTS_MODEL || 'gpt-4o-mini-tts:free';
		// Use setTimeout to not block the response
		setTimeout(() => {
			processNextJob(env.NAGA_API_KEY!, model).catch(err => {
				console.error('Background job processing error:', err);
			});
		}, 0);
	}

	return json({
		jobId: job.id,
		status: job.status,
		existing: false
	});
};
