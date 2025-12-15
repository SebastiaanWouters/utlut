import { getArticle, getAudioTrack, saveAudioTrack, getSettings } from '$lib/db/database';
import { generationProgress } from '$lib/stores/audioStore';

export async function generateArticleAudio(articleId: string): Promise<Blob> {
	const existingTrack = await getAudioTrack(articleId);
	if (existingTrack) {
		return existingTrack.audioBlob;
	}

	const article = await getArticle(articleId);
	if (!article) {
		throw new Error('Article not found');
	}

	const settings = await getSettings();

	// Note: playerStatus is set to 'loading' by AudioManager before calling this
	generationProgress.set(10); // Started

	try {
		generationProgress.set(30); // Preparing request

		const response = await fetch('/api/tts', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				text: article.content,
				voice: settings.voice || undefined
			})
		});

		if (!response.ok) {
			const errorText = await response.text().catch(() => 'Unknown error');
			throw new Error(`Failed to generate audio: ${errorText}`);
		}

		generationProgress.set(70); // Response received

		const audioBlob = await response.blob();

		generationProgress.set(85); // Processing

		const duration = await getAudioDuration(audioBlob);

		await saveAudioTrack({
			articleId,
			audioBlob,
			duration
		});

		generationProgress.set(100); // Complete

		return audioBlob;
	} catch (error) {
		generationProgress.set(0);
		throw error;
	}
}

export async function generateIntroAudio(title: string, language: 'en' | 'nl'): Promise<Blob> {
	const settings = await getSettings();
	const text = language === 'nl' ? `Nu speelt: ${title}` : `Now playing: ${title}`;

	const response = await fetch('/api/tts', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({
			text,
			voice: settings.voice || undefined
		})
	});

	if (!response.ok) {
		throw new Error('Failed to generate intro');
	}

	return response.blob();
}

function getAudioDuration(blob: Blob): Promise<number> {
	return new Promise((resolve, reject) => {
		const audio = new Audio();
		audio.addEventListener('loadedmetadata', () => {
			resolve(audio.duration);
		});
		audio.addEventListener('error', reject);
		audio.src = URL.createObjectURL(blob);
	});
}

export async function fetchVoices(): Promise<
	Array<{
		id: string;
		name: string;
		gender?: string;
	}>
> {
	const response = await fetch('/api/voices');
	if (!response.ok) {
		throw new Error('Failed to fetch voices');
	}
	const data = await response.json();
	return data.voices;
}
