<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Daftar Laporan PJP</h2>
        <p class="text-sm text-gray-500 mt-1">Kelola dan pantau progres pelaporan PJP per periode.</p>
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
                    <th class="p-4 font-semibold text-center">Progres Kelompok</th>
                    <th class="p-4 font-semibold text-center">Status Laporan Desa</th>
                    <th class="p-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriode" class="text-sm divide-y divide-gray-100">
                <!-- Data akan di-render oleh JS -->
                <tr>
                    <td colspan="5" class="p-8 text-center text-gray-400">
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
        loadDataPeriode();
    });

    // Fungsi untuk mengambil data dari Backend
    async function loadDataPeriode() {
        try {
            // Ubah path API sesuai struktur folder aslimu
            const response = await fetch('pages/laporan_desa/ajax_daftar_laporan_desa.php?action=get_list');
            const result = await response.json();

            if (result.status === 'success') {
                renderTable(result.data);
            } else {
                Swal.fire('Error!', result.message, 'error');
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            Swal.fire('Gagal Memuat Data!', error.message, 'error');
        }
    }

    // Fungsi untuk merender tabel
    function renderTable(data) {
        const tbody = document.getElementById('tableBodyPeriode');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-gray-500">Belum ada data periode.</td></tr>`;
            return;
        }

        data.forEach(item => {
            // Logika untuk tombol Aksi
            let actionBtn = '';
            let isPastEndDate = new Date() > new Date(item.tanggal_akhir);

            if (item.is_generated) {
                // Tombol Detail (Biru Muda)
                actionBtn = `<button onclick="bukaDetail(${item.id})" class="bg-blue-50 text-blue-700 hover:bg-blue-100 px-4 py-2 rounded-lg transition-colors font-medium text-xs border border-blue-200">
                        <i class="fa-solid fa-eye mr-1"></i> Detail
                     </button>`;
            } else if (isPastEndDate) {
                // Tombol Generate (Biru Tua/Solid)
                actionBtn = `<button onclick="generateLaporan(${item.id}, '${item.nama_periode}')" class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors font-medium text-xs shadow-sm">
                        <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Generate Laporan
                     </button>`;
            } else {
                actionBtn = `<span class="text-xs text-gray-400 italic"><i class="fa-solid fa-lock mr-1"></i> Belum Berakhir</span>`;
            }

            // Badge Status Desa
            let statusBadge = `<span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold">BELUM DIBUAT</span>`;
            if (item.status_desa === 'DRAFT') statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold">DRAFT</span>`;
            else if (item.status_desa === 'FINAL') statusBadge = `<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-bold">FINAL</span>`;
            else if (item.status_desa === 'TTD_KETUA') statusBadge = `<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">SELESAI (TTD)</span>`;

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            tr.innerHTML = `
                <td class="p-4 font-semibold text-gray-800">${item.nama_periode}</td>
                <td class="p-4 text-gray-600">${item.tanggal_akhir_format}</td>
                <td class="p-4 text-center">
                    ${item.is_generated ? `<span class="font-bold text-gray-800">${item.kelompok_selesai}</span> / ${item.total_kelompok} Kelompok TTD` : '-'}
                </td>
                <td class="p-4 text-center">${item.is_generated ? statusBadge : '-'}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Fungsi untuk men-trigger pembuatan Draft (Generate)
    function generateLaporan(periodeId, namaPeriode) {
        Swal.fire({
            title: 'Generate Laporan?',
            text: `Sistem akan membuat Draft Laporan PJP untuk semua kelompok pada periode ${namaPeriode}. Aksi ini tidak dapat dibatalkan.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0f766e',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Generate!',
            cancelButtonText: 'Batal'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                try {
                    const formData = new FormData();
                    formData.append('periode_id', periodeId);

                    const response = await fetch('pages/laporan_desa/ajax_daftar_laporan_desa.php?action=generate_draft', {
                        method: 'POST',
                        body: formData
                    });

                    const res = await response.json();

                    if (res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success');
                        loadDataPeriode(); // Refresh tabel
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Gagal Memuat Data!', error.message, 'error');
                }
            }
        });
    }

    // Navigasi ke halaman detail periode
    function bukaDetail(periodeId) {
        // Asumsi struktur URL sistemmu menggunakan parameter 'page'
        window.location.href = `?page=laporan_desa/detail_laporan_desa&periode_id=${periodeId}`;
    }
</script>