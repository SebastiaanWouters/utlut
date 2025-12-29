#!/bin/bash
# Setup YouTube cookies for Laravel Cloud deployment
# This script decodes base64-encoded cookies from ENV and writes to storage

set -e

echo "Setting up YouTube cookies..."

COOKIES_B64="${YOUTUBE_COOKIES_B64:-}"
COOKIES_PATH="${YOUTUBE_COOKIES_PATH:-storage/app/cookies.txt}"

if [ -z "$COOKIES_B64" ]; then
    echo "YOUTUBE_COOKIES_B64 not set in environment, skipping cookies setup"
    exit 0
fi

# Create storage directory if it doesn't exist
mkdir -p "$(dirname "$COOKIES_PATH")"

# Decode and write cookies file
echo "$COOKIES_B64" | base64 -d > "$COOKIES_PATH"

# Set secure permissions
chmod 600 "$COOKIES_PATH"

echo "YouTube cookies setup complete: $COOKIES_PATH"
echo "Cookies file permissions: $(ls -l "$COOKIES_PATH" | awk '{print $1}')"
