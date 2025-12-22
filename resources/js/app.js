import './audio-cache.js';
import './player-store.js';

// Register service worker for offline app shell caching
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.debug('SW registered:', registration.scope);
            })
            .catch((error) => {
                console.warn('SW registration failed:', error);
            });
    });
}

