# Article-to-Audio PWA - Implementation Plan

A mobile-optimized Progressive Web App for converting articles to natural-sounding spoken audio with playlist management and background playback.

## Tech Stack

| Category | Technology |
|----------|------------|
| Framework | SvelteKit + TypeScript |
| Runtime/Package Manager | Bun |
| Database | Dexie (IndexedDB wrapper) |
| Styling | Tailwind CSS |
| TTS API | ElevenLabs (EN/NL voices) |
| Article Parsing | Mozilla Readability |
| Icons | Lucide Svelte |

---

## Phase 1: Project Setup

### 1.1 Initialize SvelteKit Project
- [x] Run `bun create svelte@latest` with TypeScript
- [x] Install dependencies: `bun install`
- [x] Configure `svelte.config.js` for PWA support
- [x] Setup `vite.config.ts` with proper build options

### 1.2 Install Core Dependencies
```bash
bun add dexie @mozilla/readability tailwindcss postcss autoprefixer
bun add lucide-svelte
bun add -d @sveltejs/adapter-auto @types/bun
```

### 1.3 Configure Tailwind CSS
- [x] Run `bunx tailwindcss init -p`
- [x] Configure `tailwind.config.js` for mobile-first design
- [x] Add Tailwind directives to `src/app.css`

### 1.4 PWA Manifest
- [x] Create `static/manifest.json`:
  - App name, short name, description
  - Icons (192x192, 512x512, maskable versions)
  - Display: standalone
  - Theme color, background color
  - Share target configuration for receiving URLs
  - Shortcuts for quick actions
- [x] Create PWA icons in `static/icons/`

### 1.5 App HTML Setup
- [x] Update `src/app.html`:
  - PWA meta tags
  - Apple touch icon
  - Theme color meta
  - Manifest link
  - Viewport for mobile

---

## Phase 2: Database Layer

### 2.1 Dexie Database Schema
- [x] Create `src/lib/db/database.ts`
- [x] Define tables:

```typescript
interface Article {
  id: string;
  title: string;
  url?: string;
  content: string;           // Cleaned article text
  language: 'en' | 'nl';
  createdAt: number;
  audioGenerated: boolean;
  audioDuration?: number;
}

interface AudioTrack {
  id: string;
  articleId: string;
  audioBlob: Blob;           // Stored audio data
  duration: number;
  createdAt: number;
}

interface Playlist {
  id: string;
  name: string;
  description?: string;
  articleIds: string[];      // Ordered list
  createdAt: number;
  updatedAt: number;
}

interface QueueState {
  id: 'current';             // Singleton
  articleIds: string[];
  currentIndex: number;
  currentTime: number;       // Playback position
  updatedAt: number;
}

interface Settings {
  id: 'user';
  voice: string;             // ElevenLabs voice ID
  playbackSpeed: number;
  announceArticles: boolean; // Speak title before article
  transitionChime: boolean;  // Play sound between articles
}
```

### 2.2 Database Operations
- [x] Create CRUD functions for each table
- [x] Add queue management functions (add, remove, reorder)
- [x] Add playlist management functions
- [x] Implement cache cleanup for old audio

---

## Phase 3: Article Import

### 3.1 URL Input Component
- [x] Create `src/lib/components/AddArticle.svelte`
- [x] URL input field with paste detection
- [x] Loading state during fetch/parse
- [x] Error handling for invalid URLs

### 3.2 Text Paste Input
- [x] Large textarea for direct text paste
- [x] Title input field (manual entry)
- [x] Language selector (EN/NL)
- [x] Useful for paywalled content

### 3.3 Bookmarklet Generator
- [x] Create page at `/bookmarklet`
- [x] Generate JavaScript bookmarklet code:
```javascript
javascript:(function(){
  const text = document.body.innerText;
  const title = document.title;
  const url = location.href;
  window.open('YOUR_APP_URL/add?title='+encodeURIComponent(title)+'&url='+encodeURIComponent(url)+'&text='+encodeURIComponent(text.substring(0,50000)));
})();
```
- [x] Instructions for adding to browser bookmarks
- [x] Works with paywalled sites (runs in authenticated session)

### 3.4 Share Target API
- [x] Configure in manifest.json
- [x] Create `/share` route to receive shared URLs
- [x] Handle POST with title, text, url params

---

## Phase 4: Content Cleaning

### 4.1 Readability Integration
- [x] Create `src/lib/utils/articleParser.ts`
- [x] Fetch URL content server-side (API route)
- [x] Apply Mozilla Readability to extract article
- [x] Return title, content, byline, excerpt

### 4.2 Text Cleaning Pipeline
- [x] Create `src/lib/utils/textCleaner.ts`
- [x] Remove common unwanted patterns:
  - "Subscribe to continue reading"
  - "Share this article"
  - "Related articles"
  - Cookie consent text
  - Social media handles
  - "Advertisement" markers
  - Image captions (optional toggle)
  - Author bios at end
- [x] Normalize whitespace
- [x] Remove excessive newlines
- [x] Strip HTML entities

### 4.3 Language Detection
- [x] Simple heuristic based on common words
- [x] Or use `franc` library for detection
- [x] Default to user preference if uncertain

