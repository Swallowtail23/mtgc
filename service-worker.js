const CACHE_NAME = 'mtg-collection-v2';
const IMAGE_CACHE_NAME = 'mtg-images-v2';

const STATIC_ASSETS = [
  '/manifest.json',
  '/css/style.css',
  '/css/style-min.css',
  '/js/jquery.js',
  '/images/w_png.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)));
  self.skipWaiting(); // activate new SW ASAP
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(
        names.map((n) => (n === CACHE_NAME || n === IMAGE_CACHE_NAME ? undefined : caches.delete(n)))
      )
    )
  );
  self.clients.claim(); // control pages immediately
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // 1) Navigation requests (HTML) — network-first, fallback to cache if offline
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).then((res) => {
        // optionally update a small runtime cache of HTML for offline fallback
        const copy = res.clone();
        caches.open(CACHE_NAME).then((c) => c.put(req, copy));
        return res;
      }).catch(async () => {
        const cached = await caches.match(req);
        return cached || new Response('Offline', { status: 503, statusText: 'Offline' });
      })
    );
    return;
  }

  // 2) Card images — cache-first (as you had)
  if (url.pathname.startsWith('/cardimg/')) {
    event.respondWith(
      caches.open(IMAGE_CACHE_NAME).then((cache) =>
        cache.match(req).then((hit) => hit || fetch(req).then((net) => { cache.put(req, net.clone()); return net; }))
      )
    );
    return;
  }

  // 3) Other static assets — cache-first
  event.respondWith(
    caches.match(req).then((hit) => hit || fetch(req))
  );
});
