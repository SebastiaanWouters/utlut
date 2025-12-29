#!/bin/bash
set -e

BIN_DIR="/var/www/html/bin"
mkdir -p "$BIN_DIR"
cd "$BIN_DIR"

echo "Installing FFmpeg (ARM64)..."
if [ ! -d "ffmpeg" ]; then
    curl -sSfL -o ffmpeg.tar.xz "https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-arm64-static.tar.xz"
    tar -xf ffmpeg.tar.xz
    mv ffmpeg-*-static ffmpeg
    chmod +x "$BIN_DIR/ffmpeg/ffmpeg"
    rm -f ffmpeg.tar.xz
    echo "FFmpeg installed"
else
    echo "FFmpeg already exists"
fi

echo "Installing yt-dlp (ARM64)..."
if [ ! -f "$BIN_DIR/yt-dlp" ]; then
    curl -sSfL -o "$BIN_DIR/yt-dlp" "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux_aarch64"
    chmod +x "$BIN_DIR/yt-dlp"
    echo "yt-dlp installed"
else
    echo "yt-dlp already exists"
fi

cd "$HOME/html" || cd /var/www/html

echo "YouTube dependencies ready"
