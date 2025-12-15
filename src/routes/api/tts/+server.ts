import { error } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { chunkText } from '$lib/utils/textChunker';
import type { RequestHandler } from './$types';

const NAGA_API = 'https://api.naga.ac/v1/audio/speech';

export const POST: RequestHandler = async ({ request }) => {
	const { text, voice } = await request.json();

	if (!text || typeof text !== 'string') {
		throw error(400, 'Text is required');
	}

	if (!env.NAGA_API_KEY) {
		throw error(500, 'Naga API key not configured');
	}

	const voiceName = voice || 'alloy'; // Default to alloy
	const model = env.NAGA_TTS_MODEL || 'gpt-4o-mini-tts:free';
	const chunks = chunkText(text);
	const audioChunks: ArrayBuffer[] = [];

	try {
		for (const chunk of chunks) {
			const response = await fetch(NAGA_API, {
				method: 'POST',
				headers: {
					'Authorization': `Bearer ${env.NAGA_API_KEY}`,
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					model,
					input: chunk,
					voice: voiceName,
					response_format: 'mp3',
					speed: 1.0
				})
			});

			if (!response.ok) {
				const errorText = await response.text();
				console.error('Naga API error:', errorText);
				throw error(response.status, `TTS API error: ${response.statusText}`);
			}

			const audioBuffer = await response.arrayBuffer();
			audioChunks.push(audioBuffer);
		}

		// Concatenate all audio chunks
		const totalLength = audioChunks.reduce((sum, chunk) => sum + chunk.byteLength, 0);
		const combined = new Uint8Array(totalLength);
		let offset = 0;
		for (const chunk of audioChunks) {
			combined.set(new Uint8Array(chunk), offset);
			offset += chunk.byteLength;
		}

		return new Response(combined, {
			headers: {
				'Content-Type': 'audio/mpeg',
				'Content-Length': totalLength.toString()
			}
		});
	} catch (err) {
		if (err && typeof err === 'object' && 'status' in err) {
			throw err;
		}
		console.error('TTS error:', err);
		throw error(500, 'Failed to generate audio');
	}
};
