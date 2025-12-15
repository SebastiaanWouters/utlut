import Dexie, { type EntityTable } from 'dexie';

export interface Article {
	id: string;
	title: string;
	url?: string;
	content: string;
	language: 'en' | 'nl';
	createdAt: number;
	audioGenerated: boolean;
	audioDuration?: number;
}

export interface AudioTrack {
	id: string;
	articleId: string;
	audioBlob: Blob;
	duration: number;
	createdAt: number;
}

export interface Playlist {
	id: string;
	name: string;
	description?: string;
	articleIds: string[];
	createdAt: number;
	updatedAt: number;
}

export interface QueueState {
	id: 'current';
	articleIds: string[];         // Future queue items only
	currentArticleId: string | null;  // Currently playing article (independent of queue)
	currentTime: number;
	isPlaying: boolean;
	updatedAt: number;
}

export interface Settings {
	id: 'user';
	voice: string;
	playbackSpeed: number;
	announceArticles: boolean;
	transitionChime: boolean;
}

const db = new Dexie('ArticleReaderDB') as Dexie & {
	articles: EntityTable<Article, 'id'>;
	audioTracks: EntityTable<AudioTrack, 'id'>;
	playlists: EntityTable<Playlist, 'id'>;
	queueState: EntityTable<QueueState, 'id'>;
	settings: EntityTable<Settings, 'id'>;
};

db.version(1).stores({
	articles: 'id, title, url, language, createdAt, audioGenerated',
	audioTracks: 'id, articleId, createdAt',
	playlists: 'id, name, createdAt, updatedAt',
	queueState: 'id',
	settings: 'id'
});

export { db };

// Article operations
export async function createArticle(article: Omit<Article, 'id' | 'createdAt' | 'audioGenerated'>): Promise<Article> {
	const newArticle: Article = {
		...article,
		id: crypto.randomUUID(),
		createdAt: Date.now(),
		audioGenerated: false
	};
	await db.articles.add(newArticle);
	return newArticle;
}

export async function getArticle(id: string): Promise<Article | undefined> {
	return db.articles.get(id);
}

export async function getAllArticles(): Promise<Article[]> {
	return db.articles.orderBy('createdAt').reverse().toArray();
}

export async function updateArticle(id: string, updates: Partial<Article>): Promise<void> {
	await db.articles.update(id, updates);
}

export async function deleteArticle(id: string): Promise<void> {
	await db.transaction('rw', [db.articles, db.audioTracks], async () => {
		await db.articles.delete(id);
		await db.audioTracks.where('articleId').equals(id).delete();
	});
}

// Audio track operations
export async function saveAudioTrack(track: Omit<AudioTrack, 'id' | 'createdAt'>): Promise<AudioTrack> {
	const newTrack: AudioTrack = {
		...track,
		id: crypto.randomUUID(),
		createdAt: Date.now()
	};
	await db.audioTracks.add(newTrack);
	await db.articles.update(track.articleId, {
		audioGenerated: true,
		audioDuration: track.duration
	});
	return newTrack;
}

export async function getAudioTrack(articleId: string): Promise<AudioTrack | undefined> {
	return db.audioTracks.where('articleId').equals(articleId).first();
}

export async function deleteAudioTrack(articleId: string): Promise<void> {
	await db.audioTracks.where('articleId').equals(articleId).delete();
	await db.articles.update(articleId, { audioGenerated: false, audioDuration: undefined });
}

// Playlist operations
export async function createPlaylist(name: string, description?: string): Promise<Playlist> {
	const playlist: Playlist = {
		id: crypto.randomUUID(),
		name,
		description,
		articleIds: [],
		createdAt: Date.now(),
		updatedAt: Date.now()
	};
	await db.playlists.add(playlist);
	return playlist;
}

export async function getPlaylist(id: string): Promise<Playlist | undefined> {
	return db.playlists.get(id);
}

export async function getAllPlaylists(): Promise<Playlist[]> {
	return db.playlists.orderBy('updatedAt').reverse().toArray();
}

export async function updatePlaylist(id: string, updates: Partial<Omit<Playlist, 'id' | 'createdAt'>>): Promise<void> {
	await db.playlists.update(id, { ...updates, updatedAt: Date.now() });
}

export async function deletePlaylist(id: string): Promise<void> {
	await db.playlists.delete(id);
}

