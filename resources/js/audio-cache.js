const dbPromise = new Promise((resolve, reject) => {
    if (typeof window === 'undefined') return;
    const request = indexedDB.open('sundo-db', 1);
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
    },
    async delete(id) {
        const db = await dbPromise;
        return new Promise((resolve, reject) => {
            const tx = db.transaction('articles', 'readwrite');
            const request = tx.objectStore('articles').delete(id);
            request.onsuccess = () => resolve(true);
            request.onerror = (e) => reject(e.target.error);
        });
    }
};

export const AudioCache = {
    CACHE_NAME: 'sundo-audio-cache-v1',

    /**
     * Parse Range header and return start and end positions
     */
    parseRangeHeader(rangeHeader, contentLength) {
        if (!rangeHeader || !rangeHeader.startsWith('bytes=')) {
            return null;
        }

        const ranges = rangeHeader.replace('bytes=', '').split(',');
        const range = ranges[0];
        const [start, end] = range.split('-').map((v) => (v === '' ? null : parseInt(v, 10)));

        return {
            start: start ?? null,
            end: end ?? contentLength - 1,
            length: end !== null ? end - (start ?? 0) + 1 : contentLength - (start ?? 0),
        };
    },

    /**
     * Create a 206 Partial Content response from a full cached response
     */
    async createRangeResponse(fullResponse, rangeHeader) {
        const fullBody = await fullResponse.arrayBuffer();
        const contentLength = fullBody.byteLength;
        const range = this.parseRangeHeader(rangeHeader, contentLength);

        if (!range) {
            return fullResponse;
        }

        const start = range.start ?? 0;
        const end = Math.min(range.end, contentLength - 1);
        const length = end - start + 1;

        const partialBody = fullBody.slice(start, end + 1);

        return new Response(partialBody, {
            status: 206,
            statusText: 'Partial Content',
            headers: {
                ...Object.fromEntries(fullResponse.headers.entries()),
                'Content-Range': `bytes ${start}-${end}/${contentLength}`,
                'Content-Length': length.toString(),
                'Accept-Ranges': 'bytes',
            },
        });
    },

    async getAudio(articleId, token, requestOptions = {}) {
        const cache = await caches.open(this.CACHE_NAME);
        const url = `/api/articles/${articleId}/audio?token=${token}`;
        
        // Create a Request object for the actual request
        const request = new Request(url, requestOptions);
        const rangeHeader = request.headers.get('Range');
        
        // Try to match the cached full response (stored without Range header)
        // We match by URL only to get the full cached response
        let response = await cache.match(url);
        
        if (response) {
            // If we have a cached response and the request has a Range header,
            // we need to create a 206 response from the cached full response
            if (rangeHeader && response.status === 200) {
                return await this.createRangeResponse(response, rangeHeader);
            }
            return response;
        }

        // Fetch the audio from network
        response = await fetch(request);
        
        if (response.status === 200) {
            // Cache the full response using URL as key
            cache.put(url, response.clone());
        } else if (response.status === 206) {
            // If we got a partial response, fetch the full content for caching
            // but return the partial one for immediate use
            // Create a new request without Range header to get full content
            const headersWithoutRange = new Headers(requestOptions.headers);
            headersWithoutRange.delete('Range');
            
            const fullResponse = await fetch(url, {
                ...requestOptions,
                headers: headersWithoutRange,
            });
            
            if (fullResponse.status === 200) {
                // Cache the full response using URL as key
                cache.put(url, fullResponse.clone());
            }
            // Return the original 206 response
            return response;
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
    },

    async prefetch(articleId, token) {
        const url = `/api/articles/${articleId}/audio?token=${token}`;
        const cache = await caches.open(this.CACHE_NAME);

        const existing = await cache.match(url);
        if (existing) {
            return true;
        }

        const response = await fetch(url);
        if (response.ok) {
            await cache.put(url, response);
            return true;
        }
        return false;
    }
};

if (typeof window !== 'undefined') {
    window.AudioCache = AudioCache;
    window.MetadataDB = MetadataDB;
}

