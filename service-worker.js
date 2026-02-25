/*
Version:     3.5
Date:        25/02/26
Name:        service-worker.js
Purpose:     Safe caching of static assets and images for MTG Collection.
Notes:       Avoids caching HTML or dynamic fragments.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

'use strict';

const DEBUG = false;
const CACHE_VERSION = (function() {
    const url = new URL(self.location.href);
    return url.searchParams.get('v') || 'v1';
})();
const STATIC_CACHE = 'mtg-static-' + CACHE_VERSION;
const IMAGE_CACHE = 'mtg-images-' + CACHE_VERSION;
const IMAGE_CACHE_MAX_ITEMS = 200;

const STATIC_ASSETS = [
    '/manifest.json',
    '/css/style.css?v=' + CACHE_VERSION,
    '/css/style-min.css?v=' + CACHE_VERSION,
    '/js/jquery.js?v=' + CACHE_VERSION,
    '/fonts/alcarin/AlcarinTengwar-Regular.woff2',
    '/fonts/alcarin/AlcarinTengwar-Regular.woff',
    '/fonts/alcarin/AlcarinTengwar-Bold.woff2',
    '/fonts/alcarin/AlcarinTengwar-Bold.woff',
    '/images/w_png.png',
    '/images/ajax-loader.gif'
];

function logDebug(message, data) {
    if (!DEBUG) {
        return;
    }
    if (typeof data === 'undefined') {
        console.debug('[SW]', message);
        return;
    }
    console.debug('[SW]', message, data);
}

function isSameOrigin(url) {
    return url.origin === self.location.origin;
}

async function cachePut(cache, request, response) {
    if (response && response.ok) {
        await cache.put(request, response.clone());
    }
}

async function trimCache(cacheName, maxItems) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    if (keys.length <= maxItems) {
        return;
    }
    const excess = keys.length - maxItems;
    for (let i = 0; i < excess; i++) {
        await cache.delete(keys[i]);
    }
}

async function cacheFirst(request, cacheName, maxItems) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    if (cached) {
        logDebug('cache-first hit', request.url);
        return cached;
    }
    logDebug('cache-first miss', request.url);
    const response = await fetch(request);
    await cachePut(cache, request, response);
    if (maxItems) {
        trimCache(cacheName, maxItems);
    }
    return response;
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const fetchPromise = fetch(request).then(async (response) => {
        await cachePut(cache, request, response);
        logDebug('stale-while-revalidate update', request.url);
        return response;
    });
    if (cached) {
        logDebug('stale-while-revalidate hit', request.url);
        return cached;
    }
    logDebug('stale-while-revalidate miss', request.url);
    return fetchPromise;
}

self.addEventListener('install', (event) => {
    logDebug('install');
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            const requests = STATIC_ASSETS.map((asset) => new Request(asset, { cache: 'reload' }));
            return cache.addAll(requests);
        })
    );
});

self.addEventListener('activate', (event) => {
    logDebug('activate');
    event.waitUntil(
        caches.keys().then((names) =>
            Promise.all(
                names.map((name) => (name === STATIC_CACHE || name === IMAGE_CACHE
                    ? undefined
                    : caches.delete(name)))
            )
        )
    );
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        logDebug('skip-waiting message');
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (!isSameOrigin(url)) {
        return;
    }

    if (url.pathname.endsWith('.php')) {
        logDebug('network-only php', request.url);
        event.respondWith(fetch(request));
        return;
    }

    if (request.mode === 'navigate' || request.destination === 'document') {
        logDebug('network-only document', request.url);
        event.respondWith(
            fetch(request).catch(() => new Response('Offline', { status: 503, statusText: 'Offline' }))
        );
        return;
    }

    if (request.destination === 'image'
        && (url.pathname.startsWith('/cardimg/') || url.pathname.startsWith('/images/'))
    ) {
        event.respondWith(cacheFirst(request, IMAGE_CACHE, IMAGE_CACHE_MAX_ITEMS));
        return;
    }

    if (request.destination === 'style'
        || request.destination === 'script'
        || request.destination === 'font'
    ) {
        event.respondWith(staleWhileRevalidate(request, STATIC_CACHE));
        return;
    }
});
