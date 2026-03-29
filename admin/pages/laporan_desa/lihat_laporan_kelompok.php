<!-- Ambil parameter id laporan dari URL -->
<script>
    const urlParams = new URLSearchParams(window.location.search);
    const LAPORAN_ID = urlParams.get('id');

    if (!LAPORAN_ID) {
        Swal.fire('Error!', 'ID Laporan tidak ditemukan.', 'error').then(() => {
            history.back();
        });
    }
</script>

<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <button onclick="history.back()" class="text-gray-500 hover:text-blue-600 text-sm mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Detail Desa
        </button>
        <h2 class="text-2xl font-bold text-gray-900" id="titleHalaman">Lihat Laporan Kelompok</h2>
        <p class="text-sm text-gray-500 mt-1" id="infoPeriodeKelompok">Memuat informasi...</p>
    </div>
    <div id="statusBadge" class="bg-gray-100 text-gray-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center">
        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat...
    </div>
</div>

<div id="containerLaporan" class="space-y-6 hidden">

    <!-- Pemberitahuan Read-Only -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fa-solid fa-circle-info text-blue-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    Ini adalah tampilan <strong>Read-Only</strong> (hanya baca). Data di bawah ini merupakan *snapshot* dari laporan kelompok yang sudah ditandatangani.
                </p>
            </div>
        </div>
    </div>

    <!-- Grid 2 Kolom untuk Kepengurusan & Checklist -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Card 1: Data Kepengurusan -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-800"><i class="fa-solid fa-sitemap text-blue-600 mr-2"></i> Data Kepengurusan Saat Laporan Dibuat</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 text-sm" id="containerKepengurusan">
                    <!-- Dirender oleh JS -->
                </div>
            </div>
        </div>

        <!-- Card 2: Checklist Musyawarah -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-800"><i class="fa-solid fa-list-check text-blue-600 mr-2"></i> Checklist Musyawarah</h3>
            </div>
            <div class="p-6 space-y-4">
                <label class="flex items-center p-3 border rounded-lg bg-gray-50 cursor-not-allowed">
                    <input type="checkbox" id="checkMusyawarahPjp" disabled class="w-5 h-5 text-blue-600 rounded border-gray-300">
                    <span class="ml-3 font-medium text-gray-600">Musyawarah PJP Kelompok Telah Dilaksanakan</span>
                </label>
                <label class="flex items-center p-3 border rounded-lg bg-gray-50 cursor-not-allowed">
                    <input type="checkbox" id="checkMusyawarahUnsur" disabled class="w-5 h-5 text-blue-600 rounded border-gray-300">
                    <span class="ml-3 font-medium text-gray-600">Musyawarah Lima Unsur Telah Dilaksanakan</span>
                </label>
            </div>
        </div>

    </div>

    <!-- Card 3: Detail Per Kelas -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-800"><i class="fa-solid fa-chalkboard-user text-blue-600 mr-2"></i> Detail Per Kelas</h3>
        </div>

        <div class="p-6 space-y-6" id="containerKelas">
            <div class="text-center py-4 text-gray-400"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block"></i> Memuat Data Kelas...</div>
        </div>
    </div>

    <!-- Card 4: Permasalahan -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-800"><i class="fa-solid fa-triangle-exclamation text-red-500 mr-2"></i> Permasalahan yang Dihadapi</h3>
        </div>
        <div class="p-6">
            <div id="containerPermasalahan" class="space-y-3">
                <!-- Di-render oleh JS -->
            </div>
            <div id="emptyPermasalahan" class="hidden text-center py-6 text-gray-400 text-sm">
                <i class="fa-regular fa-folder-open text-3xl mb-2 block"></i>
                Tidak ada permasalahan yang dicatat oleh kelompok.
            </div>
        </div>
    </div>

</div>

