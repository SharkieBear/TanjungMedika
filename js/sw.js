// Nama cache yang digunakan
const CACHE_NAME = "tanjung-medika-v2";

// Aset statis yang akan di-cache
const STATIC_ASSETS = ["/", "/offline.html", "/css/styles.css", "/js/app.js", "/images/Logopwa.png", "/images/Logopwa2.png"];

// Halaman yang tidak boleh dicache
const NO_CACHE_PAGES = ["/admin-dashboard.php", "/admin-inventory.php", "/admin-kategori.php", "/admin-laporan.php", "/admin-logout.php", "/admin-pengguna.php", "/admin-pesanan.php", "/admin-produk.php", "/profile.php", "/order_history.php", "/OrderHistoryDetail.php", "/staff-dashboard.php", "/staff-inventory.php", "/staff-kategori.php", "/staff-logout.php", "/staff-pesanan.php", "/staff-produk.php"];

// Install Service Worker dan cache static assets
self.addEventListener("install", (event) => {
    console.log("Service Worker installing...");
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => {
                console.log("Caching static assets...");
                return cache.addAll(STATIC_ASSETS);
            })
            .catch((err) => console.error("Gagal cache static assets:", err))
    );
});

// Push Notification
self.addEventListener("push", (event) => {
    const data = event.data.json();
    const options = {
        body: data.body,
        icon: "/images/Logopwa.png",
        badge: "/images/Logopwa.png",
        data: {
            url: data.url || "/",
        },
    };

    console.log("Push notification received:", data);
    event.waitUntil(self.registration.showNotification(data.title, options));
});

// Ketika pengguna mengklik notifikasi
self.addEventListener("notificationclick", (event) => {
    console.log("Notification clicked:", event.notification.data.url);
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url));
});

// Fetch event untuk cache dan fallback ke offline.html jika gagal
self.addEventListener("fetch", (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // PERUBAHAN: Cek protokol di awal
    if (url.protocol === "chrome-extension:") {
        console.log("Mengabaikan permintaan dari ekstensi Chrome:", request.url);
        return; // Keluar dari fungsi fetch lebih awal
    }

    // Jangan cache halaman sensitif atau request selain GET
    if (NO_CACHE_PAGES.some((page) => url.pathname.endsWith(page)) || request.method !== "GET") {
        return;
    }

    console.log("Fetching:", request.url);

    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                console.log("Returning cached response for", request.url);
                return cachedResponse;
            }

            return fetch(request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                })
                .catch(() => {
                    console.log("Network error, returning offline.html");
                    return caches.match("/offline.html");
                });
        })
    );
});

// Aktifkan service worker dan hapus cache lama
self.addEventListener("activate", (event) => {
    console.log("Service Worker activating...");
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (!cacheWhitelist.includes(cacheName)) {
                        console.log("Deleting old cache:", cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});
