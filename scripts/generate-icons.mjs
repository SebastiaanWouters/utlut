import sharp from 'sharp';
import { readFileSync, mkdirSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const publicDir = join(__dirname, '../public');
const iconsDir = join(publicDir, 'icons');

// Ensure icons directory exists
if (!existsSync(iconsDir)) {
    mkdirSync(iconsDir, { recursive: true });
}

// Read the SVG file
const svgBuffer = readFileSync(join(publicDir, 'favicon.svg'));

// Icon sizes to generate
const sizes = [
    { size: 96, name: 'icon-96.png' },
    { size: 192, name: 'icon-192.png' },
    { size: 256, name: 'icon-256.png' },
    { size: 512, name: 'icon-512.png' },
    { size: 180, name: '../apple-touch-icon.png' }, // Apple touch icon
    { size: 32, name: '../favicon-32x32.png' },
    { size: 16, name: '../favicon-16x16.png' },
];

async function generateIcons() {
    console.log('Generating PWA icons from favicon.svg...\n');

    for (const { size, name } of sizes) {
        const outputPath = join(iconsDir, name);

        await sharp(svgBuffer)
            .resize(size, size)
            .png()
            .toFile(outputPath);

        console.log(`  Created: ${name} (${size}x${size})`);
    }

    console.log('\nAll icons generated successfully!');
}

generateIcons().catch(console.error);
