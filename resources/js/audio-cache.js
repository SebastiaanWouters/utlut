const dbPromise = new Promise((resolve, reject) => {
    if (typeof window === 'undefined') return;
    const request = indexedDB.open('utlut-db', 1);
    request.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains('articles')) {
            db.createObjectStore('articles', { keyPath: 'id' });
        }
    };
    request.onsuccess = (e) => resolve(e.target.result);
    request.onerror = (e) => reject(e.target.error);
});

export const MetadataDB = {
    async set(article) {
        const db = await dbPromise;
        const tx = db.transaction('articles', 'readwrite');
        tx.objectStore('articles').put(article);
    },
    async get(id) {
        const db = await dbPromise;
        return new Promise((resolve) => {
            const request = db.transaction('articles').objectStore('articles').get(id);
            request.onsuccess = (e) => resolve(e.target.result);
        });
    }
};

export const AudioCache = {
    CACHE_NAME: 'utlut-audio-cache-v1',

    async getAudio(articleId, token) {
        const cache = await caches.open(this.CACHE_NAME);
        const url = `/api/articles/${articleId}/audio?token=${token}`;
        
        let response = await cache.match(url);
        
        if (response) {
            return response;
        }

        response = await fetch(url);
        
        if (response.ok && response.status !== 206) {
            cache.put(url, response.clone());
        }
        
        return response;
    },

    async isCached(articleId, token) {
        const cache = await caches.open(this.CACHE_NAME);
        const url = `/api/articles/${articleId}/audio?token=${token}`;
        return !!(await cache.match(url));
    },

    async remove(articleId, token) {
        const cache = await caches.open(this.CACHE_NAME);
        const url = `/api/articles/${articleId}/audio?token=${token}`;
        return await cache.delete(url);
    }
};

if (typeof window !== 'undefined') {
    window.AudioCache = AudioCache;
    window.MetadataDB = MetadataDB;
}

