import { readFile, mkdir } from 'node:fs/promises';
import { chromium } from 'playwright';

const svg = await readFile(new URL('../public/PA_Favicon_512x512.svg', import.meta.url), 'utf8');
const output = new URL('../public/icons/', import.meta.url);
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ channel: 'chrome', headless: true });
try {
    for (const [file, size, maskable] of [['predicafrica-192.png', 192, false], ['predicafrica-512.png', 512, false], ['apple-touch-icon.png', 180, false], ['predicafrica-maskable-512.png', 512, true]]) {
        const page = await browser.newPage({ viewport: { width: size, height: size }, deviceScaleFactor: 1 });
        await page.setContent(`<style>html,body{margin:0;width:100%;height:100%;background:#153a73;display:grid;place-items:center}svg{display:block;width:${maskable ? '65%' : '100%'};height:${maskable ? '65%' : '100%'}}</style>${svg.replace(/<\?xml[^>]*>/, '')}`);
        await page.screenshot({ path: new URL(file, output).pathname });
        await page.close();
    }
    console.log('PredicAfrica PWA icons generated from the existing SVG.');
} finally { await browser.close(); }
