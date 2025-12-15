/// <reference types="@sveltejs/kit" />
/// <reference no-default-lib="true"/>
/// <reference lib="esnext" />
/// <reference lib="webworker" />

import { build, files, version } from '$service-worker';

const sw = self as unknown as ServiceWorkerGlobalScope;

const CACHE_NAME = `cache-${version}`;

const ASSETS = [
	...build,
	...files
];

sw.addEventListener('install', (event) => {
	event.waitUntil(
		caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)).then(() => {
			sw.skipWaiting();
		})
	);
});

sw.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys().then((keys) => {
			return Promise.all(
				keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
			);
		}).then(() => {
			sw.clients.claim();
		})
	);
});

sw.addEventListener('fetch', (event) => {
	const url = new URL(event.request.url);

	// Don't intercept non-GET requests at all - let them pass through
	if (event.request.method !== 'GET') return;

	// Skip API requests entirely - don't cache them
	if (url.pathname.startsWith('/api/')) return;

	// For static assets, try cache first
	if (ASSETS.includes(url.pathname)) {
		event.respondWith(
			caches.match(event.request).then((cached) => {
				return cached || fetch(event.request);
			})
		);
		return;
	}

	// For navigation requests, try network first, fallback to cache
	if (event.request.mode === 'navigate') {
		event.respondWith(
			fetch(event.request).catch(() => {
				return caches.match(event.request).then((cached) => {
					return cached || caches.match('/');
				});
			}) as Promise<Response>
		);
		return;
	}

	// Default: network first, cache fallback
	event.respondWith(
		fetch(event.request).then((response) => {
			// Cache successful responses (but not 206 partial responses - they can't be cached)
			if (response.ok && response.status !== 206) {
				const clone = response.clone();
				caches.open(CACHE_NAME).then((cache) => {
					cache.put(event.request, clone);
				});
			}
			return response;
		}).catch(() => {
			return caches.match(event.request).then((cached) => {
				return cached || new Response('Offline', { status: 503 });
			});
		}) as Promise<Response>
	);
});
