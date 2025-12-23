function getDeviceToken() {
    const meta = document.querySelector('meta[name="utlut-device-token"]');
    return meta?.getAttribute('content') ?? '';
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function isValidDuration(duration) {
    return Number.isFinite(duration) && duration > 0;
}

function safeHostname(url) {
    try {
        const host = new URL(url).hostname;
        return host.replace('www.', '');
    } catch (e) {
        return url;
    }
}

// Persistent audio element - survives page navigations
if (!window.__utlutAudio) {
    window.__utlutAudio = new Audio();
    window.__utlutAudio.crossOrigin = 'anonymous';
}

// Persistent player state - survives page navigations
if (!window.__utlutPlayerState) {
    window.__utlutPlayerState = {
        queue: [],
        currentIndex: -1,
        currentTrack: null,
        playbackRate: parseFloat(localStorage.getItem('utlut-playback-rate')) || 1,
        shuffleEnabled: localStorage.getItem('utlut-shuffle') === 'true',
        repeatMode: localStorage.getItem('utlut-repeat') || 'off', // 'off', 'one', 'all'
        shuffleHistory: [], // Tracks already played in shuffle mode
    };
}

function createPlayerStore() {
    const audio = window.__utlutAudio;
    const persistedState = window.__utlutPlayerState;

    return {
        // config
        token: getDeviceToken(),

        // runtime
        audio,
        isReady: false,
        isPlaying: false,
        isOnline: navigator.onLine,

        // time/progress
        currentTime: 0,
        duration: 0,
        progress: 0,
        buffered: 0,

        // queue - initialized from persisted state
        queue: persistedState.queue,
        currentIndex: persistedState.currentIndex,
        currentTrack: persistedState.currentTrack,

        // playback settings - initialized from persisted state
        playbackRate: persistedState.playbackRate,
        shuffleEnabled: persistedState.shuffleEnabled,
        repeatMode: persistedState.repeatMode,
        shuffleHistory: persistedState.shuffleHistory,
        isLoading: false,

        init() {
            if (this.isReady) {
                // Restore state from audio element on re-init (page navigation)
                this.isPlaying = !audio.paused && !audio.ended;
                this.currentTime = audio.currentTime || 0;
                this.setDurationFromAudio();
                this.syncProgress();
                this.syncBuffered();
                // Apply persisted playback rate
                audio.playbackRate = this.playbackRate;
                return;
            }

            this.isReady = true;
            this.token = getDeviceToken();

            // Restore playing state from audio element
            this.isPlaying = !audio.paused && !audio.ended;
            this.currentTime = audio.currentTime || 0;
            this.setDurationFromAudio();
            this.syncProgress();
            this.syncBuffered();

            // Apply persisted playback rate
            audio.playbackRate = this.playbackRate;

            window.addEventListener('online', () => {
                this.isOnline = true;
            });

            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            audio.addEventListener('play', () => {
                this.isPlaying = true;
                if ('mediaSession' in navigator) {
                    navigator.mediaSession.playbackState = 'playing';
                }
            });

            audio.addEventListener('pause', () => {
                this.isPlaying = false;
                if ('mediaSession' in navigator) {
                    navigator.mediaSession.playbackState = 'paused';
                }
            });

            audio.addEventListener('timeupdate', () => {
                this.currentTime = audio.currentTime || 0;
                this.syncProgress();
                this.updatePositionState();
            });

            audio.addEventListener('loadedmetadata', () => {
                this.setDurationFromAudio();
                this.syncProgress();
                this.syncBuffered();
            });

            audio.addEventListener('durationchange', () => {
                this.setDurationFromAudio();
                this.syncProgress();
                this.syncBuffered();
            });

            audio.addEventListener('progress', () => {
                this.syncBuffered();
            });

            audio.addEventListener('ended', () => {
                this.next();
            });

            audio.addEventListener('error', (e) => {
                console.warn('Audio error:', e);
                this.isLoading = false;
                // Mark current track as not ready in the queue
                if (this.currentTrack) {
                    const idx = this.queue.findIndex((t) => t.id === this.currentTrack.id);
                    if (idx !== -1) {
                        this.queue[idx].audio_url = null;
                        this.queue[idx].status = 'pending';
                    }
                }
                // Try to skip to the next playable track
                this.next();
            });

            window.addEventListener('play-article', async (e) => {
                const articleId = e.detail?.articleId;
                if (!articleId) {
                    return;
                }

                await this.playArticle(articleId);
            });

            window.addEventListener('play-queue', async (e) => {
                const articleIds = e.detail?.articleIds || [];
                if (!Array.isArray(articleIds) || articleIds.length === 0) {
                    return;
                }

                await this.playQueue(articleIds);
            });

            window.addEventListener('play-playlist', async (e) => {
                const playlistId = e.detail?.playlistId;
                if (!playlistId) {
                    return;
                }

                await this.playPlaylist(playlistId);
            });

            window.addEventListener('add-to-queue', async (e) => {
                const articleId = e.detail?.articleId;
                if (!articleId) {
                    return;
                }

                await this.addToQueue(articleId);
            });

            window.addEventListener('play-next', async (e) => {
                const articleId = e.detail?.articleId;
                if (!articleId) {
                    return;
                }

                await this.playNext(articleId);
            });

            if ('mediaSession' in navigator) {
                navigator.mediaSession.setActionHandler('play', () => {
                    audio.play();
                });
                navigator.mediaSession.setActionHandler('pause', () => {
                    audio.pause();
                });
                navigator.mediaSession.setActionHandler('previoustrack', () => this.prev());
                navigator.mediaSession.setActionHandler('nexttrack', () => this.next());
                navigator.mediaSession.setActionHandler('seekto', (e) => {
                    if (e.seekTime === undefined) {
                        return;
                    }

                    audio.currentTime = e.seekTime;
                    this.currentTime = audio.currentTime || 0;
                    this.syncProgress();
                });
                navigator.mediaSession.setActionHandler('seekforward', () => {
                    audio.currentTime = Math.min(audio.currentTime + 30, audio.duration || audio.currentTime);
                    this.currentTime = audio.currentTime || 0;
                    this.syncProgress();
                });
                navigator.mediaSession.setActionHandler('seekbackward', () => {
                    audio.currentTime = Math.max(audio.currentTime - 30, 0);
                    this.currentTime = audio.currentTime || 0;
                    this.syncProgress();
                });
            }
        },

        setDurationFromAudio() {
            const duration = audio.duration;
            this.duration = isValidDuration(duration) ? duration : 0;
        },

        syncProgress() {
            if (!isValidDuration(this.duration)) {
                this.progress = 0;
                return;
            }

            const ratio = (this.currentTime || 0) / this.duration;
            this.progress = clamp(ratio * 100, 0, 100);
        },

        syncBuffered() {
            if (!isValidDuration(this.duration)) {
                this.buffered = 0;
                return;
            }

            try {
                const buf = audio.buffered;
                if (!buf || buf.length === 0) {
                    this.buffered = 0;
                    return;
                }

                const end = buf.end(buf.length - 1);
                this.buffered = clamp((end / this.duration) * 100, 0, 100);
            } catch (e) {
                this.buffered = 0;
            }
        },

        formatTime(seconds) {
            if (!seconds || Number.isNaN(seconds)) {
                return '0:00';
            }

            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },

        getHostname(url) {
            return safeHostname(url);
        },

        // Persist state to window for navigation survival
        persistState() {
            window.__utlutPlayerState.queue = this.queue;
            window.__utlutPlayerState.currentIndex = this.currentIndex;
            window.__utlutPlayerState.currentTrack = this.currentTrack;
        },

        togglePlay() {
            if (!this.currentTrack) {
                return;
            }

            if (this.isPlaying) {
                audio.pause();
                return;
            }

            audio.play().catch((e) => console.warn('Audio play failed:', e.message));
        },

        seek(e) {
            if (!isValidDuration(this.duration)) {
                return;
            }

            const rect = e.currentTarget.getBoundingClientRect();
            const percent = clamp((e.clientX - rect.left) / rect.width, 0, 1);

            audio.currentTime = percent * this.duration;
            this.currentTime = audio.currentTime || 0;
            this.syncProgress();
        },

        // Check if an article is ready to play
        isArticleReady(article) {
            if (!article) {
                return false;
            }
            // Article is ready if it has audio_url or status is 'ready'
            return Boolean(article.audio_url) || article.status === 'ready';
        },

        async playTrack(index) {
            if (index < 0 || index >= this.queue.length) {
                return;
            }

            const track = this.queue[index];

            // Check if article is ready to play
            if (!this.isArticleReady(track)) {
                console.warn('Article not ready to play:', track.id);
                // Try to find the next playable track
                const nextPlayableIndex = this.findNextPlayableIndex(index);
                if (nextPlayableIndex !== -1 && nextPlayableIndex !== index) {
                    await this.playTrack(nextPlayableIndex);
                }
                return;
            }

            this.isLoading = true;
            this.token = getDeviceToken();
            this.currentIndex = index;
            this.currentTrack = track;
            this.persistState();

            // Add to shuffle history if shuffle is enabled
            if (this.shuffleEnabled && !this.shuffleHistory.includes(this.currentTrack.id)) {
                this.shuffleHistory.push(this.currentTrack.id);
                window.__utlutPlayerState.shuffleHistory = this.shuffleHistory;
            }

            this.currentTime = 0;
            this.duration = 0;
            this.progress = 0;
            this.buffered = 0;

            audio.pause();

            // Set source and play immediately to preserve user gesture context
            // Cache lookup would break the gesture chain and cause autoplay to be blocked
            audio.src = `/api/articles/${this.currentTrack.id}/audio?token=${this.token}`;
            audio.playbackRate = this.playbackRate;
            audio.load();

            try {
                await audio.play();
                this.isLoading = false;
            } catch (e) {
                console.warn('Audio play failed:', e.message);
                this.isLoading = false;
                // If audio failed to load (e.g., 409 not ready), skip to next
                if (e.name === 'NotSupportedError' || e.name === 'AbortError') {
                    this.next();
                    return;
                }
            }

            this.updateMetadata();

            if (window.MetadataDB) {
                window.MetadataDB.set(JSON.parse(JSON.stringify(this.currentTrack)));
            }

            // Pre-cache next ready track in background
            const nextReadyIndex = this.findNextPlayableIndex(index);
            if (nextReadyIndex !== -1 && window.AudioCache) {
                const nextTrack = this.queue[nextReadyIndex];
                window.AudioCache.prefetch(nextTrack.id, this.token).catch(() => {});
            }
        },

        // Find the next playable track index from a given starting point
        findNextPlayableIndex(fromIndex) {
            for (let i = fromIndex + 1; i < this.queue.length; i++) {
                if (this.isArticleReady(this.queue[i])) {
                    return i;
                }
            }
            // If repeat all is enabled, check from the beginning
            if (this.repeatMode === 'all') {
                for (let i = 0; i < fromIndex; i++) {
                    if (this.isArticleReady(this.queue[i])) {
                        return i;
                    }
                }
            }
            return -1;
        },

        // Find the previous playable track index
        findPrevPlayableIndex(fromIndex) {
            for (let i = fromIndex - 1; i >= 0; i--) {
                if (this.isArticleReady(this.queue[i])) {
                    return i;
                }
            }
            return -1;
        },

        playNow(track) {
            if (!track || !track.id) {
                return;
            }

            this.init();

            const id = Number(track.id);
            const existingIndex = this.queue.findIndex((a) => a.id === id);
            if (existingIndex !== -1) {
                this.playTrack(existingIndex);
                return;
            }

            this.queue.unshift({
                id,
                title: track.title ?? null,
                url: track.url ?? null,
            });
            this.persistState();

            this.playTrack(0);
        },

        playNowQueue(tracks, replace = true) {
            if (!Array.isArray(tracks) || tracks.length === 0) {
                return;
            }

            this.init();

            const normalized = tracks
                .map((t) => ({
                    id: Number(t.id),
                    title: t.title ?? null,
                    url: t.url ?? null,
                }))
                .filter((t) => t.id);

            if (!normalized.length) {
                return;
            }

            this.queue = replace ? normalized : [...normalized, ...this.queue];
            this.persistState();
            this.playTrack(0);
        },

        next() {
            // Repeat one: replay current track (only if it's ready)
            if (this.repeatMode === 'one' && this.isArticleReady(this.currentTrack)) {
                audio.currentTime = 0;
                audio.play().catch((e) => console.warn('Audio play failed:', e.message));
                return;
            }

            // Shuffle mode: pick random unplayed ready track
            if (this.shuffleEnabled) {
                const unplayedReadyIndices = [];
                for (let i = 0; i < this.queue.length; i++) {
                    if (!this.shuffleHistory.includes(this.queue[i].id) && this.isArticleReady(this.queue[i])) {
                        unplayedReadyIndices.push(i);
                    }
                }

                if (unplayedReadyIndices.length > 0) {
                    const randomIdx = unplayedReadyIndices[Math.floor(Math.random() * unplayedReadyIndices.length)];
                    this.shuffleHistory.push(this.queue[randomIdx].id);
                    window.__utlutPlayerState.shuffleHistory = this.shuffleHistory;
                    this.playTrack(randomIdx);
                    return;
                }

                // All ready tracks played - check repeat mode
                if (this.repeatMode === 'all') {
                    this.shuffleHistory = [];
                    window.__utlutPlayerState.shuffleHistory = this.shuffleHistory;
                    // Find all ready tracks
                    const readyIndices = [];
                    for (let i = 0; i < this.queue.length; i++) {
                        if (this.isArticleReady(this.queue[i])) {
                            readyIndices.push(i);
                        }
                    }
                    if (readyIndices.length > 0) {
                        const randomIdx = readyIndices[Math.floor(Math.random() * readyIndices.length)];
                        this.shuffleHistory.push(this.queue[randomIdx].id);
                        this.playTrack(randomIdx);
                    }
                }
                return;
            }

            // Normal mode: find next ready track
            const nextIndex = this.findNextPlayableIndex(this.currentIndex);
            if (nextIndex !== -1) {
                this.playTrack(nextIndex);
            } else if (this.repeatMode === 'all') {
                // Loop back to start - find first ready track
                for (let i = 0; i < this.queue.length; i++) {
                    if (this.isArticleReady(this.queue[i])) {
                        this.playTrack(i);
                        return;
                    }
                }
            }
        },

        prev() {
            const prevIndex = this.findPrevPlayableIndex(this.currentIndex);
            if (prevIndex !== -1) {
                this.playTrack(prevIndex);
            }
        },

        removeFromQueue(index) {
            this.queue.splice(index, 1);

            if (index === this.currentIndex) {
                audio.pause();
                this.currentTrack = null;
                this.currentIndex = -1;
                this.currentTime = 0;
                this.duration = 0;
                this.progress = 0;
                this.buffered = 0;
                this.persistState();
                return;
            }

            if (index < this.currentIndex) {
                this.currentIndex--;
            }
            this.persistState();
        },

        clearQueue() {
            audio.pause();
            this.queue = [];
            this.currentTrack = null;
            this.currentIndex = -1;
            this.currentTime = 0;
            this.duration = 0;
            this.progress = 0;
            this.buffered = 0;
            this.shuffleHistory = [];
            this.persistState();
        },

        // Playback rate control
        setPlaybackRate(rate) {
            const validRates = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
            if (!validRates.includes(rate)) {
                return;
            }

            this.playbackRate = rate;
            audio.playbackRate = rate;
            localStorage.setItem('utlut-playback-rate', rate.toString());
            window.__utlutPlayerState.playbackRate = rate;
            this.updatePositionState();
        },

        // Skip forward/backward
        skipForward(seconds = 15) {
            if (!isValidDuration(this.duration)) {
                return;
            }

            audio.currentTime = Math.min(audio.currentTime + seconds, this.duration);
            this.currentTime = audio.currentTime || 0;
            this.syncProgress();
        },

        skipBackward(seconds = 15) {
            audio.currentTime = Math.max(audio.currentTime - seconds, 0);
            this.currentTime = audio.currentTime || 0;
            this.syncProgress();
        },

        // Shuffle mode
        toggleShuffle() {
            this.shuffleEnabled = !this.shuffleEnabled;
            localStorage.setItem('utlut-shuffle', this.shuffleEnabled.toString());
            window.__utlutPlayerState.shuffleEnabled = this.shuffleEnabled;

            if (this.shuffleEnabled && this.currentTrack) {
                // Reset shuffle history, keeping current track as first played
                this.shuffleHistory = [this.currentTrack.id];
            } else {
                this.shuffleHistory = [];
            }
            window.__utlutPlayerState.shuffleHistory = this.shuffleHistory;
        },

        // Repeat mode: off -> all -> one -> off
        cycleRepeatMode() {
            const modes = ['off', 'all', 'one'];
            const currentIdx = modes.indexOf(this.repeatMode);
            this.repeatMode = modes[(currentIdx + 1) % modes.length];
            localStorage.setItem('utlut-repeat', this.repeatMode);
            window.__utlutPlayerState.repeatMode = this.repeatMode;
        },

        // Queue reordering
        moveInQueue(fromIndex, toIndex) {
            if (fromIndex < 0 || fromIndex >= this.queue.length) {
                return;
            }
            if (toIndex < 0 || toIndex >= this.queue.length) {
                return;
            }
            if (fromIndex === toIndex) {
                return;
            }

            const item = this.queue.splice(fromIndex, 1)[0];
            this.queue.splice(toIndex, 0, item);

            // Update currentIndex if needed
            if (fromIndex === this.currentIndex) {
                this.currentIndex = toIndex;
            } else if (fromIndex < this.currentIndex && toIndex >= this.currentIndex) {
                this.currentIndex--;
            } else if (fromIndex > this.currentIndex && toIndex <= this.currentIndex) {
                this.currentIndex++;
            }

            this.persistState();
        },

        // Add to end of queue without playing
        async addToQueue(articleId) {
            const id = Number(articleId);
            if (!id) {
                return;
            }

            // Check if already in queue
            const existingIndex = this.queue.findIndex((a) => a.id === id);
            if (existingIndex !== -1) {
                return; // Already in queue
            }

            let article = null;
            if (window.MetadataDB) {
                article = await window.MetadataDB.get(id);
            }

            if (!article && this.isOnline) {
                try {
                    const token = getDeviceToken();
                    const response = await fetch(`/api/articles/batch?ids=${id}&preserve_order=1`, {
                        headers: { Authorization: `Bearer ${token}` },
                    });
                    const data = await response.json();
                    article = data?.data?.[0] ?? null;
                } catch (e) {
                    console.warn('Article fetch failed:', e.message);
                }
            }

            if (!article) {
                return;
            }

            this.queue.push(article);
            this.persistState();
        },

        // Insert after current track
        async playNext(articleId) {
            const id = Number(articleId);
            if (!id) {
                return;
            }

            // Check if already in queue
            const existingIndex = this.queue.findIndex((a) => a.id === id);
            if (existingIndex !== -1) {
                // Move to after current
                if (existingIndex !== this.currentIndex + 1) {
                    this.moveInQueue(existingIndex, this.currentIndex + 1);
                }
                return;
            }

            let article = null;
            if (window.MetadataDB) {
                article = await window.MetadataDB.get(id);
            }

            if (!article && this.isOnline) {
                try {
                    const token = getDeviceToken();
                    const response = await fetch(`/api/articles/batch?ids=${id}&preserve_order=1`, {
                        headers: { Authorization: `Bearer ${token}` },
                    });
                    const data = await response.json();
                    article = data?.data?.[0] ?? null;
                } catch (e) {
                    console.warn('Article fetch failed:', e.message);
                }
            }

            if (!article) {
                return;
            }

            // Insert after current track
            const insertIndex = this.currentIndex >= 0 ? this.currentIndex + 1 : 0;
            this.queue.splice(insertIndex, 0, article);
            this.persistState();
        },

        // Check if an article is in the queue
        isInQueue(articleId) {
            const id = Number(articleId);
            return this.queue.some((a) => a.id === id);
        },

        async playArticle(articleId) {
            const id = Number(articleId);
            if (!id) {
                return;
            }

            const existingIndex = this.queue.findIndex((a) => a.id === id);
            if (existingIndex !== -1) {
                await this.playTrack(existingIndex);
                return;
            }

            let article = null;
            if (window.MetadataDB) {
                article = await window.MetadataDB.get(id);
            }

            if (!article && this.isOnline) {
                try {
                    const token = getDeviceToken();
                    const response = await fetch(`/api/articles/batch?ids=${id}&preserve_order=1`, {
                        headers: { Authorization: `Bearer ${token}` },
                    });
                    const data = await response.json();
                    article = data?.data?.[0] ?? null;
                } catch (e) {
                    console.warn('Article fetch failed:', e.message);
                }
            }

            if (!article) {
                return;
            }

            this.queue.unshift(article);
            this.persistState();
            await this.playTrack(0);
        },

        async playQueue(articleIds) {
            const ids = articleIds.map((id) => Number(id)).filter(Boolean);
            if (ids.length === 0) {
                return;
            }

            let articles = [];

            if (!this.isOnline && window.MetadataDB) {
                for (const id of ids) {
                    const a = await window.MetadataDB.get(id);
                    if (a) {
                        articles.push(a);
                    }
                }
            } else {
                try {
                    const token = getDeviceToken();
                    const response = await fetch(`/api/articles/batch?ids=${ids.join(',')}&preserve_order=1`, {
                        headers: { Authorization: `Bearer ${token}` },
                    });
                    const data = await response.json();
                    articles = data?.data ?? [];
                } catch (e) {
                    console.warn('Batch fetch failed:', e.message);
                }
            }

            if (!articles.length) {
                return;
            }

            this.queue = articles;
            this.persistState();
            await this.playTrack(0);
        },

        async playPlaylist(playlistId) {
            if (!this.isOnline) {
                return;
            }

            try {
                const token = getDeviceToken();
                const response = await fetch(`/api/playlists/${playlistId}`, {
                    headers: { Authorization: `Bearer ${token}` },
                });
                const data = await response.json();
                const items = data?.playlist?.items ?? [];
                const articles = items.map((i) => i.article).filter(Boolean);

                if (!articles.length) {
                    return;
                }

                this.queue = articles;
                this.persistState();
                await this.playTrack(0);
            } catch (e) {
                console.warn('Playlist fetch failed:', e.message);
            }
        },

        updateMetadata() {
            if (!('mediaSession' in navigator) || !this.currentTrack) {
                return;
            }

            navigator.mediaSession.metadata = new MediaMetadata({
                title: this.currentTrack.title || this.currentTrack.url,
                artist: 'Utlut',
                album: 'Articles',
                artwork: [
                    { src: '/icons/icon-96.png', sizes: '96x96', type: 'image/png' },
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-256.png', sizes: '256x256', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                ],
            });
        },

        updatePositionState() {
            if (!('mediaSession' in navigator) || !('setPositionState' in navigator.mediaSession)) {
                return;
            }

            if (!isValidDuration(this.duration)) {
                return;
            }

            navigator.mediaSession.setPositionState({
                duration: this.duration,
                playbackRate: this.playbackRate,
                position: this.currentTime || 0,
            });
        },
    };
}

function installPlayerStore() {
    const AlpineRef = window.Alpine;
    if (!AlpineRef || typeof AlpineRef.store !== 'function') {
        return;
    }

    // Check if store already exists (survives wire:navigate)
    const existingStore = AlpineRef.store('player');
    if (existingStore && existingStore.audio === window.__utlutAudio) {
        // Store exists with same audio element - just refresh token and sync state
        existingStore.token = getDeviceToken();
        existingStore.isPlaying = !window.__utlutAudio.paused && !window.__utlutAudio.ended;
        existingStore.currentTime = window.__utlutAudio.currentTime || 0;
        existingStore.setDurationFromAudio();
        existingStore.syncProgress();
        existingStore.syncBuffered();
        return;
    }

    // First time installation
    if (!window.__utlutPlayerStoreInstalled) {
        window.__utlutPlayerStoreInstalled = true;
        AlpineRef.store('player', createPlayerStore());
        AlpineRef.store('player').init();
    }
}

document.addEventListener('alpine:init', () => {
    installPlayerStore();
});

// Handle Livewire navigation - re-sync store after page swap
document.addEventListener('livewire:navigated', () => {
    installPlayerStore();
});

// If Alpine is already on the page (e.g. after SPA-like navigation), install immediately.
installPlayerStore();


