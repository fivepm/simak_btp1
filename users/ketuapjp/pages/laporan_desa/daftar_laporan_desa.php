<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Persetujuan Laporan PJP Desa</h2>
        <p class="text-sm text-gray-500 mt-1">Halaman persetujuan dan tanda tangan laporan tingkat desa (Ketua PJP).</p>
    </div>
    <div class="bg-blue-50 text-blue-800 text-sm font-semibold px-4 py-2 rounded-lg border border-blue-100 flex items-center">
        <i class="fa-solid fa-user-tie mr-2"></i> Akses Ketua PJP
    </div>
</div>

<!-- Table Card -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-sm text-gray-600">
                    <th class="p-4 font-semibold">Periode</th>
                    <th class="p-4 font-semibold">Batas Akhir</th>
                    <th class="p-4 font-semibold text-center">Status Laporan</th>
                    <th class="p-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriodeKetuaDesa" class="text-sm divide-y divide-gray-100">
                <tr>
                    <td colspan="4" class="p-8 text-center text-gray-400">
                        <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2"></i>
                        <p>Memuat data...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Script Logika Frontend -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDataPeriodeKetuaDesa();
    });

    async function loadDataPeriodeKetuaDesa() {
        try {
            // Arahkan fetch ke file backend ketua yang baru
            const response = await fetch('pages/laporan_desa/ajax_daftar_laporan_desa.php?action=get_list');

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();

            if (result.status === 'success') {
                renderTableKetuaDesa(result.data);
            } else {
                Swal.fire('Error dari Server!', result.message, 'error');
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            Swal.fire('Gagal Memuat Data!', error.message, 'error');
        }
    }

    function renderTableKetuaDesa(data) {
        const tbody = document.getElementById('tableBodyPeriodeKetuaDesa');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-gray-500">Belum ada data periode.</td></tr>`;
            return;
        }

        data.forEach(item => {
            let statusBadge = '';
            let actionBtn = '';

            if (!item.status_desa) {
                // Belum dibuat sama sekali oleh Admin Desa
                statusBadge = `<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-hourglass-start mr-1"></i> BELUM DIBUAT</span>`;
                actionBtn = `<span class="text-xs text-gray-400 italic">Menunggu Admin Desa</span>`;

            } else if (item.status_desa === 'DRAFT') {
                // Sedang disusun oleh Admin Desa
                statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-pen mr-1"></i> SEDANG DISUSUN</span>`;
                actionBtn = `<span class="text-xs text-yellow-600 italic">Menunggu Admin Finalisasi</span>`;

            } else if (item.status_desa === 'FINAL') {
                // Siap di TTD oleh Ketua
                statusBadge = `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold animate-pulse"><i class="fa-solid fa-file-signature mr-1"></i> PERLU TTD</span>`;
                actionBtn = `
                    <button onclick="bukaReviewLaporan(${item.periode_id})" class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors font-medium text-xs shadow-sm">
                        <i class="fa-solid fa-file-contract mr-1"></i> Review & TTD
                    </button>
                `;

            } else if (item.status_desa === 'TTD_KETUA') {
                // Sudah Selesai
                statusBadge = `<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-check-double mr-1"></i> SELESAI</span>`;
                actionBtn = `
                    <button onclick="bukaReviewLaporan(${item.periode_id})" class="bg-teal-50 text-teal-700 hover:bg-teal-100 border border-teal-200 px-4 py-2 rounded-lg transition-colors font-medium text-xs">
                        <i class="fa-solid fa-eye mr-1"></i> Lihat Dokumen
                    </button>
                `;
            }

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            tr.innerHTML = `
                <td class="p-4 font-semibold text-gray-800">${item.nama_periode}</td>
                <td class="p-4 text-gray-600">${item.tanggal_akhir_format}</td>
                <td class="p-4 text-center">${statusBadge}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Fungsi untuk membuka halaman Review (untuk TTD atau sekadar melihat)
    function bukaReviewLaporan(periodeId) {
        // Ganti URL ini sesuai dengan format routing aplikasi kamu
        // Misalnya: index.php?page=ketua_review_laporan_desa&periode_id=X
        window.location.href = `index.php?page=ketua_review_laporan_desa&periode_id=${periodeId}`;
    }
</script>