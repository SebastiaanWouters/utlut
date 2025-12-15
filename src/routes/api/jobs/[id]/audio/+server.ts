import { error } from '@sveltejs/kit';
import { readFileSync, existsSync, statSync } from 'fs';
import { getJob } from '$lib/server/db/jobQueue';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ params }) => {
	const { id } = params;

	const job = getJob(id);
	if (!job) {
		throw error(404, 'Job not found');
	}

	if (job.status !== 'completed') {
		throw error(400, 'Audio not ready yet');
	}

	if (!job.audio_path || !existsSync(job.audio_path)) {
		throw error(404, 'Audio file not found');
	}

	const audioBuffer = readFileSync(job.audio_path);
	const stats = statSync(job.audio_path);

	return new Response(audioBuffer, {
		headers: {
			'Content-Type': 'audio/mpeg',
			'Content-Length': stats.size.toString(),
			'Content-Disposition': `attachment; filename="${job.article_id}.mp3"`,
			'Cache-Control': 'private, max-age=3600'
		}
	});
};