---

## Phase 5: TTS Integration (ElevenLabs)

### 5.1 API Route Setup
- [x] Create `src/routes/api/tts/+server.ts`
- [x] Environment variable: `ELEVENLABS_API_KEY`
- [x] Handle streaming responses

### 5.2 Text Chunking
- [x] Create `src/lib/utils/textChunker.ts`
- [x] Split at sentence boundaries
- [x] Max 4500 characters per chunk
- [x] Preserve paragraph breaks where possible

### 5.3 Audio Generation
- [x] Create `src/lib/services/ttsService.ts`
- [x] Functions:
  - `generateArticleAudio(articleId)` - Full article
  - `generateIntroAudio(title)` - "Now playing: [title]"
  - `getAvailableVoices()` - List EN/NL voices
- [x] Concatenate chunks into single audio file
- [x] Store in IndexedDB via Dexie

### 5.4 Voice Configuration
- [x] Recommended voices:
  - English: `21m00Tcm4TlvDq8ikWAM` (Rachel)
  - Dutch: Select appropriate Dutch voice
- [x] Voice settings in user preferences
- [x] Model: `eleven_monolingual_v1`

---

## Phase 6: Article Announcements

### 6.1 Intro Generation
- [x] Before each article, generate TTS: "Now playing: [Article Title]"
- [x] Cache intro audio separately (reusable)
- [x] Configurable in settings (on/off)

### 6.2 Transition Audio
- [x] Create/source a short chime sound (< 1 second)
- [x] Store in `static/sounds/chime.mp3`
- [x] Play between articles
- [x] Configurable in settings (on/off)

### 6.3 Audio Sequencing
- [x] Order: [Chime] → [Intro] → [Article Content]
- [x] 1 second pause after chime
- [x] 0.5 second pause after intro

---

## Phase 7: Audio Player

### 7.1 Svelte Stores
- [x] Create `src/lib/stores/audioStore.ts`:
```typescript
export const currentArticle = writable<Article | null>(null);
export const isPlaying = writable(false);
export const currentTime = writable(0);
export const duration = writable(0);
export const volume = writable(1);
export const playbackRate = writable(1);
export const queue = writable<string[]>([]);
export const queueIndex = writable(0);
```

### 7.2 Audio Manager Class
- [x] Create `src/lib/audio/AudioManager.ts`
- [x] Single HTMLAudioElement for playback
- [x] Methods: play, pause, seek, next, previous
- [x] Event handlers for timeupdate, ended, error
- [x] Preload next track for gapless playback

### 7.3 Media Session API
- [x] Set metadata (title, artist, artwork)
- [x] Action handlers:
  - play, pause
  - seekbackward (-15s), seekforward (+15s)
  - previoustrack, nexttrack
- [x] Update position state for lock screen progress
- [x] Critical for iOS/Android background playback

### 7.4 Background Playback
- [x] Audio element must be user-initiated
- [x] Keep audio session alive
- [x] Handle interruptions (calls, other audio)

---

## Phase 8: Queue System

### 8.1 Queue State Management
- [x] Add to queue (end)
- [x] Play next (insert after current)
- [x] Remove from queue
- [x] Clear queue
- [x] Reorder (drag and drop)

### 8.2 Persistence
- [x] Save queue state to IndexedDB
- [x] Restore on app load
- [x] Save current playback position
- [x] Resume where user left off

### 8.3 Gapless Playback
- [x] Preload next article's audio
- [x] Use two Audio elements, swap on track end
- [x] Seamless transition

### 8.4 Queue UI Component
- [x] Create `src/lib/components/Queue.svelte`
- [x] Show current + upcoming articles
- [x] Drag handle for reordering
- [x] Swipe to remove
- [x] Tap to jump to article

---

## Phase 9: Playlist Management

### 9.1 Playlist CRUD
- [x] Create new playlist
- [x] Rename playlist
- [x] Delete playlist
- [x] Add article to playlist
- [x] Remove article from playlist
- [x] Reorder articles in playlist

### 9.2 Playlist UI
- [x] Create `src/lib/components/PlaylistList.svelte`
- [x] Create `src/lib/components/PlaylistDetail.svelte`
- [x] Grid/list view of playlists
- [x] Article count, total duration
- [x] Play all button
- [x] Shuffle play option

### 9.3 Smart Playlists (Optional)
- [ ] "Recently Added" (not implemented)
- [ ] "Not Yet Played" (not implemented)
- [ ] Auto-generated based on criteria (not implemented)

---

## Phase 10: Mobile UI

### 10.1 Layout Structure
- [x] Create `src/routes/+layout.svelte`
- [x] Bottom navigation bar (fixed)
- [x] Mini-player above nav (when audio active)
- [x] Safe area insets for notched devices

### 10.2 Navigation Tabs
- [x] Home/Library (articles list)
- [x] Queue (current queue)
- [x] Playlists
- [x] Settings

### 10.3 Mini-Player Component
- [x] Create `src/lib/components/MiniPlayer.svelte`
- [x] Shows current article title
- [x] Play/pause button
- [x] Progress bar (thin)
- [x] Tap to expand to full player

