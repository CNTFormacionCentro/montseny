const CACHE_NAME = 'montseny-cache-v1';

// Al instalarse, el motor se activa inmediatamente
self.addEventListener('install', (event) => {
  self.skipWaiting();
});

// Al activarse, toma el mando de las pestañas abiertas
self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim());
});

// Escucha las peticiones de red (indispensable para que Opera deje instalar)
self.addEventListener('fetch', (event) => {
  event.respondWith(
    fetch(event.request).catch(() => {
      // Aquí podríamos poner algo si no hay internet en el futuro
    })
  );
});
