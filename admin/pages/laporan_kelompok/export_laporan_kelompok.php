<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Ekspor Laporan PJP Kelompok</h2>
        <p class="text-sm text-gray-500 mt-1">Unduh dokumen laporan PJP kelompok Anda yang sudah disahkan menjadi PDF.</p>
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
                    <th class="p-4 font-semibold whitespace-nowrap">Tanggal Selesai</th>
                    <th class="p-4 font-semibold text-center whitespace-nowrap">Status Laporan</th>
                    <th class="p-4 font-semibold text-right whitespace-nowrap">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriodeKelompok" class="text-sm divide-y divide-gray-100">
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
    <div id="loadingStateMobileKelompok" class="text-center py-12 text-blue-400">
        <i class="fa-solid fa-circle-notch fa-spin text-3xl mb-3 block"></i>
        <p class="text-sm">Memuat data...</p>
    </div>
    <div id="containerPeriodeKelompokMobile" class="space-y-4 hidden"></div>
    <div id="emptyStateMobileKelompok" class="hidden text-center py-16 text-gray-400">
        <i class="fa-regular fa-folder-open text-5xl mb-4 block"></i>
        <p class="font-medium">Belum ada data periode.</p>
    </div>
</div>

<!-- Script Logika Frontend -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDataPeriodeKelompok();
    });

    async function loadDataPeriodeKelompok() {
        try {
            // Sesuaikan URL ke file backend
            const response = await fetch('pages/laporan_kelompok/ajax_export_laporan_kelompok.php?action=get_list');

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();

            // Sembunyikan loading mobile
            document.getElementById('loadingStateMobileKelompok').classList.add('hidden');

            if (result.status === 'success') {
                renderTableKelompok(result.data);
                renderCardsKelompok(result.data);
            } else {
                Swal.fire('Error dari Server!', result.message, 'error');
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            document.getElementById('loadingStateMobileKelompok').classList.add('hidden');
            Swal.fire('Gagal Memuat Data!', error.message, 'error');
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
        formData.append('laporan_id', laporanId); // Kirim ID laporan ke backend PHP

        try {
            // Panggil file PHP pembuat PDF
            const response = await fetch('pages/export/export_laporan_kelompok.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(errorText || 'Gagal mengekspor data.');
            }

            let filename = "Laporan_Kelompok.pdf";
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

    // ── Helper: buat konten UI (Badge & Tombol) dari 1 item data ──
    function buildExportKelompokUI(item) {
        let statusBadge = '';
        let actionBtn = '';
        let isPastEndDate = new Date() > new Date(item.tanggal_selesai);

        if (!item.status_laporan) {
            statusBadge = `<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-lock mr-1"></i> BELUM DIBUAT</span>`;
            actionBtn = isPastEndDate ?
                `<span class="text-xs text-red-400 italic">Menunggu akses dari Desa</span>` :
                `<span class="text-xs text-gray-400 italic">Periode belum berakhir</span>`;
        } else {
            if (item.status_laporan === 'DRAFT') {
                statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-pen mr-1"></i> DRAFT</span>`;
                actionBtn = `<span class="text-xs text-gray-400 italic whitespace-nowrap" title="Hanya bisa diekspor jika sudah disahkan">Selesaikan Laporan</span>`;
            } else if (item.status_laporan === 'FINAL') {
                statusBadge = `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-clock mr-1"></i> MENUNGGU TTD</span>`;
                actionBtn = `<span class="text-xs text-gray-400 italic whitespace-nowrap" title="Hanya bisa diekspor jika sudah disahkan">Menunggu Acc Ketua PJP</span>`;
            } else if (item.status_laporan === 'TTD_KETUA') {
                statusBadge = `<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-check-double mr-1"></i> SELESAI</span>`;

                // Tombol Ekspor Aktif
                actionBtn = `<button type="button" onclick="exportPDF(${item.laporan_id})" class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 font-medium px-4 py-2 rounded-lg shadow-sm transition-colors whitespace-nowrap">
                                <i class="fa-solid fa-file-pdf mr-1"></i> Ekspor PDF
                            </button>`;
            }
        }

        return {
            statusBadge,
            actionBtn
        };
    }

    // ── Render TABEL (desktop) ──
    function renderTableKelompok(data) {
        const tbody = document.getElementById('tableBodyPeriodeKelompok');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-gray-500">Belum ada data periode.</td></tr>`;
            return;
        }

        data.forEach(item => {
            const {
                statusBadge,
                actionBtn
            } = buildExportKelompokUI(item);

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            tr.innerHTML = `
                <td class="p-4 font-semibold text-gray-800 whitespace-nowrap">${item.nama_periode}</td>
                <td class="p-4 text-gray-600 whitespace-nowrap">${item.tanggal_akhir_format}</td>
                <td class="p-4 text-center">${statusBadge}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // ── Render CARDS (mobile) ──
    function renderCardsKelompok(data) {
        const container = document.getElementById('containerPeriodeKelompokMobile');

        if (data.length === 0) {
            document.getElementById('emptyStateMobileKelompok').classList.remove('hidden');
            return;
        }

        container.classList.remove('hidden');
        container.innerHTML = '';

        data.forEach(item => {
            const {
                statusBadge,
                actionBtn
            } = buildExportKelompokUI(item);

            const card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow-sm border border-gray-100 p-5';

            card.innerHTML = `
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-semibold tracking-wider mb-1">Periode</p>
                        <h3 class="text-base font-bold text-gray-900">${item.nama_periode}</h3>
                    </div>
                    <div class="flex-shrink-0">${statusBadge}</div>
                </div>
                <div class="flex items-center gap-2 py-3 border-t border-b border-gray-100 mb-4">
                    <i class="fa-regular fa-calendar-check text-gray-400 text-sm"></i>
                    <span class="text-sm text-gray-500 mr-1">Tgl Selesai:</span>
                    <span class="text-sm font-medium text-gray-800">${item.tanggal_akhir_format}</span>
                </div>
                <div class="flex justify-end">
                    ${actionBtn}
                </div>
            `;
            container.appendChild(card);
        });
    }
</script>