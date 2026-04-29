// === FILE: sw.js ===
// Letakkan file ini di folder paling luar (root), sejajar dengan index.php

const CACHE_NAME = "simak-cache-v1";

// Event Install: Dipanggil saat pertama kali service worker didaftarkan
self.addEventListener("install", (event) => {
  // Lewati proses antrean (langsung aktif)
  self.skipWaiting();
});

// Event Activate: Dipanggil saat service worker mulai mengambil alih
self.addEventListener("activate", (event) => {
  event.waitUntil(clients.claim());
});

// Event Fetch: Mengontrol request jaringan (Network-first strategy sederhana)
self.addEventListener("fetch", (event) => {
  event.respondWith(
    fetch(event.request).catch(() => {
      // Jika sedang offline (fetch gagal), coba cari di cache
      return caches.match(event.request);
    }),
  );
});
