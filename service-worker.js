const CACHE_NAME = 'mtg-collection-v1';
const IMAGE_CACHE_NAME = 'mtg-images-v1';

const STATIC_ASSETS = [
    '/', 
    '/index.php',
    '/manifest.json',
    '/css/style.css',
    '/css/style-min.css',
    '/js/jquery.js',
    '/images/w_png.png'
];

// Install event: Cache static assets
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(STATIC_ASSETS);
        })
    );
});

// Activate event: Cleanup old caches
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cache) {
                    if (cache !== CACHE_NAME && cache !== IMAGE_CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
});

// Fetch event: Cache static assets + card images separately
self.addEventListener('fetch', function(event) {
    const url = new URL(event.request.url);

    // Cache card images separately
    if (url.pathname.startsWith('/cardimg/')) {
        event.respondWith(
            caches.open(IMAGE_CACHE_NAME).then(function(cache) {
                return cache.match(event.request).then(function(response) {
                    return response || fetch(event.request).then(function(networkResponse) {
                        cache.put(event.request, networkResponse.clone());
                        return networkResponse;
                    });
                });
            })
        );
        return;
    }

    // Cache-first for static assets
    event.respondWith(
        caches.match(event.request).then(function(response) {
            return response || fetch(event.request);
        })
    );
});
