// Service worker của Quỹ nhóm, xem plan/06 mục 4.
// Chỉ cache file tĩnh đã build (tên có hash) và biểu tượng. KHÔNG cache API và
// ảnh nhóm (/api, /media): dữ liệu tiền bạc luôn lấy mới, ảnh nhóm riêng tư
// không được nằm lại trên máy. Mất mạng thì hiện trang /offline.html.
const VERSION = 'qn-v1';
const STATIC = `${VERSION}-static`;
const PRECACHE = ['/offline.html', '/icons/icon-192.png', '/favicon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/media/')) return;

    // File build có hash và biểu tượng: lấy trong cache trước.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(STATIC).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
        return;
    }

    // Trang: luôn lấy mới, mất mạng thì trang báo offline.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
    }
});
