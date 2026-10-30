/* Only public fallback assets are cached. Account pages and financial requests
   always use the network, including Inertia requests and all mutations. */
const CACHE = 'predicafrica-pwa-v1';
const ASSETS = ['/offline.html', '/PA_Favicon_512x512.svg', '/icons/predicafrica-192.png', '/icons/predicafrica-512.png', '/icons/predicafrica-maskable-512.png', '/icons/apple-touch-icon.png'];
self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(ASSETS)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('predicafrica-pwa-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => (await caches.match('/offline.html')) || Response.error()));
    } else if (ASSETS.includes(url.pathname) && !url.search) {
        event.respondWith(caches.match(request).then(cached => cached || fetch(request)));
    }
});
