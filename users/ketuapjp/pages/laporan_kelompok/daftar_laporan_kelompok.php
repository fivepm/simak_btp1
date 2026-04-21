<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Persetujuan Laporan Kelompok</h2>
        <p class="text-sm text-gray-500 mt-1">Halaman persetujuan dan tanda tangan laporan tingkat kelompok (Ketua PJP).</p>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     DESKTOP: Tabel (tampil di lg ke atas)
════════════════════════════════════════════ -->
<div class="hidden lg:block bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-sm text-gray-600">
                    <th class="p-4 font-semibold whitespace-nowrap">Periode</th>
                    <th class="p-4 font-semibold whitespace-nowrap">Batas Akhir</th>
                    <th class="p-4 font-semibold text-center whitespace-nowrap">Status Laporan</th>
                    <th class="p-4 font-semibold text-right whitespace-nowrap">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriodeKetuaKelompok" class="text-sm divide-y divide-gray-100">
                <tr>
                    <td colspan="4" class="p-8 text-center text-gray-400">
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
    <div id="loadingStateMobileKetuaKelompok" class="text-center py-12 text-blue-400">
        <i class="fa-solid fa-circle-notch fa-spin text-3xl mb-3 block"></i>
        <p class="text-sm">Memuat data...</p>
    </div>
    <div id="containerPeriodeKetuaKelompokMobile" class="space-y-4 hidden"></div>
    <div id="emptyStateMobileKetuaKelompok" class="hidden text-center py-16 text-gray-400">
        <i class="fa-regular fa-folder-open text-5xl mb-4 block"></i>
        <p class="font-medium">Belum ada data periode.</p>
    </div>
</div>

<!-- Script Logika Frontend -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDataPeriodeKetuaKelompok();
    });

    async function loadDataPeriodeKetuaKelompok() {
        try {
            // Arahkan fetch ke file backend ketua kelompok
            const response = await fetch('pages/laporan_kelompok/ajax_daftar_laporan_kelompok.php?action=get_list');

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();

            // Sembunyikan loading state mobile
            document.getElementById('loadingStateMobileKetuaKelompok').classList.add('hidden');

            if (result.status === 'success') {
                renderTableKetuaKelompok(result.data);
                renderCardsKetuaKelompok(result.data);
            } else {
                Swal.fire('Error dari Server!', result.message, 'error');
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            document.getElementById('loadingStateMobileKetuaKelompok').classList.add('hidden');
            Swal.fire('Gagal Memuat Data!', error.message, 'error');
        }
    }

    // ── Helper: buat konten UI (Badge & Tombol) dari 1 item data ──
    function buildDaftarKetuaKelompokUI(item) {
        let statusBadge = '';
        let actionBtn = '';

        // Cek apakah laporan sudah di-generate oleh Desa
        if (!item.status_laporan) {
            statusBadge = `<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-hourglass-start mr-1"></i> BELUM DIBUAT</span>`;
            actionBtn = `<span class="text-xs text-gray-400 italic whitespace-nowrap">Menunggu akses dari Desa</span>`;

        } else if (item.status_laporan === 'DRAFT') {
            // Sedang disusun oleh Admin Kelompok
            statusBadge = `<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-pen mr-1"></i> SEDANG DISUSUN</span>`;
            actionBtn = `<span class="text-xs text-yellow-600 italic whitespace-nowrap">Menunggu Admin Kelompok</span>`;

        } else if (item.status_laporan === 'FINAL') {
            // Siap di TTD oleh Ketua Kelompok
            statusBadge = `<span class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-xs font-bold animate-pulse whitespace-nowrap"><i class="fa-solid fa-file-signature mr-1"></i> PERLU TTD</span>`;
            actionBtn = `
                <button onclick="bukaReviewLaporanKelompok(${item.periode_id})" class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors font-medium text-xs shadow-sm whitespace-nowrap">
                    <i class="fa-solid fa-file-contract mr-1"></i> Review & TTD
                </button>
            `;

        } else if (item.status_laporan === 'TTD_KETUA') {
            // Sudah Selesai / Ditandatangani
            statusBadge = `<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap"><i class="fa-solid fa-check-double mr-1"></i> SELESAI</span>`;
            actionBtn = `
                <button onclick="bukaReviewLaporanKelompok(${item.periode_id})" class="bg-teal-50 text-teal-700 hover:bg-teal-100 border border-teal-200 px-4 py-2 rounded-lg transition-colors font-medium text-xs whitespace-nowrap">
                    <i class="fa-solid fa-eye mr-1"></i> Lihat Dokumen
                </button>
            `;
        }

        return {
            statusBadge,
            actionBtn
        };
    }

    // ── Render TABEL (desktop) ──
    function renderTableKetuaKelompok(data) {
        const tbody = document.getElementById('tableBodyPeriodeKetuaKelompok');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-gray-500">Belum ada data periode.</td></tr>`;
            return;
        }

        data.forEach(item => {
            const {
                statusBadge,
                actionBtn
            } = buildDaftarKetuaKelompokUI(item);

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
    function renderCardsKetuaKelompok(data) {
        const container = document.getElementById('containerPeriodeKetuaKelompokMobile');

        if (data.length === 0) {
            document.getElementById('emptyStateMobileKetuaKelompok').classList.remove('hidden');
            return;
        }

        container.classList.remove('hidden');
        container.innerHTML = '';

        data.forEach(item => {
            const {
                statusBadge,
                actionBtn
            } = buildDaftarKetuaKelompokUI(item);

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
                    <span class="text-sm text-gray-500 mr-1">Batas Akhir:</span>
                    <span class="text-sm font-medium text-gray-800">${item.tanggal_akhir_format}</span>
                </div>
                <div class="flex justify-end">
                    ${actionBtn}
                </div>
            `;
            container.appendChild(card);
        });
    }

    // Fungsi untuk membuka halaman Review (untuk TTD atau sekadar melihat)
    function bukaReviewLaporanKelompok(periodeId) {
        // Ganti URL ini sesuai dengan format routing aplikasi kamu
        // Misalnya: index.php?page=ketua_review_laporan_kelompok&periode_id=X
        window.location.href = `?page=laporan_kelompok/review_laporan_kelompok&periode_id=${periodeId}`;
    }
</script>