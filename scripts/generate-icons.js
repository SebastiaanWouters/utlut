#!/usr/bin/env node

import sharp from 'sharp';
import { mkdir } from 'fs/promises';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const publicDir = join(__dirname, '..', 'public');
const iconsDir = join(publicDir, 'icons');
const svgPath = join(publicDir, 'favicon.svg');

const sizes = [96, 192, 256, 512];

async function generateIcons() {
    await mkdir(iconsDir, { recursive: true });

    for (const size of sizes) {
        const outputPath = join(iconsDir, `icon-${size}.png`);
        await sharp(svgPath)
            .resize(size, size)
            .png()
            .toFile(outputPath);
        console.log(`Generated: icon-${size}.png`);
    }

    console.log('Done! Icons generated in public/icons/');
}

generateIcons().catch(console.error);
