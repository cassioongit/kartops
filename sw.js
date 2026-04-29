const CACHE_NAME = 'kartops-v1.6';
const ASSETS_TO_CACHE = [
    '/',
    '/index.php',
    '/home.php',
    '/css/style.css',
    '/css/loader.css',
    '/images/logo-kartops.png',
    '/images/logo-campeonato.png'
];

// Instalação do Service Worker
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(ASSETS_TO_CACHE))
            .then(self.skipWaiting())
    );
});

// Ativação e limpeza de cache antigo
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Estratégia de Fetch: Network with Cache Fallback
self.addEventListener('fetch', event => {
    event.respondWith(
        fetch(event.request)
            .catch(() => caches.match(event.request))
    );
});