<!-- JS Logic Form -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (LAPORAN_ID) {
            loadDataLaporan();
        }
    });

    async function loadDataLaporan() {
        try {
            const response = await fetch(`pages/laporan_desa/ajax_lihat_laporan_kelompok.php?action=get_laporan_readonly&id=${LAPORAN_ID}`);
            const result = await response.json();

            if (result.status === 'success') {
                const data = result.data;

                // Set Header Info
                document.getElementById('titleHalaman').innerText = `Laporan PJP - Kelompok ${data.nama_kelompok}`;
                document.getElementById('infoPeriodeKelompok').innerHTML = `Periode: <strong>${data.nama_periode}</strong>`;

                // Tampilkan Container
                document.getElementById('containerLaporan').classList.remove('hidden');

                populateForm(data);

                // Update Badge Status
                const badge = document.getElementById('statusBadge');
                if (data.status === 'TTD_KETUA') {
                    badge.className = 'bg-green-100 text-green-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
                    badge.innerHTML = '<i class="fa-solid fa-check-double mr-2"></i> Status: SELESAI (Sudah TTD)';
                } else if (data.status === 'FINAL') {
                    badge.className = 'bg-blue-100 text-blue-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
                    badge.innerHTML = '<i class="fa-solid fa-clock mr-2"></i> Status: FINAL (Menunggu TTD)';
                } else {
                    badge.className = 'bg-yellow-100 text-yellow-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
                    badge.innerHTML = '<i class="fa-solid fa-pen mr-2"></i> Status: DRAFT';
                }

            } else {
                Swal.fire('Error!', result.message, 'error').then(() => history.back());
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Gagal!', 'Terjadi kesalahan saat memuat form laporan.', 'error');
        }
    }

    function populateForm(data) {
        // A. Kepengurusan
        let kepengurusanHTML = `
            <div><p class="text-gray-500 mb-1">Pengawas</p><p class="font-semibold text-gray-900">${data.kepengurusan.pengawas || '-'}</p></div>
            <div><p class="text-gray-500 mb-1">Ketua Kelompok</p><p class="font-semibold text-gray-900">${data.kepengurusan.ketua || '-'}</p></div>
            <div><p class="text-gray-500 mb-1">Wakil Ketua</p><p class="font-semibold text-gray-900">${data.kepengurusan.wakil || '-'}</p></div>
            <div><p class="text-gray-500 mb-1">Sekretaris</p><p class="font-semibold text-gray-900">${data.kepengurusan.sekretaris || '-'}</p></div>
            <div><p class="text-gray-500 mb-1">Bendahara</p><p class="font-semibold text-gray-900">${data.kepengurusan.bendahara || '-'}</p></div>
            <div class="col-span-1 sm:col-span-2 mt-2"><p class="text-gray-500 mb-1">Wali Kelas</p><ul class="list-disc list-inside text-gray-900 font-medium">
        `;
        for (const [kelas, nama] of Object.entries(data.kepengurusan.wali_kelas || {})) {
            kepengurusanHTML += `<li>${kelas}: ${nama}</li>`;
        }
        kepengurusanHTML += `</ul></div>`;
        document.getElementById('containerKepengurusan').innerHTML = kepengurusanHTML;

        // B. Checklist
        document.getElementById('checkMusyawarahPjp').checked = data.checklist.pjp;
        document.getElementById('checkMusyawarahUnsur').checked = data.checklist.unsur;

        // C. Detail Kelas
        renderDetailKelas(data.detail_kelas);

        // D. Permasalahan (Hanya render teks, bukan textarea)
        const containerMasalah = document.getElementById('containerPermasalahan');
        containerMasalah.innerHTML = '';

        if (data.permasalahan && data.permasalahan.length > 0) {
            data.permasalahan.forEach(text => {
                containerMasalah.innerHTML += `
                    <div class="flex items-start gap-2 bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <div class="mt-1 text-red-400"><i class="fa-solid fa-circle-dot text-[10px]"></i></div>
                        <p class="text-sm text-gray-700 w-full">${text.replace(/\n/g, '<br>')}</p>
                    </div>
                `;
            });
        } else {
            document.getElementById('emptyPermasalahan').classList.remove('hidden');
        }
    }

    function renderDetailKelas(kelasArray) {
        const container = document.getElementById('containerKelas');
        container.innerHTML = '';

        kelasArray.forEach((k, index) => {
            let kategoriHTML = '';
            if (k.ketercapaian_kategori && Object.keys(k.ketercapaian_kategori).length > 0) {
                for (const [namaKat, nilai] of Object.entries(k.ketercapaian_kategori)) {
                    kategoriHTML += `<div class="flex justify-between"><span>${namaKat}</span> <span>${nilai}%</span></div>`;
                }
            } else {
                kategoriHTML = `<div class="text-gray-400 italic">Belum ada data jurnal</div>`;
            }

            const isDesa = k.penyelenggara === 'desa' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500';
            const isKelompok = k.penyelenggara === 'kelompok' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500';

            const html = `
                <div class="border rounded-xl p-5 bg-gray-50/50">
                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200">
                        <h4 class="text-lg font-bold text-blue-700">${k.nama_kelas}</h4>
                        <div class="flex space-x-4 text-sm font-medium">
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded"><i class="fa-solid fa-users mr-1"></i> ${k.jml_siswa} Siswa</span>
                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded"><i class="fa-solid fa-person-chalkboard mr-1"></i> ${k.jml_guru} Guru</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Kehadiran -->
                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Rata-Rata Kehadiran</p>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between"><span class="text-green-600"><i class="fa-solid fa-circle-check w-4"></i> Hadir</span> <span class="font-bold">${k.kehadiran.hadir}%</span></div>
                                <div class="flex justify-between"><span class="text-blue-600"><i class="fa-solid fa-circle-info w-4"></i> Izin</span> <span class="font-bold">${k.kehadiran.izin}%</span></div>
                                <div class="flex justify-between"><span class="text-yellow-600"><i class="fa-solid fa-notes-medical w-4"></i> Sakit</span> <span class="font-bold">${k.kehadiran.sakit}%</span></div>
                                <div class="flex justify-between"><span class="text-red-600"><i class="fa-solid fa-circle-xmark w-4"></i> Alpa</span> <span class="font-bold">${k.kehadiran.alpa}%</span></div>
                            </div>
                        </div>

                        <!-- Ketercapaian -->
                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Ketercapaian Materi</p>
                            <div class="mb-2">
                                <div class="flex justify-between text-xs mb-1"><span>Rata-rata Global</span><span class="font-bold text-green-600">${k.ketercapaian_global}%</span></div>
                                <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-green-500 h-2 rounded-full" style="width: ${k.ketercapaian_global}%"></div></div>
                            </div>
                            <div class="space-y-1 text-xs mt-3">${kategoriHTML}</div>
                        </div>

                        <!-- Info Manual (Read Only Mode) -->
                        <div class="lg:col-span-2 bg-white p-4 rounded-lg shadow-sm border border-gray-100 flex flex-col justify-center space-y-4">
                            <div>
                                <p class="text-sm font-bold text-gray-700 mb-2">Penyelenggara KBM</p>
                                <div class="flex space-x-2">
                                    <span class="px-3 py-1 rounded-md text-xs font-semibold ${isDesa}">Desa</span>
                                    <span class="px-3 py-1 rounded-md text-xs font-semibold ${isKelompok}">Kelompok</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Tatap Muka per Minggu</label>
                                <div class="text-lg font-bold text-blue-600">
                                    ${k.tatap_muka} <span class="text-sm font-normal text-gray-500">Kali / Minggu</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.innerHTML += html;
        });
    }
</script>