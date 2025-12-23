const CACHE_NAME = 'utlut-v1';
const AUDIO_CACHE_NAME = 'utlut-audio-cache-v1';
const STATIC_ASSETS = [
    '/',
    '/manifest.webmanifest',
    '/favicon.ico',
    '/favicon.svg',
    '/apple-touch-icon.png',
    '/icons/icon-96.png',
    '/icons/icon-192.png',
    '/icons/icon-256.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME && cacheName !== AUDIO_CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // Handle audio requests
    if (url.pathname.includes('/api/articles/') && url.pathname.endsWith('/audio')) {
        event.respondWith(handleAudioRequest(event.request));
        return;
    }

    // Strategy: Stale-while-revalidate for assets, Network-first for others
    if (url.pathname.startsWith('/build/') || STATIC_ASSETS.includes(url.pathname)) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) => {
                return cache.match(event.request).then((cachedResponse) => {
                    const fetchedResponse = fetch(event.request).then((networkResponse) => {
                        cache.put(event.request, networkResponse.clone());
                        return networkResponse;
                    });

                    return cachedResponse || fetchedResponse;
                });
            })
        );
    } else {
        // Network-first for everything else
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match(event.request);
            })
        );
    }
});

/**
 * Parse Range header and return start and end positions
 */
function parseRangeHeader(rangeHeader, contentLength) {
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
}

/**
 * Create a 206 Partial Content response from a full cached response
 */
async function createRangeResponse(fullResponse, rangeHeader) {
    const fullBody = await fullResponse.arrayBuffer();
    const contentLength = fullBody.byteLength;
    const range = parseRangeHeader(rangeHeader, contentLength);

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
}

async function handleAudioRequest(request) {
    const url = new URL(request.url);
    const cache = await caches.open(AUDIO_CACHE_NAME);
    const rangeHeader = request.headers.get('Range');
    
    // Try to match cached full response (stored without Range header)
    const cachedResponse = await cache.match(url.toString());

    if (cachedResponse) {
        // If request has Range header, create 206 response from cached full response
        if (rangeHeader && cachedResponse.status === 200) {
            return await createRangeResponse(cachedResponse, rangeHeader);
        }
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.status === 200) {
            // Cache the full response using URL as key
            cache.put(url.toString(), networkResponse.clone());
        } else if (networkResponse.status === 206) {
            // If we got a partial response, fetch full content for caching
            // Create a new request without Range header
            const fullRequest = new Request(url.toString(), {
                method: 'GET',
                headers: Object.fromEntries(
                    Array.from(request.headers.entries())
                        .filter(([key]) => key.toLowerCase() !== 'range')
                ),
            });
            
            const fullResponse = await fetch(fullRequest);
            
            if (fullResponse.status === 200) {
                // Cache the full response using URL as key
                cache.put(url.toString(), fullResponse.clone());
            }
        }
        
        return networkResponse;
    } catch (error) {
        // If network fails and we have a cached response, try to serve it
        // (even if it doesn't match the range request exactly)
        if (cachedResponse) {
            if (rangeHeader && cachedResponse.status === 200) {
                return await createRangeResponse(cachedResponse, rangeHeader);
            }
            return cachedResponse;
        }
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}
