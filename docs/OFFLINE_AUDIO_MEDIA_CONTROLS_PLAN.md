# Offline Audio Playback with OS Media Controls - Implementation Plan

> **Goal**: Enable fully offline audio playback with native-like OS media controls (play/pause/next/prev) on mobile devices, similar to Spotify/Apple Music.

## Table of Contents
1. [Current State Analysis](#current-state-analysis)
2. [Architecture Overview](#architecture-overview)
3. [Implementation Tasks](#implementation-tasks)
4. [Technical Details](#technical-details)
5. [Platform Considerations](#platform-considerations)
6. [Sources & References](#sources--references)

---

## Current State Analysis

### What Already Exists
| Feature | Status | Location |
|---------|--------|----------|
| Service Worker | ✅ Basic | `public/sw.js` |
| PWA Manifest | ✅ Basic | `public/manifest.webmanifest` |
| Audio Cache API | ✅ Partial | `resources/js/audio-cache.js` |
| IndexedDB Metadata | ✅ Partial | `resources/js/audio-cache.js` |
| Media Session API | ✅ Partial | `player.blade.php` |
| HTML5 Audio Player | ✅ Complete | `player.blade.php` |

### Current Gaps
1. **Service Worker**: Does not handle audio range requests or audio-specific caching
2. **Audio Caching**: Only caches full responses (status 200), not handling 206 partial content properly
3. **Offline Queue**: No persistence of playback queue/state across sessions
4. **Download Management**: No explicit "download for offline" UI
5. **Media Session**: Missing `seekbackward`/`seekforward` handlers
6. **PWA Manifest**: Missing required icons and categories for media apps

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         USER INTERFACE                               │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────────┐  │
│  │ Download UI │  │ Player UI   │  │ OS Media Controls           │  │
│  │ (per track) │  │ (existing)  │  │ (lockscreen/notification)   │  │
│  └─────────────┘  └─────────────┘  └─────────────────────────────┘  │
└───────────────────────────────────────────────────────────────────┬─┘
                                                                    │
┌───────────────────────────────────────────────────────────────────▼─┐
│                      PLAYBACK LAYER                                  │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ PlaybackManager (Alpine.js)                                  │    │
│  │ • Queue management & persistence                             │    │
│  │ • State restoration on page load                             │    │
│  │ • Media Session API integration                              │    │
│  └─────────────────────────────────────────────────────────────┘    │
└───────────────────────────────────────────────────────────────────┬─┘
                                                                    │
┌───────────────────────────────────────────────────────────────────▼─┐
│                      CACHING LAYER                                   │
│  ┌─────────────────────┐  ┌────────────────────────────────────┐    │
│  │ Service Worker      │  │ AudioCacheManager                   │    │
│  │ • Range requests    │  │ • Full audio pre-caching            │    │
│  │ • Offline fallback  │  │ • Download progress tracking        │    │
│  │ • Cache routing     │  │ • Storage quota management          │    │
│  └─────────────────────┘  └────────────────────────────────────┘    │
└───────────────────────────────────────────────────────────────────┬─┘
                                                                    │
┌───────────────────────────────────────────────────────────────────▼─┐
│                      STORAGE LAYER                                   │
│  ┌────────────────────────┐  ┌─────────────────────────────────┐    │
│  │ Cache API              │  │ IndexedDB                        │    │
│  │ • Audio files (blobs)  │  │ • Article metadata               │    │
│  │ • Range request support│  │ • Playback state                 │    │
│  └────────────────────────┘  │ • Queue persistence              │    │
│                              └─────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Implementation Tasks

### Phase 1: Enhanced Service Worker for Audio

#### 1.1 Install Workbox Dependencies
```bash
npm install workbox-core workbox-routing workbox-strategies workbox-range-requests workbox-precaching
```

#### 1.2 Rewrite Service Worker with Range Request Support

**File**: `public/sw.js`

```javascript
import { registerRoute } from 'workbox-routing';
import { CacheFirst, StaleWhileRevalidate } from 'workbox-strategies';
import { RangeRequestsPlugin } from 'workbox-range-requests';
import { CacheableResponsePlugin } from 'workbox-cacheable-response';

const AUDIO_CACHE = 'utlut-audio-v1';
const STATIC_CACHE = 'utlut-static-v1';

// Audio files: CacheFirst with Range Request support
registerRoute(
  ({ url }) => url.pathname.startsWith('/api/articles/') && url.pathname.endsWith('/audio'),
  new CacheFirst({
    cacheName: AUDIO_CACHE,
    plugins: [
      new RangeRequestsPlugin(),
      new CacheableResponsePlugin({ statuses: [0, 200] }),
    ],
  })
);

// Static assets: StaleWhileRevalidate
registerRoute(
  ({ url }) => url.pathname.startsWith('/build/') ||
               ['/', '/manifest.webmanifest'].includes(url.pathname),
  new StaleWhileRevalidate({ cacheName: STATIC_CACHE })
);
```

#### 1.3 Configure Vite for Service Worker Build

**File**: `vite.config.js` additions

```javascript
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  plugins: [
    VitePWA({
      strategies: 'injectManifest',
      srcDir: 'resources/js',
      filename: 'sw.js',
      injectManifest: {
        injectionPoint: undefined
      }
    })
  ]
});
```

---

### Phase 2: Explicit Audio Download System

#### 2.1 Create Download Manager

**File**: `resources/js/download-manager.js`

```javascript
export class DownloadManager {
  constructor() {
    this.CACHE_NAME = 'utlut-audio-v1';
    this.downloads = new Map(); // articleId -> { progress, status }
  }

  async download(articleId, token, onProgress) {
    const url = `/api/articles/${articleId}/audio?token=${token}`;
    const cache = await caches.open(this.CACHE_NAME);

    // Check if already cached
    if (await cache.match(url)) {
      return { status: 'cached' };
    }

    const response = await fetch(url);
    const reader = response.body.getReader();
    const contentLength = +response.headers.get('Content-Length');

    let receivedLength = 0;
    const chunks = [];

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      chunks.push(value);
      receivedLength += value.length;
      onProgress?.(receivedLength / contentLength);
    }

    const blob = new Blob(chunks, { type: 'audio/mpeg' });
    const cachedResponse = new Response(blob, {
      headers: { 'Content-Type': 'audio/mpeg' }
    });

    await cache.put(url, cachedResponse);
    return { status: 'downloaded' };
  }

  async getDownloadStatus(articleId, token) {
    const cache = await caches.open(this.CACHE_NAME);
    const url = `/api/articles/${articleId}/audio?token=${token}`;
    return !!(await cache.match(url));
  }

  async removeDownload(articleId, token) {
    const cache = await caches.open(this.CACHE_NAME);
    const url = `/api/articles/${articleId}/audio?token=${token}`;
    return cache.delete(url);
  }

  async getStorageUsage() {
    if ('storage' in navigator && 'estimate' in navigator.storage) {
      const estimate = await navigator.storage.estimate();
      return {
        used: estimate.usage,
        quota: estimate.quota,
        percent: (estimate.usage / estimate.quota) * 100
      };
    }
    return null;
  }
}
```

#### 2.2 Add Download UI Component

**File**: `resources/views/components/download-button.blade.php`

```blade
<div x-data="downloadButton('{{ $articleId }}', '{{ $token }}')" class="inline-flex">
  <button
    @click="toggle"
    :disabled="isDownloading"
    class="p-2 rounded-lg transition-colors"
    :class="isDownloaded ? 'text-green-500' : 'text-zinc-500 hover:text-zinc-700'"
  >
    <template x-if="isDownloading">
      <div class="relative size-5">
        <svg class="animate-spin" viewBox="0 0 24 24"><!-- spinner --></svg>
        <span class="absolute inset-0 flex items-center justify-center text-xs"
              x-text="Math.round(progress * 100) + '%'"></span>
      </div>
    </template>
    <template x-if="!isDownloading">
      <flux:icon :name="isDownloaded ? 'check-circle' : 'arrow-down-tray'" />
    </template>
  </button>
</div>
```

---

### Phase 3: Persistent Playback State

#### 3.1 Create State Persistence Module

**File**: `resources/js/playback-state.js`

```javascript
const STATE_KEY = 'utlut-playback-state';

export const PlaybackState = {
  save(state) {
    const data = {
      queue: state.queue.map(t => t.id),
      currentIndex: state.currentIndex,
      currentTime: state.currentTime,
      timestamp: Date.now()
    };
    localStorage.setItem(STATE_KEY, JSON.stringify(data));
  },

  load() {
    try {
      const data = JSON.parse(localStorage.getItem(STATE_KEY));
      // Expire after 7 days
      if (data && Date.now() - data.timestamp < 7 * 24 * 60 * 60 * 1000) {
        return data;
      }
    } catch {}
    return null;
  },

  clear() {
    localStorage.removeItem(STATE_KEY);
  }
};
```

#### 3.2 Enhance Player with State Restoration

Update `player.blade.php` init function:

```javascript
async init() {
  // ... existing code ...

  // Restore state on load
  const savedState = PlaybackState.load();
  if (savedState) {
    await this.restoreState(savedState);
  }

  // Auto-save state periodically
  setInterval(() => {
    if (this.currentTrack) {
      PlaybackState.save({
        queue: this.queue,
        currentIndex: this.currentIndex,
        currentTime: this.currentTime
      });
    }
  }, 5000);
},

async restoreState(state) {
  // Fetch article metadata from IndexedDB
  const articles = await Promise.all(
    state.queue.map(id => MetadataDB.get(id))
  );
  this.queue = articles.filter(Boolean);
  this.currentIndex = state.currentIndex;

  if (this.queue[this.currentIndex]) {
    this.currentTrack = this.queue[this.currentIndex];
    await this.loadTrack(this.currentIndex);
    this.audio.currentTime = state.currentTime;
    this.updateMetadata();
    // Don't auto-play due to autoplay policy
  }
}
```

---

### Phase 4: Complete Media Session Integration

#### 4.1 Enhanced Media Session Handlers

Update `player.blade.php`:

```javascript
setupMediaSession() {
  if (!('mediaSession' in navigator)) return;

  const actions = {
    play: () => { this.audio.play(); this.isPlaying = true; },
    pause: () => { this.audio.pause(); this.isPlaying = false; },
    previoustrack: () => this.prev(),
    nexttrack: () => this.next(),
    seekto: (details) => {
      if (details.seekTime !== undefined) {
        this.audio.currentTime = details.seekTime;
      }
    },
    seekbackward: (details) => {
      const skipTime = details.seekOffset || 10;
      this.audio.currentTime = Math.max(this.audio.currentTime - skipTime, 0);
    },
    seekforward: (details) => {
      const skipTime = details.seekOffset || 10;
      this.audio.currentTime = Math.min(this.audio.currentTime + skipTime, this.duration);
    },
    stop: () => {
      this.audio.pause();
      this.audio.currentTime = 0;
      this.isPlaying = false;
    }
  };

  for (const [action, handler] of Object.entries(actions)) {
    try {
      navigator.mediaSession.setActionHandler(action, handler);
    } catch (e) {
      console.log(`Media Session action "${action}" not supported`);
    }
  }
}
```

#### 4.2 Dynamic Metadata Updates

```javascript
updateMetadata() {
  if (!('mediaSession' in navigator) || !this.currentTrack) return;

  navigator.mediaSession.metadata = new MediaMetadata({
    title: this.currentTrack.title || 'Untitled Article',
    artist: 'Utlut',
    album: this.currentTrack.url ? new URL(this.currentTrack.url).hostname : 'Articles',
    artwork: [
      { src: '/icons/icon-96.png', sizes: '96x96', type: 'image/png' },
      { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
      { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' }
    ]
  });

  navigator.mediaSession.playbackState = this.isPlaying ? 'playing' : 'paused';
}
```

---

### Phase 5: PWA Manifest Enhancements

#### 5.1 Updated Manifest

**File**: `public/manifest.webmanifest`

```json
{
  "name": "Utlut",
  "short_name": "Utlut",
  "description": "Listen to your articles anywhere.",
  "start_url": "/?source=pwa",
  "display": "standalone",
  "orientation": "portrait",
  "background_color": "#ffffff",
  "theme_color": "#000000",
  "categories": ["entertainment", "productivity"],
  "icons": [
    { "src": "/icons/icon-72.png", "sizes": "72x72", "type": "image/png" },
    { "src": "/icons/icon-96.png", "sizes": "96x96", "type": "image/png" },
    { "src": "/icons/icon-128.png", "sizes": "128x128", "type": "image/png" },
    { "src": "/icons/icon-144.png", "sizes": "144x144", "type": "image/png" },
    { "src": "/icons/icon-152.png", "sizes": "152x152", "type": "image/png" },
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-384.png", "sizes": "384x384", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" },
    { "src": "/icons/maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ],
  "shortcuts": [
    {
      "name": "Now Playing",
      "url": "/player",
      "icons": [{ "src": "/icons/shortcut-play.png", "sizes": "96x96" }]
    },
    {
      "name": "Library",
      "url": "/library",
      "icons": [{ "src": "/icons/shortcut-library.png", "sizes": "96x96" }]
    }
  ]
}
```

#### 5.2 Generate Required Icons

Create icons in these sizes: 72, 96, 128, 144, 152, 192, 384, 512 pixels.
Use a tool like `pwa-asset-generator` or create manually.

---

### Phase 6: IndexedDB Schema Enhancement

#### 6.1 Extended Database Schema

**File**: `resources/js/audio-cache.js` (updated)

```javascript
const DB_NAME = 'utlut-db';
const DB_VERSION = 2;

const dbPromise = new Promise((resolve, reject) => {
  const request = indexedDB.open(DB_NAME, DB_VERSION);

  request.onupgradeneeded = (e) => {
    const db = e.target.result;

    // Articles store
    if (!db.objectStoreNames.contains('articles')) {
      db.createObjectStore('articles', { keyPath: 'id' });
    }

    // Playback queue store
    if (!db.objectStoreNames.contains('queue')) {
      db.createObjectStore('queue', { keyPath: 'id', autoIncrement: true });
    }

    // Download status store
    if (!db.objectStoreNames.contains('downloads')) {
      const store = db.createObjectStore('downloads', { keyPath: 'articleId' });
      store.createIndex('status', 'status');
    }
  };

  request.onsuccess = (e) => resolve(e.target.result);
  request.onerror = (e) => reject(e.target.error);
});
```

---

## Technical Details

### Audio Element Configuration

For proper offline playback with range requests, configure the audio element:

```html
<audio
  crossorigin="anonymous"
  preload="metadata"
  x-ref="audioElement"
></audio>
```

**Key attributes**:
- `crossorigin="anonymous"`: Required for service worker caching
- `preload="metadata"`: Load enough to get duration without full download

### Blob URL vs Direct URL Strategy

```javascript
async loadTrack(index) {
  const track = this.queue[index];
  const cache = await caches.open('utlut-audio-v1');
  const url = `/api/articles/${track.id}/audio?token=${this.token}`;

  const cachedResponse = await cache.match(url);

  if (cachedResponse) {
    // Use blob URL for cached content (more reliable offline)
    const blob = await cachedResponse.blob();
    this.audio.src = URL.createObjectURL(blob);
  } else if (navigator.onLine) {
    // Use direct URL when online (enables streaming)
    this.audio.src = url;
  } else {
    // Offline and not cached
    this.showError('This article is not available offline');
    return false;
  }

  return true;
}
```

### Storage Quota Management

```javascript
async checkStorageQuota() {
  if (!('storage' in navigator)) return true;

  const estimate = await navigator.storage.estimate();
  const usedPercent = (estimate.usage / estimate.quota) * 100;

  if (usedPercent > 80) {
    // Warn user about storage
    this.showStorageWarning(usedPercent);
  }

  if (usedPercent > 95) {
    // Prevent new downloads
    return false;
  }

  return true;
}
```

---

## Platform Considerations

### iOS Limitations & Workarounds

| Issue | Workaround |
|-------|------------|
| 30-second background audio timeout when paused | Keep audio element playing silence or accept limitation |
| No persistent service worker | User must add to homescreen for best experience |
| IndexedDB storage limits (~50MB before prompts) | Monitor usage, use efficient codecs (Opus) |
| Media Session requires user gesture | Show prominent play button on app load |

**iOS-Specific Code**:

```javascript
// Detect iOS
const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);

if (isIOS) {
  // iOS requires explicit user interaction to start audio
  document.addEventListener('touchstart', () => {
    // Unlock audio context
    const silentAudio = new Audio();
    silentAudio.play().catch(() => {});
  }, { once: true });
}
```

### Android Optimizations

Android has better PWA support. Take advantage of:
- Background sync for downloads
- Persistent service workers
- Reliable Media Session API
- Share Target API for receiving articles

```javascript
// Request persistent storage on Android
if (navigator.storage && navigator.storage.persist) {
  navigator.storage.persist().then(granted => {
    console.log(granted ? 'Storage persisted' : 'Storage may be cleared');
  });
}
```

---

## Implementation Checklist

### Phase 1: Service Worker
- [ ] Install Workbox dependencies
- [ ] Rewrite service worker with range request support
- [ ] Configure Vite for SW build
- [ ] Test range request handling

### Phase 2: Download System
- [ ] Create DownloadManager class
- [ ] Add download button component
- [ ] Implement download progress UI
- [ ] Add storage usage indicator

### Phase 3: State Persistence
- [ ] Create PlaybackState module
- [ ] Add state save/restore to player
- [ ] Test state restoration across sessions

### Phase 4: Media Session
- [ ] Add all action handlers
- [ ] Implement seekbackward/seekforward
- [ ] Add playback state updates
- [ ] Generate proper artwork icons

### Phase 5: PWA Manifest
- [ ] Generate all icon sizes
- [ ] Update manifest with shortcuts
- [ ] Add categories and orientation
- [ ] Test PWA installation

### Phase 6: IndexedDB
- [ ] Upgrade database schema
- [ ] Add queue persistence store
- [ ] Add download status store
- [ ] Migrate existing data

---

## Sources & References

### Official Documentation
- [MDN: Navigator.mediaSession](https://developer.mozilla.org/en-US/docs/Web/API/Navigator/mediaSession)
- [Chrome Developers: Serving cached audio and video](https://developer.chrome.com/docs/workbox/serving-cached-audio-and-video)
- [Chrome Developers: Workbox Range Requests](https://developer.chrome.com/docs/workbox/reference/workbox-range-requests/)

### Community Resources
- [What PWA Can Do Today: Audio](https://whatpwacando.today/audio/)
- [Prototyp Digital: PWAs and Audio Playback](https://prototyp.digital/blog/what-we-learned-about-pwas-and-audio-playback)
- [iOS Web Apps and Media Session API](https://dbushell.com/2023/03/20/ios-pwa-media-session-api/)
- [PWA Offline Streaming (web.dev)](https://web.dev/articles/pwa-with-offline-streaming)

### GitHub References
- [Workbox Audio Caching Example](https://github.com/daffinm/audio-cache-test)
- [Media Session API Explainer](https://github.com/w3c/mediasession/blob/main/explainer.md)
- [Audio Player PWA Demo](https://progressier.com/pwa-capabilities/audio-player-pwa)

### iOS-Specific
- [Apple Forums: iOS Audio Lockscreen in PWA](https://developer.apple.com/forums/thread/762582)
- [PWA on iOS: Limitations (2025)](https://brainhub.eu/library/pwa-on-ios)

---

## Notes

### Alternative: Native Wrapper

If iOS background audio limitations prove unacceptable, consider wrapping the PWA in a native container:

- **Capacitor**: Maintains web codebase, adds native plugins
- **React Native**: Reuse business logic, native UI
- **Native iOS**: Best background audio support, highest effort

The PWA approach outlined in this plan provides the best balance of:
- Single codebase
- Cross-platform compatibility
- Native-like media controls
- Offline capability

For 90%+ of use cases, this PWA implementation will match native app functionality.
