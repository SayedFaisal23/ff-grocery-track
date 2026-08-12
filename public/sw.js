const CACHE_NAME = 'ffgrocery-cache-v2';
const ASSETS_TO_CACHE = [
    '/login',
    '/manifest.json',
    '/images/icon-192.png',
    '/images/icon-512.png'
];

// Pemasangan Service Worker dan Cache Aset Asas
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
    self.skipWaiting();
});

// Pembersihan Cache Lama
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Strategi Failback Rangkaian Utama dengan Cache Tempatan
self.addEventListener('fetch', event => {
    // Hanya proses permintaan GET
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request)
            .then(networkResponse => {
                // Simpan salinan terbaru dalam cache jika sah
                if (networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            })
            .catch(() => {
                // Jika offline, cuba dapatkan dari cache
                return caches.match(event.request).then(cachedResponse => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Jika halaman utama dicari tetapi tiada internet, kembalikan ke /login sebagai fallback
                    if (event.request.mode === 'navigate') {
                        return caches.match('/login');
                    }
                });
            })
    );
});
