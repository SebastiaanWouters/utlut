# YouTube Audio Feature - Technical Plan

## Overview
Allow users to add YouTube videos to their library by extracting audio directly from the video. The system accepts a YouTube URL, validates it, extracts audio using yt-dlp, and stores it for playback.

## Scope

### In Scope
- Accept all YouTube URL formats (youtube.com, youtu.be, music.youtube.com, shorts, embeds)
- Extract audio directly from YouTube (no TTS generation)
- Extract title and duration metadata
- Store audio in `youtube/{article_id}.mp3`
- Show YouTube icon in library UI
- Separate "Add YouTube" flow from "Add URL" flow

### Out of Scope
- Transcript/caption extraction
- Video download
- Playlist import (single videos only)

## Architecture

```
User submits YouTube URL
        ↓
[library.blade.php] addFromYouTube()
        ↓
YouTubeUrlParser::parse($url)
  - Validates URL format
  - Extracts video ID
  - Returns normalized URL
        ↓
Create Article (source_type: 'youtube', extraction_status: 'extracting')
        ↓
Dispatch ExtractYouTubeAudio job
        ↓
[ExtractYouTubeAudio Job]
  - YouTubeAudioExtractor::extract($url)
    - Calls yt-dlp for metadata (title, duration)
    - Calls yt-dlp for audio extraction
    - Returns audio content + metadata
        ↓
  - Store audio at youtube/{article_id}.mp3
  - Update Article (title, audio_url)
  - Update ArticleAudio (duration_seconds, status: 'ready')
```

## Data Model Changes

### articles table
Add column:
- `source_type` ENUM('article', 'youtube') DEFAULT 'article'

## Services

### YouTubeUrlParser
Validates and normalizes YouTube URLs.

**Supported URL formats:**
- `https://www.youtube.com/watch?v=VIDEO_ID`
- `https://youtube.com/watch?v=VIDEO_ID`
- `https://youtu.be/VIDEO_ID`
- `https://www.youtube.com/shorts/VIDEO_ID`
- `https://youtube.com/shorts/VIDEO_ID`
- `https://music.youtube.com/watch?v=VIDEO_ID`
- `https://www.youtube.com/embed/VIDEO_ID`
- `https://www.youtube.com/v/VIDEO_ID`
- With/without `www.`, with/without `https://`

**Methods:**
- `isYouTubeUrl(string $url): bool`
- `extractVideoId(string $url): ?string`
- `normalize(string $url): string` - Returns canonical `https://www.youtube.com/watch?v=ID`

### YouTubeAudioExtractor
Wrapper around yt-dlp for audio extraction.

**Methods:**
- `extract(string $url): array{title: string, duration_seconds: int, audio_content: string}`
- `getMetadata(string $url): array{title: string, duration_seconds: int}`

**yt-dlp commands:**
```bash
# Metadata only (fast)
yt-dlp --dump-json --no-download "$url"

# Audio extraction
yt-dlp -x --audio-format mp3 --audio-quality 0 -o - "$url"
```

## Jobs

### ExtractYouTubeAudio
Similar structure to GenerateArticleAudio but simpler:

- Implements ShouldQueue, ShouldBeUnique
- timeout: 300 seconds
- tries: 3
- backoff: [10, 30, 60]

**Flow:**
1. Call YouTubeAudioExtractor::extract()
2. Store audio to `youtube/{article_id}.mp3`
3. Update Article with title, audio_url
4. Create/update ArticleAudio with duration_seconds, status

## UI Changes

### library.blade.php

1. Add "Add YouTube" button next to "Add URL"
2. New modal for YouTube URL input
3. Show YouTube icon for source_type='youtube' items
4. Validation: reject non-YouTube URLs with error message

## Configuration

### config/sundo.php
```php
'youtube' => [
    'timeout' => 300,
    'max_duration_seconds' => 7200, // 2 hours max
    'audio_quality' => 0, // best
    'audio_format' => 'mp3',
],
```

## Error Handling

| Error | User Message |
|-------|-------------|
| Invalid YouTube URL | "Please enter a valid YouTube URL" |
| Video not found | "Video not found or unavailable" |
| Video too long | "Video exceeds maximum duration (2 hours)" |
| Age-restricted | "This video is age-restricted and cannot be downloaded" |
| yt-dlp failure | "Failed to extract audio. Please try again." |

## Testing Strategy

1. **Unit Tests:**
   - YouTubeUrlParser: all URL formats, invalid URLs
   - YouTubeAudioExtractor: mock yt-dlp calls

2. **Feature Tests:**
   - addFromYouTube Livewire action
   - Job dispatch and completion
   - Error handling paths

## Dependencies

System requirements:
- `yt-dlp` (latest)
- `ffmpeg` (for audio conversion)
