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

        init() {
            if (this.isReady) {
                // Restore state from audio element on re-init (page navigation)
                this.isPlaying = !audio.paused && !audio.ended;
                this.currentTime = audio.currentTime || 0;
                this.setDurationFromAudio();
                this.syncProgress();
                this.syncBuffered();
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

            window.addEventListener('online', () => {
                this.isOnline = true;
            });

            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            audio.addEventListener('play', () => {
                this.isPlaying = true;
            });

            audio.addEventListener('pause', () => {
                this.isPlaying = false;
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

        async playTrack(index) {
            if (index < 0 || index >= this.queue.length) {
                return;
            }

            this.token = getDeviceToken();
            this.currentIndex = index;
            this.currentTrack = this.queue[index];
            this.persistState();

            this.currentTime = 0;
            this.duration = 0;
            this.progress = 0;
            this.buffered = 0;

            audio.pause();

            // Set source and play immediately to preserve user gesture context
            // Cache lookup would break the gesture chain and cause autoplay to be blocked
            audio.src = `/api/articles/${this.currentTrack.id}/audio?token=${this.token}`;
            audio.load();

            try {
                await audio.play();
            } catch (e) {
                console.warn('Audio play failed:', e.message);
            }

            this.updateMetadata();

            if (window.MetadataDB) {
                window.MetadataDB.set(JSON.parse(JSON.stringify(this.currentTrack)));
            }
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
            if (this.currentIndex < this.queue.length - 1) {
                this.playTrack(this.currentIndex + 1);
            }
        },

        prev() {
            if (this.currentIndex > 0) {
                this.playTrack(this.currentIndex - 1);
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
            this.persistState();
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
                artwork: [{ src: '/apple-touch-icon.png', sizes: '180x180', type: 'image/png' }],
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
                playbackRate: 1,
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


