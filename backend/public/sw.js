/**
 * Service Worker for POS Application
 * Phase F8: Offline & Sync
 * 
 * Handles caching of static assets and API responses for offline support
 */

var CACHE_NAME = 'pos-cache-v1';
var API_CACHE_NAME = 'pos-api-cache-v1';

// Static assets to cache on install
var STATIC_ASSETS = [
  '/',
  '/index.html',
  '/manifest.json',
];

// API endpoints to cache
var CACHEABLE_API_PATTERNS = [
  /\/api\/pos\/products$/,
  /\/api\/categories\/tree$/,
  /\/api\/payment-methods$/,
];

// Install event - cache static assets
self.addEventListener('install', function(event) {
  console.log('[SW] Installing service worker...');
  
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      console.log('[SW] Caching static assets');
      // Cache assets individually to avoid failing on missing files
      return Promise.all(
        STATIC_ASSETS.map(function(url) {
          return cache.add(url).catch(function(error) {
            console.warn('[SW] Failed to cache:', url, error);
          });
        })
      );
    }).catch(function(error) {
      console.warn('[SW] Cache initialization failed:', error);
    })
  );

  // Skip waiting to activate immediately
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', function(event) {
  console.log('[SW] Activating service worker...');

  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames
          .filter(function(name) { return name !== CACHE_NAME && name !== API_CACHE_NAME; })
          .map(function(name) {
            console.log('[SW] Deleting old cache:', name);
            return caches.delete(name);
          })
      );
    })
  );

  // Take control of all clients immediately
  self.clients.claim();
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', function(event) {
  var url = new URL(event.request.url);

  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Check if this is an API request we want to cache
  var isApiRequest = url.pathname.startsWith('/api/');
  var isCacheableApi = CACHEABLE_API_PATTERNS.some(function(pattern) {
    return pattern.test(url.pathname);
  });

  if (isApiRequest) {
    if (isCacheableApi) {
      // Network-first strategy for cacheable API requests
      event.respondWith(networkFirstWithCache(event.request, API_CACHE_NAME));
    }
    // Don't cache other API requests
    return;
  }

  // Cache-first strategy for static assets
  event.respondWith(cacheFirstWithNetwork(event.request));
});

// Network-first strategy - try network, fall back to cache
function networkFirstWithCache(request, cacheName) {
  return fetch(request)
    .then(function(networkResponse) {
      // Cache successful responses
      if (networkResponse.ok) {
        var responseClone = networkResponse.clone();
        caches.open(cacheName).then(function(cache) {
          cache.put(request, responseClone);
        });
      }
      return networkResponse;
    })
    .catch(function() {
      // Network failed, try cache
      return caches.match(request).then(function(cachedResponse) {
        if (cachedResponse) {
          console.log('[SW] Serving from cache:', request.url);
          return cachedResponse;
        }
        
        // Return offline error response
        return new Response(
          JSON.stringify({ error: 'Offline', message: 'No cached data available' }),
          {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'application/json' },
          }
        );
      });
    });
}

// Cache-first strategy - try cache, fall back to network
function cacheFirstWithNetwork(request) {
  return caches.match(request).then(function(cachedResponse) {
    if (cachedResponse) {
      // Return cached response and update cache in background
      fetchAndCache(request).catch(function() {
        // Silently fail if network is unavailable
      });
      return cachedResponse;
    }

    // Not in cache, try network
    return fetchAndCache(request).catch(function() {
      // Return basic offline page for navigation requests
      if (request.mode === 'navigate') {
        return caches.open(CACHE_NAME).then(function(cache) {
          return cache.match('/index.html');
        }).then(function(offlineResponse) {
          if (offlineResponse) {
            return offlineResponse;
          }
          throw new Error('No offline page available');
        });
      }
      throw new Error('Request failed');
    });
  });
}

// Fetch and cache a request
function fetchAndCache(request) {
  return fetch(request).then(function(response) {
    if (response.ok) {
      var responseClone = response.clone();
      caches.open(CACHE_NAME).then(function(cache) {
        cache.put(request, responseClone);
      });
    }
    return response;
  });
}

// Background sync event
self.addEventListener('sync', function(event) {
  if (event.tag === 'sync-sales') {
    console.log('[SW] Background sync triggered:', event.tag);
    event.waitUntil(syncSales());
  }
});

// Sync pending sales
function syncSales() {
  // Notify all clients to sync
  return self.clients.matchAll().then(function(clients) {
    clients.forEach(function(client) {
      client.postMessage({
        type: 'SYNC_SALES',
        timestamp: Date.now(),
      });
    });
  });
}

// Message event - handle messages from main thread
self.addEventListener('message', function(event) {
  var data = event.data || {};
  var type = data.type;

  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;

    case 'CLEAR_CACHE':
      event.waitUntil(
        Promise.all([
          caches.delete(CACHE_NAME),
          caches.delete(API_CACHE_NAME),
        ]).then(function() {
          if (event.ports[0]) {
            event.ports[0].postMessage({ success: true });
          }
        })
      );
      break;

    case 'GET_CACHE_STATUS':
      event.waitUntil(
        getCacheStatus().then(function(status) {
          if (event.ports[0]) {
            event.ports[0].postMessage(status);
          }
        })
      );
      break;

    default:
      console.log('[SW] Unknown message type:', type);
  }
});

// Get cache status
function getCacheStatus() {
  return Promise.all([
    caches.open(CACHE_NAME).then(function(cache) { return cache.keys(); }),
    caches.open(API_CACHE_NAME).then(function(cache) { return cache.keys(); })
  ]).then(function(results) {
    return {
      staticCacheSize: results[0].length,
      apiCacheSize: results[1].length,
    };
  });
}
