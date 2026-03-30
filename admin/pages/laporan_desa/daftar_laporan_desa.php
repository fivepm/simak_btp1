<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Pembuatan Laporan PJP Desa</h2>
        <p class="text-sm text-gray-500 mt-1">Buat rekapitulasi laporan tingkat desa. Syarat: Seluruh kelompok telah menyelesaikan laporan.</p>
    </div>
    <div class="bg-blue-50 text-blue-800 text-sm font-semibold px-4 py-2 rounded-lg border border-blue-100 flex items-center shadow-sm">
        <i class="fa-solid fa-building-flag mr-2"></i> Laporan Tingkat Desa
    </div>
</div>

<!-- Table Card -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-sm text-gray-600">
                    <th class="p-4 font-semibold">Periode</th>
                    <th class="p-4 font-semibold text-center">Syarat Kelompok</th>
                    <th class="p-4 font-semibold text-center">Status Laporan Desa</th>
                    <th class="p-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriodeDesa" class="text-sm divide-y divide-gray-100">
                <tr>
                    <td colspan="4" class="p-8 text-center text-gray-400"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block"></i> Memuat data...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDataPeriodeDesa();
    });

    async function loadDataPeriodeDesa() {
        try {
            // Menggunakan API yang SAMA persis dengan halaman pantau
            const response = await fetch('pages/laporan_desa/ajax_daftar_laporan_desa.php?action=get_list');
            const result = await response.json();
            if (result.status === 'success') {
                renderTableDesa(result.data);
            } else {
                Swal.fire('Error!', result.message, 'error');
            }
        } catch (error) {
            Swal.fire('Gagal!', 'Terjadi kesalahan komunikasi dengan server.', 'error');
        }
    }

    function renderTableDesa(data) {
        const tbody = document.getElementById('tableBodyPeriodeDesa');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-gray-500">Belum ada data periode.</td></tr>`;
            return;
        }

        data.forEach(item => {
            let actionBtn = '';

            // Logika Tombol Aksi Desa
            if (!item.is_generated) {
                actionBtn = `<span class="text-xs text-gray-400 italic">Menunggu akses kelompok</span>`;
            } else if (item.kelompok_selesai == item.total_kelompok) {
                // Jika 4/4 Kelompok sudah Selesai
                if (!item.status_desa || item.status_desa === 'DRAFT') {
                    actionBtn = `<button onclick="bukaFormDesa(${item.id})" class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors font-medium text-xs shadow-sm">
                                    <i class="fa-solid fa-file-pen mr-1"></i> Buat Laporan Desa
                                 </button>`;
                } else {
                    actionBtn = `<button onclick="bukaFormDesa(${item.id})" class="bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 px-4 py-2 rounded-lg transition-colors font-medium text-xs">
                                    <i class="fa-solid fa-eye mr-1"></i> Lihat Laporan Desa
                                 </button>`;
                }
            } else {
                // Jika belum 4/4 Selesai
                actionBtn = `<button disabled class="bg-gray-100 text-gray-400 border border-gray-200 px-4 py-2 rounded-lg font-medium text-xs cursor-not-allowed">
                                <i class="fa-solid fa-lock mr-1"></i> Terkunci
                             </button>`;
            }

            // Logika Badge Status Desa
            let statusBadge = `<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-minus"></i></span>`;
            if (item.status_desa === 'DRAFT') statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-pen mr-1"></i> DRAFT</span>`;
            else if (item.status_desa === 'FINAL') statusBadge = `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold animate-pulse"><i class="fa-solid fa-clock mr-1"></i> MENUNGGU TTD KETUA</span>`;
            else if (item.status_desa === 'TTD_KETUA') statusBadge = `<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-check-double mr-1"></i> SELESAI</span>`;

            // Render Indikator Syarat Kelompok
            let syaratBadge = '';
            if (item.is_generated) {
                const isLengkap = item.kelompok_selesai == item.total_kelompok;
                syaratBadge = `<div class="inline-flex items-center gap-2">
                                  <i class="fa-solid ${isLengkap ? 'fa-circle-check text-green-500' : 'fa-circle-exclamation text-yellow-500'}"></i>
                                  <span class="font-bold text-${isLengkap ? 'green' : 'yellow'}-600 text-sm">${item.kelompok_selesai} / ${item.total_kelompok} Kelompok</span>
                               </div>`;
            } else {
                syaratBadge = `<span class="text-gray-300">-</span>`;
            }

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            tr.innerHTML = `
                <td class="p-4 font-bold text-gray-800">${item.nama_periode}</td>
                <td class="p-4 text-center">${syaratBadge}</td>
                <td class="p-4 text-center">${item.is_generated && item.kelompok_selesai == item.total_kelompok ? statusBadge : '<span class="text-gray-300">-</span>'}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function bukaFormDesa(periodeId) {
        // Arahkan ke Form Laporan Desa yang sudah kita buat sebelumnya
        window.location.href = `?page=laporan_desa/form_laporan_desa&periode_id=${periodeId}`;
    }
</script>