### 10.4 Full Player View
- [x] Create `src/routes/player/+page.svelte`
- [x] Large playback controls
- [x] Seek slider
- [x] Speed control (0.75x, 1x, 1.25x, 1.5x, 2x)
- [x] Queue button
- [x] Article info

### 10.5 Gestures
- [ ] Swipe down on full player to minimize (not implemented)
- [ ] Swipe on queue items to delete (not implemented)
- [x] Pull to refresh on article list

### 10.6 Responsive Design
- [x] Mobile-first (< 640px)
- [x] Tablet adjustments (640px - 1024px)
- [ ] Desktop layout (> 1024px) - optional

---

## Phase 11: Offline Support

### 11.1 Service Worker
- [x] Create `src/service-worker.ts`
- [x] Cache static assets (app shell)
- [x] Cache audio files for offline playback
- [ ] Background sync for queued conversions (not implemented)

### 11.2 Caching Strategy
- [x] Static assets: Cache first
- [x] API calls: Network first
- [x] Audio files: Cache first, network fallback

### 11.3 Offline Indicators
- [ ] Show offline badge when disconnected (not implemented)
- [ ] Indicate which articles are available offline (not implemented)
- [ ] Queue conversions when back online (not implemented)

---

## Phase 12: API Routes

### 12.1 Article Parsing
- [x] `POST /api/parse`
- [x] Input: `{ url: string }`
- [x] Output: `{ title, content, excerpt, language }`
- [x] Uses fetch + Readability server-side

### 12.2 Text-to-Speech
- [x] `POST /api/tts`
- [x] Input: `{ text: string, voice: string, language: string }`
- [x] Output: Audio stream (MP3)
- [x] Handles chunking internally

### 12.3 Voice List
- [x] `GET /api/voices`
- [x] Returns available ElevenLabs voices
- [x] Filter by language (EN/NL)

---

## File Structure

```
src/
├── routes/
│   ├── +layout.svelte          # App shell, nav, mini-player
│   ├── +page.svelte             # Home/Library
│   ├── add/+page.svelte         # Add article
│   ├── player/+page.svelte      # Full player
│   ├── queue/+page.svelte       # Queue view
│   ├── playlists/
│   │   ├── +page.svelte         # Playlist list
│   │   └── [id]/+page.svelte    # Playlist detail
│   ├── settings/+page.svelte    # Settings
│   ├── bookmarklet/+page.svelte # Bookmarklet instructions
│   ├── share/+page.svelte       # Share target handler
│   └── api/
│       ├── parse/+server.ts     # Article parsing
│       ├── tts/+server.ts       # Text-to-speech
│       └── voices/+server.ts    # Voice list
├── lib/
│   ├── components/
│   │   ├── AddArticle.svelte
│   │   ├── ArticleCard.svelte
│   │   ├── AudioPlayer.svelte
│   │   ├── MiniPlayer.svelte
│   │   ├── Queue.svelte
│   │   ├── PlaylistList.svelte
│   │   ├── PlaylistDetail.svelte
│   │   ├── ProgressBar.svelte
│   │   └── VolumeControl.svelte
│   ├── stores/
│   │   └── audioStore.ts
│   ├── audio/
│   │   └── AudioManager.ts
│   ├── db/
│   │   └── database.ts
│   ├── services/
│   │   └── ttsService.ts
│   └── utils/
│       ├── articleParser.ts
│       ├── textCleaner.ts
│       └── textChunker.ts
├── service-worker.ts
└── app.css
static/
├── manifest.json
├── icons/
│   ├── icon-192x192.png
│   ├── icon-512x512.png
│   └── apple-touch-icon.png
└── sounds/
    └── chime.mp3
```

---

## Environment Variables

```env
ELEVENLABS_API_KEY=your_api_key_here
PUBLIC_APP_URL=https://your-app-url.com
```

---

## Commands

```bash
# Development
bun run dev

# Build
bun run build

# Preview production build
bun run preview

# Type check
bun run check
```

---

## Implementation Order

1. **Phase 1**: Project setup, Tailwind, PWA basics
2. **Phase 2**: Database schema and operations
3. **Phase 3**: Article import (URL + text paste)
4. **Phase 4**: Content cleaning
5. **Phase 5**: TTS integration
6. **Phase 6**: Article announcements
7. **Phase 7**: Audio player core
8. **Phase 8**: Queue system
9. **Phase 9**: Playlists
10. **Phase 10**: Mobile UI polish
11. **Phase 11**: Offline support
12. **Phase 12**: API routes refinement

---

## Testing Checklist

- [ ] Add article via URL
- [ ] Add article via text paste
- [ ] Add article via bookmarklet
- [ ] Add article via share (mobile)
- [ ] Generate audio for article
- [ ] Play audio with controls
- [ ] Background playback (screen off)
- [ ] Lock screen controls work
- [ ] Queue multiple articles
- [ ] Gapless transition between articles
- [ ] Article title announced
- [ ] Create and manage playlists
- [ ] Offline playback of cached audio
- [ ] PWA install prompt
- [ ] Works on iOS Safari
- [ ] Works on Android Chrome
