self.addEventListener('fetch', function(event) {
    // For now, simply pass through all fetches.
    event.respondWith(fetch(event.request));
});
