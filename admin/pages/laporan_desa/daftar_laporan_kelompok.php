<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Daftar Laporan PJP Kelompok</h2>
        <p class="text-sm text-gray-500 mt-1">Kelola dan pantau progres laporan PJP Kelompok per periode.</p>
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
                    <th class="p-4 font-semibold">Tanggal Selesai</th>
                    <th class="p-4 font-semibold text-center">Progres Kelompok</th>
                    <th class="p-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBodyPeriode" class="text-sm divide-y divide-gray-100">
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
    <div id="containerKelompokMobile" class="space-y-4 hidden"></div>
    <div id="emptyStateMobile" class="hidden text-center py-16 text-gray-400">
        <i class="fa-regular fa-folder-open text-5xl mb-4 block"></i>
        <p class="font-medium">Belum ada data periode.</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadDataPeriode();
    });

    async function loadDataPeriode() {
        try {
            const response = await fetch('pages/laporan_desa/ajax_daftar_laporan_kelompok.php?action=get_list');
            const result = await response.json();

            document.getElementById('loadingStateMobile').classList.add('hidden');

            if (result.status === 'success') {
                renderTable(result.data);
                renderCards(result.data);
            } else {
                Swal.fire('Error!', result.message, 'error');
            }
        } catch (error) {
            document.getElementById('loadingStateMobile').classList.add('hidden');
            Swal.fire('Gagal Memuat Data!', error.message, 'error');
        }
    }

    // ── Helper: buat konten badge & tombol dari 1 item data ──
    function buildKelompokDesaUI(item) {
        const isPastEndDate = new Date() > new Date(item.tanggal_akhir);

        // Tombol Aksi
        let actionBtn = '';
        if (item.is_generated) {
            actionBtn = `<button onclick="bukaDetail(${item.id})"
                class="bg-blue-50 text-blue-700 hover:bg-blue-100 px-4 py-2 rounded-lg transition-colors font-medium text-xs border border-blue-200">
                <i class="fa-solid fa-eye mr-1"></i> Detail
            </button>`;
        } else if (isPastEndDate) {
            actionBtn = `<button onclick="generateLaporan(${item.id}, '${item.nama_periode}')"
                class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors font-medium text-xs shadow-sm">
                <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Buka Laporan
            </button>`;
        } else {
            actionBtn = `<span class="text-xs text-gray-400 italic"><i class="fa-solid fa-lock mr-1"></i> Belum Berakhir</span>`;
        }

        // Progres Kelompok
        let syaratBadge = '';
        if (item.is_generated) {
            const isLengkap = item.kelompok_selesai == item.total_kelompok;
            syaratBadge = `<div class="inline-flex items-center gap-2">
                <i class="fa-solid ${isLengkap ? 'fa-circle-check text-green-500' : 'fa-circle-exclamation text-yellow-500'}"></i>
                <span class="font-bold text-${isLengkap ? 'green' : 'yellow'}-600 text-sm">
                    ${item.kelompok_selesai} / ${item.total_kelompok} Kelompok
                </span></div>`;
        } else {
            syaratBadge = `<span class="text-gray-300 text-sm">-</span>`;
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
            } = buildKelompokDesaUI(item);
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            tr.innerHTML = `
                <td class="p-4 font-semibold text-gray-800">${item.nama_periode}</td>
                <td class="p-4 text-gray-600">${item.tanggal_akhir_format}</td>
                <td class="p-4 text-center">${syaratBadge}</td>
                <td class="p-4 text-right">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // ── Render CARDS (mobile) ──
    function renderCards(data) {
        const container = document.getElementById('containerKelompokMobile');

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
            } = buildKelompokDesaUI(item);
            const card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow-sm border border-gray-100 p-5';
            card.innerHTML = `
                <div class="mb-3">
                    <p class="text-xs text-gray-400 uppercase font-semibold tracking-wider mb-1">Periode</p>
                    <h3 class="text-base font-bold text-gray-900">${item.nama_periode}</h3>
                    <p class="text-sm text-gray-500 mt-0.5"><i class="fa-regular fa-calendar mr-1"></i>${item.tanggal_akhir_format}</p>
                </div>
                <div class="flex items-center gap-2 py-3 border-t border-b border-gray-100 mb-4">
                    <i class="fa-solid fa-users text-gray-400 text-sm"></i>
                    <span class="text-sm text-gray-500 mr-1">Progres:</span>
                    ${syaratBadge}
                </div>
                <div class="flex justify-end">${actionBtn}</div>
            `;
            container.appendChild(card);
        });
    }

    function generateLaporan(periodeId, namaPeriode) {
        Swal.fire({
            title: 'Generate Laporan?',
            text: `Sistem akan membuat Draft Laporan PJP untuk semua kelompok pada periode ${namaPeriode}. Aksi ini tidak dapat dibatalkan.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Generate!',
            cancelButtonText: 'Batal'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                try {
                    const formData = new FormData();
                    formData.append('periode_id', periodeId);
                    const response = await fetch('pages/laporan_desa/ajax_daftar_laporan_kelompok.php?action=generate_draft', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();
                    if (res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success');
                        loadDataPeriode();
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Gagal!', error.message, 'error');
                }
            }
        });
    }

    function bukaDetail(periodeId) {
        window.location.href = `?page=laporan_desa/detail_laporan_kelompok&periode_id=${periodeId}`;
    }
</script>