export async function addArticleToPlaylist(playlistId: string, articleId: string): Promise<void> {
	const playlist = await db.playlists.get(playlistId);
	if (playlist && !playlist.articleIds.includes(articleId)) {
		await db.playlists.update(playlistId, {
			articleIds: [...playlist.articleIds, articleId],
			updatedAt: Date.now()
		});
	}
}

export async function removeArticleFromPlaylist(playlistId: string, articleId: string): Promise<void> {
	const playlist = await db.playlists.get(playlistId);
	if (playlist) {
		await db.playlists.update(playlistId, {
			articleIds: playlist.articleIds.filter((id) => id !== articleId),
			updatedAt: Date.now()
		});
	}
}

export async function reorderPlaylistArticles(playlistId: string, articleIds: string[]): Promise<void> {
	await db.playlists.update(playlistId, {
		articleIds,
		updatedAt: Date.now()
	});
}

// Queue operations
interface OldQueueState {
	id: 'current';
	articleIds: string[];
	currentIndex: number;
	currentTime: number;
	isPlaying: boolean;
	updatedAt: number;
}

export async function getQueueState(): Promise<QueueState> {
	const state = await db.queueState.get('current');
	if (state) {
		// Migration: if old format with currentIndex, convert to new format
		if ('currentIndex' in state && !('currentArticleId' in state)) {
			const oldState = state as unknown as OldQueueState;
			const currentArticleId = oldState.articleIds[oldState.currentIndex ?? 0] ?? null;
			return {
				id: 'current',
				currentArticleId,
				articleIds: oldState.articleIds.slice((oldState.currentIndex ?? 0) + 1), // Queue is items after current
				currentTime: oldState.currentTime,
				isPlaying: oldState.isPlaying,
				updatedAt: oldState.updatedAt
			};
		}
		return state;
	}
	return {
		id: 'current',
		articleIds: [],
		currentArticleId: null,
		currentTime: 0,
		isPlaying: false,
		updatedAt: Date.now()
	};
}

export async function saveQueueState(state: Omit<QueueState, 'id' | 'updatedAt'>): Promise<void> {
	await db.queueState.put({
		id: 'current',
		...state,
		updatedAt: Date.now()
	});
}

export async function addToQueue(articleId: string): Promise<void> {
	const state = await getQueueState();
	if (!state.articleIds.includes(articleId)) {
		await saveQueueState({
			articleIds: [...state.articleIds, articleId],
			currentArticleId: state.currentArticleId,
			currentTime: state.currentTime,
			isPlaying: state.isPlaying
		});
	}
}

export async function removeFromQueue(articleId: string): Promise<void> {
	const state = await getQueueState();
	if (state.articleIds.includes(articleId)) {
		await saveQueueState({
			articleIds: state.articleIds.filter((id) => id !== articleId),
			currentArticleId: state.currentArticleId,
			currentTime: state.currentTime,
			isPlaying: state.isPlaying
		});
	}
}

export async function reorderQueue(articleIds: string[]): Promise<void> {
	const state = await getQueueState();
	await saveQueueState({
		articleIds,
		currentArticleId: state.currentArticleId,
		currentTime: state.currentTime,
		isPlaying: state.isPlaying
	});
}

export async function clearQueue(): Promise<void> {
	const state = await getQueueState();
	await saveQueueState({
		articleIds: [],
		currentArticleId: state.currentArticleId,
		currentTime: state.currentTime,
		isPlaying: state.isPlaying
	});
}

// Settings operations
export async function getSettings(): Promise<Settings> {
	const settings = await db.settings.get('user');
	return settings ?? {
		id: 'user',
		voice: 'alloy',
		playbackSpeed: 1,
		announceArticles: true,
		transitionChime: true
	};
}

export async function updateSettings(updates: Partial<Omit<Settings, 'id'>>): Promise<void> {
	const current = await getSettings();
	await db.settings.put({ ...current, ...updates });
}

// Cleanup old audio (keep last N articles with audio)
export async function cleanupOldAudio(keepCount: number = 50): Promise<void> {
	const tracks = await db.audioTracks.orderBy('createdAt').reverse().toArray();
	if (tracks.length > keepCount) {
		const toDelete = tracks.slice(keepCount);
		for (const track of toDelete) {
			await deleteAudioTrack(track.articleId);
		}
	}
}
