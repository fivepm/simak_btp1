<?php
// Tentukan prefix path. Jika variabel $path_to_root belum diset, anggap kosong (root).
// Variabel $path_to_root ini akan diset di file index.php masing-masing.
$prefix = isset($path_to_root) ? $path_to_root : '';
$ajaxUrl = $prefix . 'pages/development/ajax_activity_log.php';
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {

        // Fungsi untuk lapor ke backend
        function reportScreenshotAttempt(triggerKey) {
            // Ambil URL halaman saat ini (biar tau screenshot di halaman apa)
            const currentPath = window.location.pathname + window.location.search;

            const formData = new FormData();
            formData.append('action', 'log_screenshot');
            formData.append('trigger', triggerKey);
            formData.append('path', currentPath);

            // Kirim diam-diam (Silent Request)
            // Path ini sekarang DINAMIS, disuntikkan dari PHP
            fetch('<?php echo $ajaxUrl; ?>', {
                method: 'POST',
                body: formData
            }).then(res => {
                // Opsional: Efek visual blur sebentar jika terdeteksi
                // document.body.style.filter = 'blur(10px)';
                // setTimeout(() => {
                //     document.body.style.filter = 'none';
                // }, 1000);
            }).catch(err => console.error(err));
        }

        // --- DESKTOP DETECTION ---

        // 1. Deteksi Tombol PrintScreen
        window.addEventListener('keyup', (e) => {
            if (e.key === 'PrintScreen') {
                reportScreenshotAttempt('Tombol PrintScreen (PC)');
            }
        });

        // 2. Deteksi Ctrl + P (Print)
        window.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'p') {
                reportScreenshotAttempt('Ctrl + P (Cetak)');
            }
        });

        // 3. Deteksi Shift + Win + S (Snipping Tool Windows)
        window.addEventListener('keydown', (e) => {
            if (e.shiftKey && e.metaKey && e.key === 's') {
                reportScreenshotAttempt('Snipping Tool Shortcut');
            }
        });

        // --- MOBILE DETECTION ---

        // 4. Deteksi Gesture 3 Jari (Umum di Android untuk Screenshot)
        window.addEventListener('touchstart', (e) => {
            // Jika user menyentuh layar dengan 3 jari sekaligus
            if (e.touches.length === 3) {
                reportScreenshotAttempt('Gesture 3 Jari (Mobile)');
            }
        });

    });
</script>