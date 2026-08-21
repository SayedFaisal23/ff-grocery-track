const CACHE_VERSION = 'ffgrocery-v4';
const PRECACHE = `${CACHE_VERSION}-precache`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;
const OFFLINE_PAGE = '/offline.html';

// Keep the offline experience public. Authenticated HTML, API responses, and
// uploaded documents must never be stored in a shared browser cache.
const PRECACHE_URLS = [
    OFFLINE_PAGE,
    '/manifest.json',
    '/favicon.ico',
    '/css/app.css',
    '/images/icon-192.png',
    '/images/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(PRECACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames
                .filter((cacheName) => cacheName.startsWith('ffgrocery-') && ![PRECACHE, RUNTIME_CACHE].includes(cacheName))
                .map((cacheName) => caches.delete(cacheName)),
        )).then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkOnlyPage(request));
        return;
    }

    if (isStaticAsset(request, url)) {
        event.respondWith(cacheFirstAsset(request));
    }
});

async function networkOnlyPage(request) {
    try {
        return await fetch(request);
    } catch {
        return (await caches.match(OFFLINE_PAGE)) || Response.error();
    }
}

async function cacheFirstAsset(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);

        if (networkResponse.ok && networkResponse.type === 'basic') {
            const cache = await caches.open(RUNTIME_CACHE);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch {
        return Response.error();
    }
}

function isStaticAsset(request, url) {
    if (url.pathname.startsWith('/storage/')) {
        return false;
    }

    return ['style', 'script', 'image', 'font'].includes(request.destination)
        || /\.(?:css|js|mjs|png|jpe?g|gif|svg|webp|ico|woff2?)$/i.test(url.pathname);
}
