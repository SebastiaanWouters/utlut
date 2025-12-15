# Coolify Deployment Guide

Deploy this SvelteKit app with SQLite database on Coolify.

## Prerequisites

- Coolify instance running (self-hosted or cloud)
- GitHub repository connected to Coolify

## Quick Start

### 1. Create Application in Coolify

1. Go to your Coolify dashboard
2. Click **New Resource** → **Application**
3. Select **GitHub** and connect your repository
4. Select the `utlut` repository

### 2. Configure Build Settings

| Setting | Value |
|---------|-------|
| Build Pack | **Dockerfile** |
| Dockerfile Location | `Dockerfile` (default) |
| Port | `3000` |

### 3. Add Persistent Storage (Important!)

The SQLite database and generated audio files need persistent storage.

1. Go to **Storages** tab
2. Click **Add**
3. Configure:
   - **Source Path**: `/app/data`
   - **Destination Path**: Leave empty or use a named volume

This ensures your database survives container restarts and redeployments.

### 4. Set Environment Variables

Go to **Environment Variables** tab and add:

| Variable | Required | Value |
|----------|----------|-------|
| `ORIGIN` | Yes | Your full domain, e.g., `https://reader.yourdomain.com` |
| `NAGA_API_KEY` | Yes | Your Naga API key for TTS |
| `NAGA_TTS_MODEL` | No | Default: `gpt-4o-mini-tts:free` |

**Important:** The `ORIGIN` variable is required for SvelteKit SSR to work correctly. Without it, form submissions will fail with "Cross-site POST form submissions are forbidden".

### 5. Deploy

Click **Deploy** and wait for the build to complete.

## Database Management

### SQLite Location
- Inside container: `/app/data/jobs.db`
- Persisted via the volume mount you configured

### Features Already Configured
- **WAL Mode**: Enabled for concurrent access
- **Auto-initialization**: Tables created on first run
- **Job cleanup**: Completed jobs expire after 24 hours
- **Stale job recovery**: Jobs stuck processing >5 minutes auto-reset

### Backup (Optional)
To backup the database:
```bash
# SSH into your server or use Coolify's terminal
docker exec <container_id> cat /app/data/jobs.db > backup.db
```

## Troubleshooting

### 502 Bad Gateway
- Check if port 3000 is configured correctly
- Check container logs in Coolify

### "Cross-site POST form submissions are forbidden"
- Ensure `ORIGIN` environment variable is set correctly
- Must include protocol: `https://` not just the domain

### Database not persisting
- Verify storage mount is configured for `/app/data`
- Check storage tab shows the mount is active

### Audio generation not working
- Verify `NAGA_API_KEY` is set correctly
- Check container logs for API errors

## Architecture

```
┌─────────────────────────────────────────┐
│           Coolify Container             │
├─────────────────────────────────────────┤
│  /app/build/         (SvelteKit app)    │
│  /app/node_modules/  (dependencies)     │
│  /app/data/          (PERSISTENT VOL)   │
│    ├── jobs.db       (SQLite database)  │
│    └── audio/        (Generated MP3s)   │
└─────────────────────────────────────────┘
```

## Environment Variables Reference

| Variable | Description | Default |
|----------|-------------|---------|
| `ORIGIN` | Full URL for CORS | Required |
| `NAGA_API_KEY` | TTS API authentication | Required |
| `NAGA_TTS_MODEL` | TTS model to use | `gpt-4o-mini-tts:free` |
| `PORT` | Server port | `3000` |
| `NODE_ENV` | Environment mode | `production` |
