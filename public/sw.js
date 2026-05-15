const CACHE_NAME = 'mushaf-pdf-cache-v1';
const PDF_CACHE = 'pdf-store';

self.addEventListener('install', e => {
    self.skipWaiting();
});

self.addEventListener('fetch', e => {
    // Cache PDF requests
    if (e.request.url.includes('serve-file.php')) {
        e.respondWith(
            caches.open(PDF_CACHE).then(cache => {
                return cache.match(e.request).then(response => {
                    if (response) return response; // Return cached
                    
                    return fetch(e.request).then(fetchResponse => {
                        cache.put(e.request, fetchResponse.clone());
                        return fetchResponse;
                    });
                });
            })
        );
    }
});