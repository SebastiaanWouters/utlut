#!/bin/bash
set -e

ARCH=$(uname -m)
BIN_DIR="/var/www/html/bin"
mkdir -p "$BIN_DIR"
cd "$BIN_DIR"

echo "Detected architecture: $ARCH"

echo "Installing FFmpeg..."
if [ ! -d "ffmpeg" ]; then
    if [ "$ARCH" = "aarch64" ]; then
        FFMPEG_URL="https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-arm64-static.tar.xz"
    else
        FFMPEG_URL="https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz"
    fi
    echo "Downloading FFmpeg from: $FFMPEG_URL"
    curl -sSfL -o ffmpeg.tar.xz "$FFMPEG_URL"
    tar -xf ffmpeg.tar.xz
    mv ffmpeg-*-static ffmpeg
    chmod +x "$BIN_DIR/ffmpeg/ffmpeg"
    rm -f ffmpeg.tar.xz
    echo "FFmpeg installed"
else
    echo "FFmpeg already exists"
fi

echo "Installing yt-dlp..."
if [ ! -f "$BIN_DIR/yt-dlp" ]; then
    if [ "$ARCH" = "aarch64" ]; then
        YTDLP_URL="https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux_aarch64"
    else
        YTDLP_URL="https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux"
    fi
    echo "Downloading yt-dlp from: $YTDLP_URL"
    curl -sSfL -o "$BIN_DIR/yt-dlp" "$YTDLP_URL"
    chmod +x "$BIN_DIR/yt-dlp"
    echo "yt-dlp installed"
else
    echo "yt-dlp already exists"
fi

cd "$HOME/html" || cd /var/www/html

echo "YouTube dependencies ready"
