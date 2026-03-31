<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Daftar Laporan PJP Kelompok</h2>
        <p class="text-sm text-gray-500 mt-1">Kelola dan isi laporan PJP untuk kelompok Anda pada setiap periode.</p>
    </div>
</div>

<!-- Table Card -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-blue-50 border-b border-gray-100 text-sm text-blue-600">
                    <th class="p-4 font-semibold">Periode</th>
                    <th class="p-4 font-semibold">Batas Akhir</th>
                    <th class="p-4 font-semibold text-center">Status Laporan</th>
                    <th class="p-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriodeKelompok" class="text-sm divide-y divide-gray-100">
                <tr>
                    <td colspan="4" class="p-8 text-center text-blue-400">
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
        loadDataPeriodeKelompok();
    });

    async function loadDataPeriodeKelompok() {
        try {
            // Sesuaikan URL ke file backend yang baru kita buat
            const response = await fetch('pages/laporan_kelompok/ajax_daftar_laporan_kelompok.php?action=get_list');

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();

            if (result.status === 'success') {
                renderTableKelompok(result.data);
            } else {
                Swal.fire('Error dari Server!', result.message, 'error');
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            Swal.fire('Gagal Memuat Data!', error.message, 'error');
        }
    }

    function renderTableKelompok(data) {
        const tbody = document.getElementById('tableBodyPeriodeKelompok');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-gray-500">Belum ada data periode.</td></tr>`;
            return;
        }

        data.forEach(item => {
            let statusBadge = '';
            let actionBtn = '';

            // Cek apakah tanggal batas akhir sudah terlewati
            let isPastEndDate = new Date() > new Date(item.tanggal_selesai);

            if (!item.status_laporan) {
                // Belum di-generate oleh Desa
                statusBadge = `<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-lock mr-1"></i> BELUM DIBUAT</span>`;

                if (isPastEndDate) {
                    actionBtn = `<span class="text-xs text-red-400 italic">Menunggu akses dari Desa</span>`;
                } else {
                    actionBtn = `<span class="text-xs text-gray-400 italic">Periode belum berakhir</span>`;
                }

            } else {
                // Sudah di-generate oleh Desa (Ada laporannya)
                if (item.status_laporan === 'DRAFT') {
                    statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-pen mr-1"></i> DRAFT</span>`;
                    actionBtn = `<button onclick="bukaFormLaporan(${item.periode_id})" class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors font-medium text-xs shadow-sm">
                                    <i class="fa-solid fa-file-pen mr-1"></i> Isi Laporan
                                 </button>`;
                } else if (item.status_laporan === 'FINAL') {
                    statusBadge = `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-clock mr-1"></i> MENUNGGU TTD</span>`;
                    actionBtn = `<button onclick="bukaFormLaporan(${item.periode_id})" class="bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 px-4 py-2 rounded-lg transition-colors font-medium text-xs">
                                    <i class="fa-solid fa-eye mr-1"></i> Lihat Data
                                 </button>`;
                } else if (item.status_laporan === 'TTD_KETUA') {
                    statusBadge = `<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-check-double mr-1"></i> SELESAI</span>`;
                    actionBtn = `<button onclick="bukaFormLaporan(${item.periode_id})" class="bg-green-50 text-green-700 hover:bg-green-100 border border-green-200 px-4 py-2 rounded-lg transition-colors font-medium text-xs">
                                    <i class="fa-solid fa-eye mr-1"></i> Lihat Data
                                 </button>`;
                }
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

    // Fungsi untuk membuka halaman Form Pengisian Laporan
    function bukaFormLaporan(periodeId) {
        // Ganti URL ini sesuai dengan format routing aplikasi kamu
        // Misalnya: index.php?page=form_laporan_kelompok&periode_id=X
        window.location.href = `?page=laporan_kelompok/form_laporan_kelompok&periode_id=${periodeId}`;
    }
</script>