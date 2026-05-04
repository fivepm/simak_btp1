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

// Event Fetch: Mengontrol request jaringan (Network-first strategy)
self.addEventListener("fetch", (event) => {
  // PERBAIKAN 1: Jangan pernah mengganggu request POST (seperti proses Login & WebAuthn)
  if (event.request.method !== 'GET') {
    return; // Biarkan browser menangani secara default
  }

  event.respondWith(
    fetch(event.request).catch(async () => {
      // Jika sedang offline (fetch gagal), coba cari di cache
      const cachedResponse = await caches.match(event.request);
      
      // Jika file ditemukan di cache, kembalikan file tersebut
      if (cachedResponse) {
        return cachedResponse;
      }
      
      // PERBAIKAN 2: Jika tidak ada di cache, KEMBALIKAN OBJECT 'Response' DARURAT
      // Hal ini mencegah error "Failed to convert value to 'Response'"
      return new Response("Anda sedang offline dan halaman/data ini belum tersimpan di memori perangkat.", {
        status: 503,
        statusText: "Service Unavailable",
        headers: new Headers({ "Content-Type": "text/plain" })
      });
    })
  );
});