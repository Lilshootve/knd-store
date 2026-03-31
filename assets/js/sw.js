// KND Store - Service Worker Optimizado
const CACHE_NAME = 'knd-store-v1.1.2';
const STATIC_CACHE = 'knd-static-v1.1.2';
const DYNAMIC_CACHE = 'knd-dynamic-v1.1.2';

// Solo cachear recursos locales y algunos externos seguros
const urlsToCache = [
    '/',
    '/index.php',
    '/products.php',
    '/about.php',
    '/contact.php',
    '/faq.php',
    '/privacy.php',
    '/terms.php',
    '/offline.html',
    '/assets/css/style.css',
    '/assets/css/knd-ui.css',
    '/assets/css/mobile-optimization.css',
    '/assets/css/font-awesome-fix.css',
    '/assets/js/main.js',
    '/assets/js/mobile-optimization.js',
    '/assets/js/font-awesome-fix.js',
    '/assets/images/knd-logo.png',
    '/assets/images/favicon.ico',
    '/assets/images/favicon-96x96.png',
    '/assets/images/apple-touch-icon.png',
    '/assets/images/web-app-manifest-192x192.png',
    '/assets/images/web-app-manifest-512x512.png'
];

// Recursos externos que NO se cachean (causan problemas de CSP)
const externalResources = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@400;600&display=swap',
    'https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js'
];

// Estrategia de cache: Cache First para recursos estáticos
async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.error('Error en cacheFirst:', error);
        return new Response('Error de red', { status: 503 });
    }
}

// Estrategia de cache: Network First para páginas dinámicas
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.error('Error en networkFirst:', error);
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        return caches.match('/offline.html');
    }
}

// Función para verificar si es un recurso externo problemático
function isExternalResource(url) {
    // Verificar si la URL es externa al dominio actual
    const currentHost = self.location.hostname;
    const urlHost = new URL(url).hostname;
    
    // Si es un dominio externo, verificar si está en la lista de recursos problemáticos
    if (urlHost !== currentHost) {
        return externalResources.some(resource => url.includes(resource));
    }
    
    return false;
}

// Instalación del Service Worker
self.addEventListener('install', event => {
    console.log('🚀 Instalando Service Worker optimizado para KND Store...');
    
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then(cache => {
                console.log('📦 Cache estático abierto');
                return cache.addAll(urlsToCache);
            }),
            caches.open(DYNAMIC_CACHE).then(cache => {
                console.log('📦 Cache dinámico abierto');
                return cache;
            })
        ]).then(() => {
            console.log('✅ Service Worker instalado correctamente');
            return self.skipWaiting();
        }).catch(error => {
            console.error('❌ Error durante la instalación:', error);
        })
    );
});

// Activación del Service Worker
self.addEventListener('activate', event => {
    console.log('🔄 Activando Service Worker optimizado...');
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                        console.log('🗑️ Eliminando cache antiguo:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('✅ Service Worker activado');
            return self.clients.claim();
        })
    );
});

// Interceptar peticiones de red con estrategias optimizadas
self.addEventListener('fetch', event => {
    const { request } = event;
    
    // No interceptar blob: (texturas embebidas en GLB, etc.)
    if (request.url.startsWith('blob:')) {
        return;
    }

    const url = new URL(request.url);
    // GLB/3D models: siempre ir a red, no cachear (evita 404 cacheados)
    if (url.pathname.endsWith('.glb') || url.pathname.endsWith('.gltf')) {
        return;
    }
    
    // Solo manejar peticiones GET
    if (request.method !== 'GET') {
        return;
    }

    // Excluir peticiones a APIs externas y recursos problemáticos
    if (url.hostname !== self.location.hostname || 
        url.pathname.includes('api.') || 
        url.pathname.includes('analytics') ||
        url.pathname.includes('tracking') ||
        isExternalResource(request.url)) {
        console.log('🔄 Pasando recurso externo sin cachear:', request.url);
        // Para recursos externos, simplemente pasar la petición sin interceptar
        return;
    }

    // Estrategia según el tipo de recurso
    if (request.destination === 'document') {
        // Páginas HTML: Network First
        event.respondWith(networkFirst(request));
    } else if (request.destination === 'style' || 
               request.destination === 'script' || 
               request.destination === 'image') {
        // Recursos estáticos: Cache First
        event.respondWith(cacheFirst(request));
    } else {
        // Otros recursos: Network First
        event.respondWith(networkFirst(request));
    }
});

// Manejar mensajes del cliente
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    }
});

// Manejar notificaciones push (futuro)
self.addEventListener('push', event => {
    if (event.data) {
        const data = event.data.json();
        const options = {
            body: data.body || 'Nueva actualización en KND Store',
            icon: '/assets/images/web-app-manifest-192x192.png',
            badge: '/assets/images/web-app-manifest-192x192.png',
            vibrate: [100, 50, 100],
            data: {
                dateOfArrival: Date.now(),
                primaryKey: 1
            },
            actions: [
                {
                    action: 'explore',
                    title: 'Ver más',
                    icon: '/assets/images/web-app-manifest-192x192.png'
                },
                {
                    action: 'close',
                    title: 'Cerrar',
                    icon: '/assets/images/web-app-manifest-192x192.png'
                }
            ]
        };

        event.waitUntil(
            self.registration.showNotification(data.title || 'KND Store', options)
        );
    }
});

// Manejar clics en notificaciones
self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/products.php')
        );
    } else if (event.action === 'close') {
        // Solo cerrar la notificación
    } else {
        // Acción por defecto
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});

// Manejar notificaciones rechazadas
self.addEventListener('notificationclose', event => {
    console.log('Notificación cerrada:', event.notification.tag);
});

// Función para limpiar caches antiguos
function cleanOldCaches() {
    return caches.keys().then(cacheNames => {
        return Promise.all(
            cacheNames.map(cacheName => {
                if (cacheName !== CACHE_NAME) {
                    console.log('🗑️ Limpiando cache antiguo:', cacheName);
                    return caches.delete(cacheName);
                }
            })
        );
    });
}

// Función para actualizar cache
function updateCache() {
    return caches.open(CACHE_NAME)
        .then(cache => {
            return cache.addAll(urlsToCache);
        });
}

// Exportar funciones para uso externo
self.KNDServiceWorker = {
    cleanOldCaches,
    updateCache,
    CACHE_NAME
}; 