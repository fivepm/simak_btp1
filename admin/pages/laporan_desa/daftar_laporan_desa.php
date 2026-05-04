<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Daftar Laporan PJP Desa</h2>
        <p class="text-sm text-gray-500 mt-1">Buat rekapitulasi laporan tingkat desa. Admin dapat mengakses laporan ini kapan saja.</p>
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
                    <th class="p-4 font-semibold text-center">Status Kelompok</th>
                    <th class="p-4 font-semibold text-center">Status Laporan Desa</th>
                    <th class="p-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriodeDesa" class="text-sm divide-y divide-gray-100">
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
    <div id="loadingStateMobile" class="text-center py-12 text-blue-400">
        <i class="fa-solid fa-circle-notch fa-spin text-3xl mb-3 block"></i>
        <p class="text-sm">Memuat data...</p>
    </div>
    <div id="containerPeriodeDesaMobile" class="space-y-4 hidden"></div>
    <div id="emptyStateMobile" class="hidden text-center py-16 text-gray-400">
        <i class="fa-regular fa-folder-open text-5xl mb-4 block"></i>
        <p class="font-medium">Belum ada data periode.</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDataPeriodeDesa();
    });

    async function loadDataPeriodeDesa() {
        try {
            const response = await fetch('pages/laporan_desa/ajax_daftar_laporan_desa.php?action=get_list');
            const result = await response.json();

            // Sembunyikan loading mobile
            document.getElementById('loadingStateMobile').classList.add('hidden');

            if (result.status === 'success') {
                renderTableDesa(result.data);
                renderCardsDesa(result.data);
            } else {
                Swal.fire('Error!', result.message, 'error');
            }
        } catch (error) {
            document.getElementById('loadingStateMobile').classList.add('hidden');
            Swal.fire('Gagal!', 'Terjadi kesalahan komunikasi dengan server.', 'error');
        }
    }

    // ── Helper: buat konten badge & tombol dari 1 item data ──
    function buildDesaUI(item) {
        // Status Kelompok
        let syaratBadge = '';
        if (item.is_generated) {
            const isLengkap = item.kelompok_selesai == item.total_kelompok;
            syaratBadge = `<div class="inline-flex items-center gap-1.5">
                <i class="fa-solid ${isLengkap ? 'fa-circle-check text-green-500' : 'fa-clock text-yellow-500'}"></i>
                <span class="font-semibold text-${isLengkap ? 'green' : 'yellow'}-600 text-sm">
                    ${item.kelompok_selesai}/${item.total_kelompok} Selesai
                </span></div>`;
        } else {
            syaratBadge = `<span class="text-gray-300 text-sm">-</span>`;
        }

        // Status Laporan Desa
        let statusBadge = `<span class="bg-gray-100 text-gray-500 text-xs font-bold px-2.5 py-1 rounded-full">–</span>`;
        if (item.status_desa === 'DRAFT')
            statusBadge = `<span class="bg-yellow-100 text-yellow-800 text-xs font-bold px-2.5 py-1 rounded-full"><i class="fa-solid fa-pen mr-1"></i>DRAFT</span>`;
        else if (item.status_desa === 'FINAL')
            statusBadge = `<span class="bg-blue-100 text-blue-800 text-xs font-bold px-2.5 py-1 rounded-full animate-pulse"><i class="fa-solid fa-clock mr-1"></i>MENUNGGU TTD</span>`;
        else if (item.status_desa === 'TTD_KETUA')
            statusBadge = `<span class="bg-green-100 text-green-800 text-xs font-bold px-2.5 py-1 rounded-full"><i class="fa-solid fa-check-double mr-1"></i>SELESAI</span>`;

        // Tombol Aksi
        let actionBtn = '';
        if (!item.is_generated) {
            actionBtn = `<span class="text-xs text-gray-400 italic"><i class="fa-solid fa-lock mr-1"></i>Laporan kelompok belum dibuka</span>`;
        } else if (!item.status_desa) {
            actionBtn = `<button onclick="generateLaporanDesa(${item.id}, '${item.nama_periode}')"
                class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg font-medium text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Buat Laporan Desa
            </button>`;
        } else if (item.status_desa === 'DRAFT') {
            actionBtn = `<button onclick="bukaFormDesa(${item.id})"
                class="bg-yellow-500 text-white hover:bg-yellow-600 px-4 py-2 rounded-lg font-medium text-xs shadow-sm transition-colors">
                <i class="fa-solid fa-file-pen mr-1"></i> Edit Laporan Desa
            </button>`;
        } else {
            actionBtn = `<button onclick="bukaFormDesa(${item.id})"
                class="bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 px-4 py-2 rounded-lg font-medium text-xs transition-colors">
                <i class="fa-solid fa-eye mr-1"></i> Lihat Laporan Desa
            </button>`;
        }

        return {
            syaratBadge,
            statusBadge,
            actionBtn
        };
    }

    // ── Render TABEL (desktop) ──
    function renderTableDesa(data) {
        const tbody = document.getElementById('tableBodyPeriodeDesa');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-gray-500">Belum ada data periode.</td></tr>`;
            return;
        }

        data.forEach(item => {
            const {
                syaratBadge,
                statusBadge,
                actionBtn
            } = buildDesaUI(item);
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            tr.innerHTML = `
                <td class="p-4 font-bold text-gray-800">${item.nama_periode}</td>
                <td class="p-4 text-center">${syaratBadge}</td>
                <td class="p-4 text-center">${item.is_generated ? statusBadge : '<span class="text-gray-300">-</span>'}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // ── Render CARDS (mobile) ──
    function renderCardsDesa(data) {
        const container = document.getElementById('containerPeriodeDesaMobile');

        if (data.length === 0) {
            document.getElementById('emptyStateMobile').classList.remove('hidden');
            return;
        }

        container.classList.remove('hidden');
        container.innerHTML = '';

        data.forEach(item => {
            const {
                syaratBadge,
                statusBadge,
                actionBtn
            } = buildDesaUI(item);
            const card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow-sm border border-gray-100 p-5';
            card.innerHTML = `
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-semibold tracking-wider mb-1">Periode</p>
                        <h3 class="text-base font-bold text-gray-900">${item.nama_periode}</h3>
                    </div>
                    <div class="flex-shrink-0">${item.is_generated ? statusBadge : '<span class="text-gray-300 text-xs">–</span>'}</div>
                </div>
                <div class="flex items-center gap-2 py-3 border-t border-b border-gray-100 mb-4">
                    <i class="fa-solid fa-users text-gray-400 text-sm"></i>
                    <span class="text-sm text-gray-500 mr-1">Kelompok:</span>
                    ${syaratBadge}
                </div>
                <div class="flex justify-end">${actionBtn}</div>
            `;
            container.appendChild(card);
        });
    }

    async function generateLaporanDesa(periodeId, namaPeriode) {
        const conf = await Swal.fire({
            title: 'Buat Laporan Desa?',
            text: `Sistem akan membuat draft Laporan PJP Desa untuk periode "${namaPeriode}" berdasarkan data laporan kelompok yang ada saat ini.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Buat Sekarang!',
            cancelButtonText: 'Batal'
        });
        if (!conf.isConfirmed) return;

        Swal.fire({
            title: 'Membuat laporan...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
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
                await Swal.fire({
                    title: 'Berhasil!',
                    text: res.message,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
                bukaFormDesa(periodeId);
            } else {
                Swal.fire('Gagal!', res.message, 'error');
            }
        } catch {
            Swal.fire('Error!', 'Terjadi kesalahan komunikasi dengan server.', 'error');
        }
    }

    function bukaFormDesa(periodeId) {
        window.location.href = `?page=laporan_desa/form_laporan_desa&periode_id=${periodeId}`;
    }
</script>