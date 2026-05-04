<!-- Ambil parameter periode_id dari URL secara dinamis nanti di aplikasimu -->
<script>
    const urlParams = new URLSearchParams(window.location.search);
    const CURRENT_PERIODE_ID = urlParams.get('periode_id') || 1; // Fallback untuk testing
</script>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <button onclick="history.back()" class="text-gray-500 hover:text-primary text-sm mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
        </button>
        <h2 class="text-2xl font-bold text-gray-900">Detail Progres Laporan PJP Kelompok</h2>
        <p class="text-sm text-gray-500 mt-1" id="infoPeriode">Memuat informasi periode...</p>
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
                    <th class="p-4 font-semibold whitespace-nowrap">Nama Kelompok</th>
                    <th class="p-4 font-semibold text-center whitespace-nowrap">Status Laporan</th>
                    <th class="p-4 font-semibold whitespace-nowrap">Terakhir Diupdate</th>
                    <th class="p-4 font-semibold text-right whitespace-nowrap">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyKelompok" class="text-sm divide-y divide-gray-100">
                <tr>
                    <td colspan="4" class="p-8 text-center text-blue-400">
                        <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block"></i>
                        Memuat data kelompok...
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
        <p class="text-sm">Memuat data kelompok...</p>
    </div>
    <div id="containerKelompokMobile" class="space-y-4 hidden"></div>
    <div id="emptyStateMobile" class="hidden text-center py-16 text-gray-400">
        <i class="fa-solid fa-users-slash text-5xl mb-4 block"></i>
        <p class="font-medium">Belum ada data kelompok.</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDetailKelompok();
    });

    function toUcwords(str) {
        if (!str) return '';
        return str
            .toLowerCase()
            .split(' ')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    async function loadDetailKelompok() {
        try {
            const response = await fetch(`pages/laporan_desa/ajax_detail_laporan_kelompok.php?action=get_detail&periode_id=${CURRENT_PERIODE_ID}`);
            const result = await response.json();

            // Sembunyikan loading mobile
            document.getElementById('loadingStateMobile').classList.add('hidden');

            if (result.status === 'success') {
                const data = result.data;
                document.getElementById('infoPeriode').innerHTML = `Periode: <strong>${data.periode.nama_periode}</strong> (Berakhir: ${data.periode.tgl_akhir})`;
                const statusDesa = data.status_laporan_desa || null;

                renderTableKelompok(data.laporan_kelompok, statusDesa);
                renderCardsKelompok(data.laporan_kelompok, statusDesa);
            } else {
                Swal.fire('Error!', result.message, 'error');
            }
        } catch (error) {
            document.getElementById('loadingStateMobile').classList.add('hidden');
            Swal.fire('Gagal!', 'Terjadi kesalahan saat memuat data detail.', 'error');
        }
    }

    // ── Helper: buat konten UI (Badge & Tombol) dari 1 item data ──
    function buildKelompokUI(item, statusLaporanDesa) {
        // Badge Status
        let statusBadge = '';
        let bgRow = '';
        if (item.status === 'DRAFT') {
            statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-pen mr-1"></i> DRAFT</span>`;
        } else if (item.status === 'FINAL') {
            statusBadge = `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-clock mr-1"></i> MENUNGGU TTD</span>`;
        } else if (item.status === 'TTD_KETUA') {
            statusBadge = `<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-check-double mr-1"></i> SELESAI</span>`;
            bgRow = '';
        }

        // Default ActionBtn Kosong (Hanya '-' jika belum ttd)
        let actionBtn = `<span class="text-xs text-gray-400 italic whitespace-nowrap">Belum Selesai</span>`;

        // Tombol Lihat Laporan HANYA ADA jika statusnya TTD_KETUA
        if (item.status === 'TTD_KETUA') {
            actionBtn = `
                <button onclick="window.location.href='?page=laporan_desa/lihat_laporan_kelompok&id=${item.id}'" class="bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors text-xs mr-1 whitespace-nowrap" title="Lihat Isi Laporan">
                    <i class="fa-solid fa-eye mr-1"></i> Lihat Data
                </button>
            `;
        } else if (item.status !== 'DRAFT') {
            // Tampilkan ikon gembok jika laporan desa sudah disahkan
            actionBtn = `
            <span class="text-xs text-gray-400 ml-2 font-medium whitespace-nowrap" title="Terkunci: Laporan Desa telah disahkan">
                <i class="fa-solid fa-lock"></i> Belum Disahkan
            </span>
        `;
        }

        return {
            statusBadge,
            bgRow,
            actionBtn
        };
    }

    // ── Render TABEL (desktop) ──
    function renderTableKelompok(listKelompok, statusLaporanDesa) {
        const tbody = document.getElementById('tableBodyKelompok');
        tbody.innerHTML = '';

        if (listKelompok.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-gray-500">Belum ada data kelompok.</td></tr>`;
            return;
        }

        listKelompok.forEach(item => {
            const {
                statusBadge,
                bgRow,
                actionBtn
            } = buildKelompokUI(item, statusLaporanDesa);

            const tr = document.createElement('tr');
            tr.className = `hover:bg-gray-50 transition-colors ${bgRow}`;
            tr.innerHTML = `
                <td class="p-4 font-bold text-gray-800 whitespace-nowrap">${toUcwords(item.nama_kelompok)}</td>
                <td class="p-4 text-center">${statusBadge}</td>
                <td class="p-4 text-gray-500 text-xs whitespace-nowrap">${item.tgl_update}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // ── Render CARDS (mobile) ──
    function renderCardsKelompok(listKelompok, statusLaporanDesa) {
        const container = document.getElementById('containerKelompokMobile');

        if (listKelompok.length === 0) {
            document.getElementById('emptyStateMobile').classList.remove('hidden');
            return;
        }

        container.classList.remove('hidden');
        container.innerHTML = '';

        listKelompok.forEach(item => {
            const {
                statusBadge,
                bgRow,
                actionBtn
            } = buildKelompokUI(item, statusLaporanDesa);

            const card = document.createElement('div');
            card.className = `bg-white rounded-xl shadow-sm border border-gray-100 p-5 ${bgRow}`;

            card.innerHTML = `
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-semibold tracking-wider mb-1">Kelompok</p>
                        <h3 class="text-base font-bold text-gray-900">${toUcwords(item.nama_kelompok)}</h3>
                    </div>
                    <div class="flex-shrink-0">${statusBadge}</div>
                </div>
                <div class="flex items-center gap-2 py-3 border-t border-b border-gray-100 mb-4">
                    <i class="fa-regular fa-clock text-gray-400 text-sm"></i>
                    <span class="text-sm text-gray-500 mr-1">Diupdate:</span>
                    <span class="text-sm font-medium text-gray-800">${item.tgl_update}</span>
                </div>
                <div class="flex justify-end">
                    ${actionBtn}
                </div>
            `;
            container.appendChild(card);
        });
    }
</script>