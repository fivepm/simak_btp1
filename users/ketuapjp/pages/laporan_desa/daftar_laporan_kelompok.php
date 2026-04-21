<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Daftar Laporan PJP Kelompok</h2>
        <p class="text-sm text-gray-500 mt-1">Pantau progres Laporan PJP Kelompok per periode.</p>
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
                    <th class="p-4 font-semibold text-center whitespace-nowrap">Progres Kelompok</th>
                    <th class="p-4 font-semibold text-right whitespace-nowrap">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriode" class="text-sm divide-y divide-gray-100">
                <!-- Data akan di-render oleh JS -->
                <tr>
                    <td colspan="4" class="p-8 text-center text-blue-400">
                        <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block"></i>
                        <p>Memuat data...</p>
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
    <div id="containerPeriodeMobile" class="space-y-4 hidden"></div>
    <div id="emptyStateMobile" class="hidden text-center py-16 text-gray-400">
        <i class="fa-regular fa-folder-open text-5xl mb-4 block"></i>
        <p class="font-medium">Belum ada data periode.</p>
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
            const response = await fetch('pages/laporan_desa/ajax_daftar_laporan_kelompok.php?action=get_list');
            const result = await response.json();

            // Sembunyikan loading state mobile
            document.getElementById('loadingStateMobile').classList.add('hidden');

            if (result.status === 'success') {
                renderTable(result.data);
                renderCards(result.data);
            } else {
                Swal.fire('Error!', result.message, 'error');
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            document.getElementById('loadingStateMobile').classList.add('hidden');
            Swal.fire('Gagal Memuat Data!', error.message, 'error');
        }
    }

    // ── Helper: buat konten UI (Badge & Tombol) dari 1 item data ──
    function buildDaftarKelompokUI(item) {
        let actionBtn = '';
        let isPastEndDate = new Date() > new Date(item.tanggal_akhir);

        // Logika Tombol Aksi
        if (item.is_generated) {
            actionBtn = `<button onclick="bukaDetail(${item.id})" class="bg-blue-50 text-blue-700 hover:bg-blue-100 px-4 py-2 rounded-lg transition-colors font-medium text-xs border border-blue-200 whitespace-nowrap">
                    <i class="fa-solid fa-eye mr-1"></i> Detail
                 </button>`;
        } else if (isPastEndDate) {
            actionBtn = `<span class="text-xs text-gray-400 italic whitespace-nowrap"><i class="fa-solid fa-lock mr-1"></i> Belum Dibuka</span>`;
        } else {
            actionBtn = `<span class="text-xs text-gray-400 italic whitespace-nowrap"><i class="fa-solid fa-lock mr-1"></i> Belum Berakhir</span>`;
        }

        // Render Indikator Syarat Kelompok
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

        return {
            actionBtn,
            syaratBadge
        };
    }

    // ── Render TABEL (desktop) ──
    function renderTable(data) {
        const tbody = document.getElementById('tableBodyPeriode');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-gray-500">Belum ada data periode.</td></tr>`;
            return;
        }

        data.forEach(item => {
            const {
                actionBtn,
                syaratBadge
            } = buildDaftarKelompokUI(item);

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            tr.innerHTML = `
                <td class="p-4 font-semibold text-gray-800 whitespace-nowrap">${item.nama_periode}</td>
                <td class="p-4 text-gray-600 whitespace-nowrap">${item.tanggal_akhir_format}</td>
                <td class="p-4 text-center">${syaratBadge}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // ── Render CARDS (mobile) ──
    function renderCards(data) {
        const container = document.getElementById('containerPeriodeMobile');

        if (data.length === 0) {
            document.getElementById('emptyStateMobile').classList.remove('hidden');
            return;
        }

        container.classList.remove('hidden');
        container.innerHTML = '';

        data.forEach(item => {
            const {
                actionBtn,
                syaratBadge
            } = buildDaftarKelompokUI(item);

            const card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow-sm border border-gray-100 p-5';

            card.innerHTML = `
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-semibold tracking-wider mb-1">Periode</p>
                        <h3 class="text-base font-bold text-gray-900">${item.nama_periode}</h3>
                    </div>
                    <div class="flex-shrink-0">${syaratBadge}</div>
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

    // Navigasi ke halaman detail periode
    function bukaDetail(periodeId) {
        // Asumsi struktur URL sistemmu menggunakan parameter 'page'
        window.location.href = `?page=laporan_desa/detail_laporan_kelompok&periode_id=${periodeId}`;
    }
</script>