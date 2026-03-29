<!-- Ambil parameter periode_id dari URL secara dinamis nanti di aplikasimu -->
<script>
    // Mockup URL param: index.php?page=laporan_desa_detail&periode_id=1
    const urlParams = new URLSearchParams(window.location.search);
    const CURRENT_PERIODE_ID = urlParams.get('periode_id') || 1; // Fallback untuk testing
</script>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <button onclick="history.back()" class="text-gray-500 hover:text-primary text-sm mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
        </button>
        <h2 class="text-2xl font-bold text-gray-900">Detail Progres Laporan PJP</h2>
        <p class="text-sm text-gray-500 mt-1" id="infoPeriode">Memuat informasi periode...</p>
    </div>
    <div>
        <button id="btnBuatDesa" class="bg-gray-300 text-gray-500 cursor-not-allowed px-4 py-2 rounded-lg font-medium shadow-sm transition-colors" disabled>
            <i class="fa-solid fa-file-contract mr-1"></i> Buat Laporan Desa
        </button>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-sm text-gray-600">
                    <th class="p-4 font-semibold">Nama Kelompok</th>
                    <th class="p-4 font-semibold text-center">Status Laporan</th>
                    <th class="p-4 font-semibold">Terakhir Diupdate</th>
                    <th class="p-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyKelompok" class="text-sm divide-y divide-gray-100">
                <tr>
                    <td colspan="4" class="p-8 text-center text-gray-400">Memuat data kelompok...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDetailKelompok();
    });

    async function loadDetailKelompok() {
        try {
            const response = await fetch(`pages/laporan_desa/ajax_detail_laporan_desa.php?action=get_detail&periode_id=${CURRENT_PERIODE_ID}`);
            const result = await response.json();

            if (result.status === 'success') {
                const data = result.data;
                document.getElementById('infoPeriode').innerHTML = `Periode: <strong>${data.periode.nama_periode}</strong> (Berakhir: ${data.periode.tgl_akhir})`;

                renderTableKelompok(data.laporan_kelompok);

                // Logika Tombol Buat Laporan Desa
                const btnBuatDesa = document.getElementById('btnBuatDesa');
                if (data.status_laporan_desa) {
                    btnBuatDesa.className = "bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition-colors";
                    btnBuatDesa.innerHTML = '<i class="fa-solid fa-eye mr-1"></i> Lihat Laporan Desa';
                    btnBuatDesa.disabled = false;
                } else if (data.semua_kelompok_selesai) {
                    btnBuatDesa.className = "bg-primary hover:bg-teal-800 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition-colors";
                    btnBuatDesa.disabled = false;
                } else {
                    btnBuatDesa.innerHTML = '<i class="fa-solid fa-lock mr-1"></i> Menunggu Kelompok Selesai';
                }

            } else {
                Swal.fire('Error!', result.message, 'error');
            }
        } catch (error) {
            Swal.fire('Gagal!', 'Terjadi kesalahan saat memuat data detail.', 'error');
        }
    }

    function renderTableKelompok(listKelompok) {
        const tbody = document.getElementById('tableBodyKelompok');
        tbody.innerHTML = '';

        listKelompok.forEach(item => {
            // Badge Status
            let statusBadge = '';
            let bgRow = '';
            if (item.status === 'DRAFT') {
                statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-pen mr-1"></i> DRAFT</span>`;
            } else if (item.status === 'FINAL') {
                statusBadge = `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-clock mr-1"></i> MENUNGGU TTD</span>`;
            } else if (item.status === 'TTD_KETUA') {
                statusBadge = `<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold"><i class="fa-solid fa-check-double mr-1"></i> SELESAI</span>`;
                bgRow = 'bg-green-50/30';
            }

            // Default ActionBtn Kosong (Hanya '-' jika belum ttd)
            let actionBtn = `<span class="text-xs text-gray-400">-</span>`;

            // Tombol Lihat Laporan HANYA ADA jika statusnya TTD_KETUA
            if (item.status === 'TTD_KETUA') {
                actionBtn = `
                    <button onclick="window.location.href='?page=laporan_desa/lihat_laporan_kelompok&id=${item.id}'" class="bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors text-xs mr-1" title="Lihat Isi Laporan">
                        <i class="fa-solid fa-eye mr-1"></i> Lihat Data
                    </button>
                `;
            }

            // Tambahkan tombol TOLAK jika statusnya FINAL atau TTD_KETUA
            if (item.status !== 'DRAFT') {
                actionBtn += `
                    <button onclick="tolakLaporan(${item.id}, '${item.nama_kelompok}')" class="bg-red-50 text-red-700 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors text-xs" title="Tolak & Kembalikan ke Draft">
                        <i class="fa-solid fa-rotate-left"></i> Tolak
                    </button>
                `;
            }

            const tr = document.createElement('tr');
            tr.className = `hover:bg-gray-50 transition-colors ${bgRow}`;
            tr.innerHTML = `
                <td class="p-4 font-bold text-gray-800">${item.nama_kelompok}</td>
                <td class="p-4 text-center">${statusBadge}</td>
                <td class="p-4 text-gray-500 text-xs">${item.tgl_update}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function tolakLaporan(laporanId, namaKelompok) {
        Swal.fire({
            title: `Tolak Laporan ${namaKelompok}?`,
            text: "Status laporan akan dikembalikan menjadi DRAFT. Jika sudah ada TTD Ketua, TTD tersebut akan dihapus.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Tolak Laporan!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                formData.append('laporan_id', laporanId);

                try {
                    const response = await fetch('pages/laporan_desa/ajax_detail_laporan_desa.php?action=tolak_laporan', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();

                    if (res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success');
                        loadDetailKelompok(); // Refresh tabel
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error!', 'Terjadi kesalahan pada server.', 'error');
                }
            }
        });
    }
</script>