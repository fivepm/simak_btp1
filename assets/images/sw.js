const CACHE = "v1";
const URLS_TO_CACHE = ["/", "/assets/images/logo_web_bg.png"];

// Install → simpan file ke cache
self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(URLS_TO_CACHE)));
});

// Fetch → ambil dari cache dulu, kalau tidak ada baru ke network
self.addEventListener("fetch", (e) => {
  e.respondWith(
    caches.match(e.request).then((response) => response || fetch(e.request))
  );
});
