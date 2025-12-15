# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Article Reader is a SvelteKit 5 web app that converts articles to audio using text-to-speech. Users paste URLs or text, the server extracts and cleans the content, then generates audio via the Naga TTS API. Audio is stored temporarily server-side and cached client-side in IndexedDB.

## Commands

```bash
# Package management (use bun)
bun install
bun add <package>
bun remove <package>
# Development
bun run dev           # Start dev server at localhost:5173

# Production
bun run build         # Build for production
bun run start         # Run production build

# Type checking
bun run check         # Single run
bun run check:watch   # Watch mode
```

## Architecture

### Dual Database System

**Client (IndexedDB via Dexie)** - `src/lib/db/database.ts`
- `articles`: User's article library (id, title, content, language)
- `audioTracks`: Cached audio blobs per article
- `playlists`: User-created article collections
- `queueState`: Current playback position and queue (persists across refreshes)
- `settings`: Voice selection, playback speed, preferences

**Server (SQLite via better-sqlite3)** - `src/lib/server/db/jobQueue.ts`
- `audio_jobs`: TTS generation queue with progress tracking
- Jobs expire after 24 hours, audio files stored in `/data/audio/`
- WAL mode enabled for concurrent access

### Audio Generation Flow

1. Client creates article in IndexedDB, requests job via `POST /api/jobs`
2. Server queues job, processes via `audioWorker.ts` (chunks text, calls Naga API)
3. Client polls `GET /api/jobs/[id]` for progress updates
4. On completion, client downloads from `GET /api/jobs/[id]/audio`, saves to IndexedDB
5. `AudioManager` class handles playback, preloading, Media Session API

### Key Services

- `AudioManager` (`src/lib/audio/`): Singleton managing HTML5 Audio, queue, intro/chime playback
- `jobPollingService` (`src/lib/services/`): Polls server for async job status
- `ttsService`: Client-side fallback for synchronous TTS generation

### Text Processing Pipeline

`/api/parse` (URL extraction) → `textCleaner.ts` (removes noise) → `ttsPreprocessor.ts` (TTS optimization) → `textChunker.ts` (4500 char chunks for API limits)

## Environment Variables

```bash
NAGA_API_KEY=xxx              # Required - TTS API key
NAGA_TTS_MODEL=gpt-4o-mini-tts:free  # Optional - default model
ORIGIN=https://your.domain    # Required in production for CORS
```

## Deployment

Uses Bun runtime with `@sveltejs/adapter-node` and multi-stage Dockerfile (`oven/bun:1`). Deploy to Fly.io (`fly deploy`) or Coolify with persistent volume at `/app/data` for SQLite and audio files.

## Tech Stack

- Bun runtime
- SvelteKit 5 with Svelte 5 runes (`$state`, `$derived`)
- Tailwind CSS 4 (via Vite plugin)
- Dexie (IndexedDB wrapper)
- better-sqlite3 (server-side jobs)
- @mozilla/readability + jsdom (article extraction)
- lucide-svelte (icons)
