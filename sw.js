const CACHE_NAME = 'no9ati-offline-v2.1.0';
// Only cache essential offline assets - no dynamic content
const urlsToCache = [
  '/no9ati/',
  '/no9ati/offline.html',
  '/no9ati/assets/icons/icon-192x192.svg',
  '/no9ati/assets/icons/icon-512x512.svg',
  '/no9ati/assets/css/style.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
];

// Install event - cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .catch(error => {
        console.log('Cache install failed:', error);
      })
  );
});

// Fetch event - network first, no dynamic caching
self.addEventListener('fetch', event => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Skip chrome-extension and other non-http requests
  if (!event.request.url.startsWith('http')) {
    return;
  }

  // Always fetch from network first for fresh data
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Return fresh response from network - no caching
        return response;
      })
      .catch(() => {
        // Only use cache for offline page if network fails
        if (event.request.mode === 'navigate') {
          return caches.match('/no9ati/offline.html');
        }
        // For other resources, try cache as last resort
        return caches.match(event.request);
      })
  );
});

// Activate event - cleanup old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// Background sync for offline actions
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  }
});

function doBackgroundSync() {
  // Handle offline actions when connection is restored
  return new Promise((resolve) => {
    // Sync offline data when connection is restored
    console.log('Background sync triggered');
    resolve();
  });
}

// Push notifications (for future use)
self.addEventListener('push', event => {
  if (event.data) {
    const data = event.data.json();
    const options = {
      body: data.body,
      icon: '/no9ati/assets/icons/icon-192x192.svg',
      badge: '/no9ati/assets/icons/icon-72x72.svg',
      vibrate: [100, 50, 100],
      data: {
        dateOfArrival: Date.now(),
        primaryKey: data.primaryKey
      },
      actions: [
        {
          action: 'explore',
          title: 'Voir plus',
          icon: '/no9ati/assets/icons/icon-192x192.svg'
        },
        {
          action: 'close',
          title: 'Fermer',
          icon: '/no9ati/assets/icons/icon-192x192.svg'
        }
      ]
    };

    event.waitUntil(
      self.registration.showNotification(data.title, options)
    );
  }
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
  event.notification.close();

  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('/no9ati/')
    );
  }
});