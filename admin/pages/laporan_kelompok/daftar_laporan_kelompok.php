<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Daftar Laporan PJP Kelompok</h2>
        <p class="text-sm text-gray-500 mt-1">Kelola dan isi laporan PJP untuk kelompok Anda pada setiap periode.</p>
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
                    <th class="p-4 font-semibold">Periode</th>
                    <th class="p-4 font-semibold">Batas Akhir</th>
                    <th class="p-4 font-semibold text-center">Status Laporan</th>
                    <th class="p-4 font-semibold text-right">Aksi</th>
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
    <div id="loadingStateMobileKel" class="text-center py-12 text-blue-400">
        <i class="fa-solid fa-circle-notch fa-spin text-3xl mb-3 block"></i>
        <p class="text-sm">Memuat data...</p>
    </div>
    <div id="containerKelompokMobileKel" class="space-y-4 hidden"></div>
    <div id="emptyStateMobileKel" class="hidden text-center py-16 text-gray-400">
        <i class="fa-regular fa-folder-open text-5xl mb-4 block"></i>
        <p class="font-medium">Belum ada data periode.</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDataPeriodeKelompok();
    });

    async function loadDataPeriodeKelompok() {
        try {
            const response = await fetch('pages/laporan_kelompok/ajax_daftar_laporan_kelompok.php?action=get_list');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            document.getElementById('loadingStateMobileKel').classList.add('hidden');

            if (result.status === 'success') {
                renderTableKelompok(result.data);
                renderCardsKelompok(result.data);
            } else {
                Swal.fire('Error dari Server!', result.message, 'error');
            }
        } catch (error) {
            document.getElementById('loadingStateMobileKel').classList.add('hidden');
            Swal.fire('Gagal Memuat Data!', error.message, 'error');
        }
    }

    // ── Helper: buat konten badge & tombol dari 1 item data ──
    function buildKelompokUI(item) {
        const isPastEndDate = new Date() > new Date(item.tanggal_selesai);

        let statusBadge = '';
        let actionBtn = '';

        if (!item.status_laporan) {
            statusBadge = `<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-bold">
                <i class="fa-solid fa-lock mr-1"></i> BELUM DIBUAT</span>`;
            actionBtn = isPastEndDate ?
                `<span class="text-xs text-red-400 italic">Menunggu akses dari Desa</span>` :
                `<span class="text-xs text-gray-400 italic">Periode belum berakhir</span>`;
        } else if (item.status_laporan === 'DRAFT') {
            statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold">
                <i class="fa-solid fa-pen mr-1"></i> DRAFT</span>`;
            actionBtn = `<button onclick="bukaFormLaporan(${item.periode_id})"
                class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg font-medium text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-file-pen mr-1"></i> Isi Laporan
            </button>`;
        } else if (item.status_laporan === 'FINAL') {
            statusBadge = `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold">
                <i class="fa-solid fa-clock mr-1"></i> MENUNGGU TTD</span>`;
            actionBtn = `<button onclick="bukaFormLaporan(${item.periode_id})"
                class="bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 px-4 py-2 rounded-lg font-medium text-xs transition-colors">
                <i class="fa-solid fa-eye mr-1"></i> Lihat Data
            </button>`;
        } else if (item.status_laporan === 'TTD_KETUA') {
            statusBadge = `<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold">
                <i class="fa-solid fa-check-double mr-1"></i> SELESAI</span>`;
            actionBtn = `<button onclick="bukaFormLaporan(${item.periode_id})"
                class="bg-green-50 text-green-700 hover:bg-green-100 border border-green-200 px-4 py-2 rounded-lg font-medium text-xs transition-colors">
                <i class="fa-solid fa-eye mr-1"></i> Lihat Data
            </button>`;
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
            } = buildKelompokUI(item);
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

    // ── Render CARDS (mobile) ──
    function renderCardsKelompok(data) {
        const container = document.getElementById('containerKelompokMobileKel');

        if (data.length === 0) {
            document.getElementById('emptyStateMobileKel').classList.remove('hidden');
            return;
        }

        container.classList.remove('hidden');
        container.innerHTML = '';

        data.forEach(item => {
            const {
                statusBadge,
                actionBtn
            } = buildKelompokUI(item);
            const card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow-sm border border-gray-100 p-5';
            card.innerHTML = `
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-semibold tracking-wider mb-1">Periode</p>
                        <h3 class="text-base font-bold text-gray-900">${item.nama_periode}</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            <i class="fa-regular fa-calendar mr-1"></i>${item.tanggal_akhir_format}
                        </p>
                    </div>
                    <div class="flex-shrink-0">${statusBadge}</div>
                </div>
                <div class="flex justify-end pt-3 border-t border-gray-100">${actionBtn}</div>
            `;
            container.appendChild(card);
        });
    }

    function bukaFormLaporan(periodeId) {
        window.location.href = `?page=laporan_kelompok/form_laporan_kelompok&periode_id=${periodeId}`;
    }
</script>