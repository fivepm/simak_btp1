<?php
// Security check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo "Akses ditolak.";
    return;
}
?>

<div class="container mx-auto p-4 md:p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                System Error Logs
            </h1>
            <p class="text-sm text-gray-500 mt-1" id="log-path-display">Memuat lokasi file...</p>
        </div>

        <div class="flex gap-2 mt-4 md:mt-0">
            <button onclick="loadLogs()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
            <button onclick="clearLogs()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 transition shadow-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Bersihkan Log
            </button>
        </div>
    </div>

    <!-- Container Logs -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Waktu</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Tipe</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pesan Error</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">File</th>
                    </tr>
                </thead>
                <tbody id="log-container" class="bg-white divide-y divide-gray-200">
                    <!-- Data akan dimuat via AJAX -->
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-gray-500 animate-pulse">
                            Sedang membaca file log...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const LOG_API = 'pages/development/ajax_log_viewer.php';

    function loadLogs() {
        const container = document.getElementById('log-container');
        const pathDisplay = document.getElementById('log-path-display');

        // State loading
        container.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-500">Memuat ulang data...</td></tr>';

        const formData = new FormData();
        formData.append('action', 'get_logs');

        fetch(LOG_API, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    container.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center text-red-500 font-bold">${data.message}</td></tr>`;
                    pathDisplay.innerText = "Lokasi tidak diketahui";
                    return;
                }

                pathDisplay.innerText = `Lokasi: ${data.path} | Ukuran: ${data.size}`;

                if (data.data.length === 0) {
                    container.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center text-green-600 font-medium">✨ Tidak ada error log. Sistem berjalan lancar!</td></tr>`;
                    return;
                }

                let html = '';
                data.data.forEach(log => {
                    if (log.raw) {
                        // Tampilan baris yang gagal di-parse (Raw Text)
                        html += `
                    <tr class="bg-gray-50 hover:bg-gray-100">
                        <td colspan="4" class="px-6 py-3 text-xs font-mono text-gray-600 break-all border-l-4 border-gray-300">
                            ${log.content}
                        </td>
                    </tr>`;
                    } else {
                        // Tampilan rapi
                        let badgeColor = 'bg-blue-100 text-blue-800 border-blue-200'; // Default Notice
                        let rowBorder = 'border-l-4 border-blue-400';

                        // Deteksi Fatal Error / Warning
                        if (log.type.toLowerCase().includes('fatal') || log.type.toLowerCase().includes('parse')) {
                            badgeColor = 'bg-red-100 text-red-800 border-red-200';
                            rowBorder = 'border-l-4 border-red-500';
                        } else if (log.type.toLowerCase().includes('warning') || log.type.toLowerCase().includes('deprecated')) {
                            badgeColor = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                            rowBorder = 'border-l-4 border-yellow-400';
                        }

                        html += `
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 align-top ${rowBorder}">
                            ${log.date}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap align-top">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border ${badgeColor}">
                                ${log.type.replace('PHP ', '')}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-800 font-mono align-top break-words">
                            ${log.message}
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500 font-mono align-top break-all">
                            <div class="font-bold text-gray-700">${log.file.split('/').pop()}</div>
                            <div class="text-gray-400 mt-1">Line: ${log.line}</div>
                            <div class="text-gray-300 text-[10px] mt-1 hover:text-gray-500 cursor-help" title="${log.file}">${log.file}</div>
                        </td>
                    </tr>
                `;
                    }
                });
                container.innerHTML = html;
            })
            .catch(err => {
                container.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center text-red-500">Gagal mengambil log: ${err}</td></tr>`;
            });
    }

    function clearLogs() {
        if (!confirm('Apakah Anda yakin ingin menghapus seluruh riwayat error log? Tindakan ini tidak dapat dibatalkan.')) return;

        const formData = new FormData();
        formData.append('action', 'clear_logs');

        fetch(LOG_API, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Log berhasil dibersihkan.');
                    loadLogs();
                } else {
                    alert('Gagal membersihkan log: ' + data.message);
                }
            });
    }

    // Load otomatis saat halaman dibuka
    document.addEventListener('DOMContentLoaded', loadLogs);
</script>