<?php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo "Akses ditolak.";
    return;
}
?>

<div class="container mx-auto p-4 md:p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                </svg>
                Database Backup Manager
            </h1>
            <p class="text-sm text-gray-500 mt-1">Buat salinan database MySQL secara berkala.</p>
        </div>

        <div class="mt-4 md:mt-0">
            <button id="btn-create-backup" onclick="createBackup()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 transition shadow-lg font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Backup Database Sekarang
            </button>
        </div>
    </div>

    <!-- Alert Box (Dihapus karena diganti SweetAlert) -->
    <!-- <div id="backup-alert" class="hidden mb-4"></div> -->

    <!-- Container 1: Manajemen File (Download/Hapus) -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200 mb-8">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
            <h3 class="font-semibold text-gray-700">File Backup Tersedia</h3>
            <button onclick="loadBackups()" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh List
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama File</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ukuran</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Dibuat</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody id="backup-list-container" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-gray-500">Memuat data backup...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Container 2: Audit Trail Log (Hanya Baca) -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="font-semibold text-gray-700">Audit Trail: Riwayat Pembuatan Backup</h3>
        </div>
        <div class="p-4 bg-yellow-50 text-sm text-yellow-700 border-b border-yellow-100">
            <p>Catatan: Tabel ini menampilkan riwayat siapa yang melakukan backup. Data di tabel ini <strong>tidak akan hilang</strong> meskipun file fisik backup dihapus.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu Aktivitas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin Pelaksana</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File yang Dibuat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ukuran Awal</th>
                    </tr>
                </thead>
                <tbody id="log-list-container" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-gray-500">Memuat log aktivitas...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const BACKUP_API = 'pages/development/ajax_backup_db.php';
    const btnCreate = document.getElementById('btn-create-backup');

    // Fungsi wrapper untuk notifikasi SweetAlert
    function showSwal(title, text, icon = 'success') {
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            timer: 3000,
            showConfirmButton: false
        });
    }

    // 1. Load File List (Tabel Atas)
    function loadBackups() {
        const container = document.getElementById('backup-list-container');
        const formData = new FormData();
        formData.append('action', 'list_backups');

        fetch(BACKUP_API, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    container.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center text-red-500">${data.message}</td></tr>`;
                    return;
                }
                if (data.data.length === 0) {
                    container.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center text-gray-500 italic">Belum ada file backup fisik.</td></tr>`;
                    return;
                }
                let html = '';
                data.data.forEach(file => {
                    html += `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 font-mono">${file.filename}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${file.size}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${file.date}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button onclick="downloadBackup('${file.filename}')" class="text-blue-600 hover:text-blue-900 mr-4 font-bold">Download</button>
                        <button onclick="deleteBackup('${file.filename}')" class="text-red-600 hover:text-red-900 font-bold">Hapus</button>
                    </td>
                </tr>`;
                });
                container.innerHTML = html;
            });
    }

    // 2. Load Audit Logs (Tabel Bawah)
    function loadAuditLogs() {
        const container = document.getElementById('log-list-container');
        const formData = new FormData();
        formData.append('action', 'get_backup_logs');

        fetch(BACKUP_API, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                if (data.data.length === 0) {
                    container.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center text-gray-500 italic">Belum ada riwayat backup tercatat.</td></tr>`;
                    return;
                }
                let html = '';
                data.data.forEach(log => {
                    html += `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.date}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800">${log.admin_name}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600">${log.filename}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${log.size}</td>
                </tr>`;
                });
                container.innerHTML = html;
            });
    }

    function createBackup() {
        const originalText = btnCreate.innerHTML;
        btnCreate.disabled = true;
        btnCreate.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> Sedang memproses...`;

        const formData = new FormData();
        formData.append('action', 'create_backup');

        fetch(BACKUP_API, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSwal('Berhasil!', `Backup berhasil dibuat!`, 'success');
                    loadBackups(); // Refresh tabel file
                    loadAuditLogs(); // Refresh tabel log
                } else {
                    showSwal('Gagal!', `Terjadi kesalahan: ${data.message}`, 'error');
                }
            })
            .catch(err => showSwal('Error!', `Terjadi kesalahan server.`, 'error'))
            .finally(() => {
                btnCreate.disabled = false;
                btnCreate.innerHTML = originalText;
            });
    }

    function deleteBackup(filename) {
        // Ganti confirm biasa dengan SweetAlert Confirm
        Swal.fire({
            title: 'Hapus Backup?',
            text: `Anda yakin ingin menghapus file "${filename}"? Tindakan ini tidak dapat dibatalkan.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete_backup');
                formData.append('filename', filename);

                fetch(BACKUP_API, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showSwal('Terhapus!', 'File backup telah dihapus.', 'success');
                            loadBackups(); // Refresh list
                        } else {
                            showSwal('Gagal!', data.message, 'error');
                        }
                    })
                    .catch(err => showSwal('Error!', 'Gagal menghubungi server.', 'error'));
            }
        });
    }

    function downloadBackup(filename) {
        window.location.href = `${BACKUP_API}?action=download_backup&filename=${filename}`;
    }

    // Load otomatis saat halaman dibuka
    document.addEventListener('DOMContentLoaded', () => {
        loadBackups();
        loadAuditLogs();
    });
</script>