<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Ekspor Laporan PJP Desa</h2>
        <p class="text-sm text-gray-500 mt-1">Unduh dokumen laporan PJP Desa Anda yang sudah disahkan menjadi PDF.</p>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     DESKTOP: Tabel (tampil di lg ke atas)
════════════════════════════════════════════ -->
<div class="hidden lg:block bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-blue-50 border-b border-gray-100 text-sm text-blue-600">
                    <th class="p-4 font-semibold whitespace-nowrap">Periode</th>
                    <th class="p-4 font-semibold text-center whitespace-nowrap">Progress Laporan Kelompok</th>
                    <th class="p-4 font-semibold text-center whitespace-nowrap">Status Laporan Desa</th>
                    <th class="p-4 font-semibold text-right whitespace-nowrap">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriodeDesa" class="text-sm divide-y divide-gray-100">
                <tr>
                    <td colspan="4" class="p-8 text-center text-blue-400">
                        <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block"></i>
                        Memuat data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     MOBILE: Card list (tampil di bawah lg)
════════════════════════════════════════════ -->
<div class="block lg:hidden">
    <div id="loadingStateMobile" class="text-center py-12 text-blue-400">
        <i class="fa-solid fa-circle-notch fa-spin text-3xl mb-3 block"></i>
        <p class="text-sm">Memuat data...</p>
    </div>
    <div id="containerPeriodeDesaMobile" class="space-y-4 hidden"></div>
    <div id="emptyStateMobile" class="hidden text-center py-16 text-gray-400">
        <i class="fa-regular fa-folder-open text-5xl mb-4 block"></i>
        <p class="font-medium">Belum ada data periode.</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDataPeriodeDesa();
    });

    async function loadDataPeriodeDesa() {
        try {
            // Menggunakan API yang SAMA persis dengan halaman pantau
            const response = await fetch('pages/laporan_desa/ajax_export_laporan_desa.php?action=get_list');
            const result = await response.json();

            // Sembunyikan loading mobile
            document.getElementById('loadingStateMobile').classList.add('hidden');

            if (result.status === 'success') {
                renderTableDesa(result.data);
                renderCardsDesa(result.data);
            } else {
                Swal.fire('Error!', result.message, 'error');
            }
        } catch (error) {
            document.getElementById('loadingStateMobile').classList.add('hidden');
            Swal.fire('Gagal!', 'Terjadi kesalahan komunikasi dengan server.', 'error');
        }
    }

    async function exportPDF(laporanId) {
        if (!laporanId) {
            Swal.fire('Peringatan', 'Laporan belum disimpan atau tidak valid.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Membuat PDF...',
            text: 'Mohon tunggu sebentar, dokumen sedang di-render.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();
        formData.append('periode_id', laporanId); // Kirim ID laporan ke backend PHP

        try {
            // Panggil file PHP pembuat PDF
            const response = await fetch('pages/export/export_laporan_desa.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(errorText || 'Gagal mengekspor data.');
            }

            let filename = "Laporan_Desa.pdf";
            const disposition = response.headers.get('Content-Disposition');
            if (disposition && disposition.includes('filename="')) {
                filename = disposition.split('filename="')[1].split('"')[0];
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();

            a.remove();
            window.URL.revokeObjectURL(url);

            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'File PDF berhasil diunduh.',
                timer: 2000,
                showConfirmButton: false
            });

        } catch (error) {
            Swal.fire('Gagal!', error.message, 'error');
        }
    }

    // ── Helper: buat konten badge & tombol dari 1 item data ──
    function buildExportDesaUI(item) {
        // Status Kelompok (Syarat Laporan)
        let syaratBadge = '';
        if (item.is_generated) {
            const isLengkap = item.kelompok_selesai == item.total_kelompok;
            syaratBadge = `<div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                              <i class="fa-solid ${isLengkap ? 'fa-circle-check text-green-500' : 'fa-circle-exclamation text-yellow-500'}"></i>
                              <span class="font-bold text-${isLengkap ? 'green' : 'yellow'}-700 text-xs">${item.kelompok_selesai} / ${item.total_kelompok} Kelompok</span>
                           </div>`;
        } else {
            syaratBadge = `<span class="text-gray-300">-</span>`;
        }

        // Status Laporan Desa
        let statusBadge = `<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-minus"></i></span>`;
        if (item.status_desa === 'DRAFT')
            statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-pen mr-1"></i> DRAFT</span>`;
        else if (item.status_desa === 'FINAL')
            statusBadge = `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold animate-pulse whitespace-nowrap"><i class="fa-solid fa-clock mr-1"></i> MENUNGGU TTD</span>`;
        else if (item.status_desa === 'TTD_KETUA')
            statusBadge = `<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-check-double mr-1"></i> SELESAI</span>`;

        // Tombol Aksi
        let actionBtn = '';
        if (!item.is_generated) {
            actionBtn = `<span class="text-xs text-gray-400 italic">Laporan Belum Dibuka</span>`;
        } else {
            // Jika 4/4 Kelompok sudah Selesai
            if (!item.status_desa || item.status_desa === 'DRAFT' || item.status_desa === 'FINAL') {
                actionBtn = `<span class="text-xs text-gray-400 italic whitespace-nowrap" title="Hanya bisa diekspor jika sudah disahkan"><i class="fa-solid fa-file-pdf text-red-500/50 mr-1"></i> Ekspor PDF</span>`;
            } else if (item.status_desa === 'TTD_KETUA') {
                actionBtn = `<button type="button" onclick="exportPDF(${item.id})" class="bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 px-4 py-2 rounded-lg transition-colors font-medium text-xs shadow-sm whitespace-nowrap">
                                <i class="fa-solid fa-file-pdf mr-1"></i> Ekspor PDF
                            </button>`;
            }
        }

        return {
            syaratBadge,
            statusBadge,
            actionBtn
        };
    }

    // ── Render TABEL (desktop) ──
    function renderTableDesa(data) {
        const tbody = document.getElementById('tableBodyPeriodeDesa');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-gray-500">Belum ada data periode.</td></tr>`;
            return;
        }

        data.forEach(item => {
            const {
                syaratBadge,
                statusBadge,
                actionBtn
            } = buildExportDesaUI(item);

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            tr.innerHTML = `
                <td class="p-4 font-semibold text-gray-800 whitespace-nowrap">${item.nama_periode}</td>
                <td class="p-4 text-center">${syaratBadge}</td>
                <td class="p-4 text-center">${item.is_generated ? statusBadge : '<span class="text-gray-300">-</span>'}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // ── Render CARDS (mobile) ──
    function renderCardsDesa(data) {
        const container = document.getElementById('containerPeriodeDesaMobile');

        if (data.length === 0) {
            document.getElementById('emptyStateMobile').classList.remove('hidden');
            return;
        }

        container.classList.remove('hidden');
        container.innerHTML = '';

        data.forEach(item => {
            const {
                syaratBadge,
                statusBadge,
                actionBtn
            } = buildExportDesaUI(item);

            const card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow-sm border border-gray-100 p-5';

            // Pada Mobile, jika belum digenerate/belum selesai, status desa jangan ditampilkan membingungkan, tampilkan strip
            const displayStatusDesa = (item.is_generated) ? statusBadge : '<span class="text-gray-300 text-xs">–</span>';

            card.innerHTML = `
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-semibold tracking-wider mb-1">Periode</p>
                        <h3 class="text-base font-bold text-gray-900">${item.nama_periode}</h3>
                    </div>
                    <div class="flex-shrink-0">${displayStatusDesa}</div>
                </div>
                <div class="flex items-center gap-2 py-3 border-t border-b border-gray-100 mb-4">
                    <i class="fa-solid fa-list-check text-gray-400 text-sm"></i>
                    <span class="text-sm text-gray-500 mr-1">Progress:</span>
                    ${syaratBadge}
                </div>
                <div class="flex justify-end">${actionBtn}</div>
            `;
            container.appendChild(card);
        });
    }
</script>