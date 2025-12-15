import { json, error } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import type { RequestHandler } from './$types';

// OpenAI/Naga.ac GPT-4o-mini-tts available voices
const OPENAI_VOICES = [
	{ id: 'alloy', name: 'Alloy', gender: 'neutral' },
	{ id: 'ash', name: 'Ash', gender: 'male' },
	{ id: 'ballad', name: 'Ballad', gender: 'male' },
	{ id: 'coral', name: 'Coral', gender: 'female' },
	{ id: 'echo', name: 'Echo', gender: 'male' },
	{ id: 'fable', name: 'Fable', gender: 'male' },
	{ id: 'nova', name: 'Nova', gender: 'female' },
	{ id: 'onyx', name: 'Onyx', gender: 'male' },
	{ id: 'sage', name: 'Sage', gender: 'female' },
	{ id: 'shimmer', name: 'Shimmer', gender: 'female' },
	{ id: 'verse', name: 'Verse', gender: 'male' }
];

export const GET: RequestHandler = async () => {
	if (!env.NAGA_API_KEY) {
		throw error(500, 'Naga API key not configured');
	}

	// GPT-4o-mini-tts supports 50+ languages with auto-detection
	// Same voices work for all languages
	return json({ voices: OPENAI_VOICES });
};
