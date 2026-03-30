<!-- Ambil parameter periode_id dari URL -->
<script>
    const urlParams = new URLSearchParams(window.location.search);
    const PERIODE_ID = urlParams.get('periode_id');

    if (!PERIODE_ID) {
        Swal.fire('Error!', 'ID Periode tidak ditemukan.', 'error').then(() => {
            history.back();
        });
    }
</script>

<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <button onclick="history.back()" class="text-gray-500 hover:text-blue-600 text-sm mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
        </button>
        <h2 class="text-2xl font-bold text-gray-900">Review & Tanda Tangan Laporan</h2>
        <p class="text-sm text-gray-500 mt-1" id="infoPeriodeKetua">Memuat informasi...</p>
    </div>
    <div id="statusBadge" class="bg-gray-100 text-gray-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center">
        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat...
    </div>
</div>

<div id="containerLaporanKetua" class="space-y-6 hidden">
    <!-- Simpan ID Laporan diam-diam -->
    <input type="hidden" id="laporan_id_ketua" value="">

    <!-- Pemberitahuan Read-Only -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg">
        <div class="flex">
            <div class="flex-shrink-0"><i class="fa-solid fa-magnifying-glass text-blue-500"></i></div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    Mohon periksa kembali data laporan di bawah ini. Jika sudah sesuai, silakan tekan tombol <strong>Tanda Tangani Laporan</strong> di bagian paling bawah.
                </p>
            </div>
        </div>
    </div>

    <!-- Grid 2 Kolom untuk Kepengurusan & Checklist -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Card 1: Data Kepengurusan -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-800"><i class="fa-solid fa-sitemap text-blue-600 mr-2"></i> Data Kepengurusan</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 text-sm" id="containerKepengurusan"></div>
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
            <div id="containerPermasalahan" class="space-y-3"></div>
            <div id="emptyPermasalahan" class="hidden text-center py-6 text-gray-400 text-sm">
                <i class="fa-regular fa-folder-open text-3xl mb-2 block"></i>
                Tidak ada permasalahan yang dicatat oleh kelompok.
            </div>
        </div>
    </div>

    <!-- Area Tanda Tangan & Revisi -->
    <div class="bg-white rounded-xl shadow-sm border border-blue-200 overflow-hidden mt-6" id="containerAksiTTD">
        <div class="p-8 text-center flex flex-col items-center justify-center">
            <h3 class="text-xl font-bold text-gray-800 mb-2">Persetujuan & Pengesahan</h3>
            <p class="text-gray-500 mb-6 max-w-2xl">Pilih aksi di bawah ini. Anda dapat mengesahkan laporan, atau mengembalikannya ke Admin Kelompok jika terdapat kesalahan data.</p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center w-full max-w-lg">
                <button type="button" onclick="prosesTolakRevisi()" class="flex-1 px-6 py-3 bg-white border-2 border-red-500 text-red-600 hover:bg-red-50 text-lg font-bold rounded-xl shadow-sm hover:shadow transition-all flex items-center justify-center">
                    <i class="fa-solid fa-rotate-left mr-2"></i> Kembalikan (Revisi)
                </button>
                <button type="button" onclick="prosesTandaTangan()" class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white text-lg font-bold rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center animate-pulse">
                    <i class="fa-solid fa-file-signature mr-2"></i> Sahkan Laporan
                </button>
            </div>
        </div>
    </div>

    <!-- Area Info Sudah TTD (Hidden by default) -->
    <div class="bg-green-50 rounded-xl shadow-sm border border-green-200 overflow-hidden mt-6 hidden" id="containerSudahTTD">
        <div class="p-8 text-center flex flex-col items-center justify-center">
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-3xl mb-4">
                <i class="fa-solid fa-check-double"></i>
            </div>
            <h3 class="text-xl font-bold text-green-800 mb-1">Laporan Telah Disahkan</h3>
            <p class="text-green-700 font-medium" id="teksInfoTTD">Ditandatangani pada: -</p>
        </div>
    </div>

</div>

<!-- JS Logic Form -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (PERIODE_ID) {
            loadDataLaporanKetua();
        }
    });

    async function loadDataLaporanKetua() {
        try {
            const response = await fetch(`pages/laporan_kelompok/ajax_review_laporan_kelompok.php?action=get_laporan_review&periode_id=${PERIODE_ID}`);
            const result = await response.json();

            if (result.status === 'success') {
                const data = result.data;
                document.getElementById('laporan_id_ketua').value = data.laporan_id;
                document.getElementById('infoPeriodeKetua').innerHTML = `Periode: <strong>${data.nama_periode}</strong>`;

                document.getElementById('containerLaporanKetua').classList.remove('hidden');

                populateFormKetua(data);

                // Update Badge dan Area TTD
                const badge = document.getElementById('statusBadge');
                const boxTTD = document.getElementById('containerAksiTTD');
                const boxSudahTTD = document.getElementById('containerSudahTTD');

                if (data.status === 'TTD_KETUA') {
                    badge.className = 'bg-green-100 text-green-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
                    badge.innerHTML = '<i class="fa-solid fa-check-double mr-2"></i> Status: SELESAI (Sudah TTD)';

                    boxTTD.classList.add('hidden');
                    boxSudahTTD.classList.remove('hidden');
                    document.getElementById('teksInfoTTD').innerText = `Ditandatangani secara elektronik pada: ${data.ttd_at}`;
                } else if (data.status === 'FINAL') {
                    badge.className = 'bg-blue-100 text-blue-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
                    badge.innerHTML = '<i class="fa-solid fa-file-signature mr-2"></i> Status: PERLU TTD';

                    boxTTD.classList.remove('hidden');
                    boxSudahTTD.classList.add('hidden');
                }

            } else {
                Swal.fire('Error!', result.message, 'error').then(() => history.back());
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Gagal!', 'Terjadi kesalahan saat memuat form laporan.', 'error');
        }
    }

    function populateFormKetua(data) {
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

        // C. Detail Kelas (Read Only Mode)
        renderDetailKelasKetua(data.detail_kelas);

        // D. Permasalahan
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

    function renderDetailKelasKetua(kelasArray) {
        const container = document.getElementById('containerKelas');
        container.innerHTML = '';

        kelasArray.forEach((k) => {
            let kategoriHTML = '';
            if (k.ketercapaian_kategori && Object.keys(k.ketercapaian_kategori).length > 0) {
                for (const [namaKat, nilai] of Object.entries(k.ketercapaian_kategori)) {
                    kategoriHTML += `<div class="flex justify-between"><span>${namaKat}</span> <span>${nilai}%</span></div>`;
                }
            } else {
                kategoriHTML = `<div class="text-gray-400 italic">Belum ada data</div>`;
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
                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Rata-Rata Kehadiran</p>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between"><span class="text-green-600"><i class="fa-solid fa-circle-check w-4"></i> Hadir</span> <span class="font-bold">${k.kehadiran.hadir}%</span></div>
                                <div class="flex justify-between"><span class="text-blue-600"><i class="fa-solid fa-circle-info w-4"></i> Izin</span> <span class="font-bold">${k.kehadiran.izin}%</span></div>
                                <div class="flex justify-between"><span class="text-yellow-600"><i class="fa-solid fa-notes-medical w-4"></i> Sakit</span> <span class="font-bold">${k.kehadiran.sakit}%</span></div>
                                <div class="flex justify-between"><span class="text-red-600"><i class="fa-solid fa-circle-xmark w-4"></i> Alpa</span> <span class="font-bold">${k.kehadiran.alpa}%</span></div>
                            </div>
                        </div>
                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Ketercapaian Materi</p>
                            <div class="mb-2">
                                <div class="flex justify-between text-xs mb-1"><span>Rata-rata Global</span><span class="font-bold text-green-600">${k.ketercapaian_global}%</span></div>
                                <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-green-500 h-2 rounded-full" style="width: ${k.ketercapaian_global}%"></div></div>
                            </div>
                            <div class="space-y-1 text-xs mt-3">${kategoriHTML}</div>
                        </div>
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
                                <div class="text-lg font-bold text-blue-600">${k.tatap_muka} <span class="text-sm font-normal text-gray-500">Kali / Minggu</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.innerHTML += html;
        });
    }

    // Fungsi Mengirim Data TTD
    function prosesTandaTangan() {
        const laporanId = document.getElementById('laporan_id_ketua').value;

        Swal.fire({
            title: 'Tanda Tangani Laporan?',
            text: "Dengan ini Anda menyetujui seluruh data yang tertera. Laporan yang sudah ditandatangani akan diteruskan ke tingkat Desa dan tidak dapat diubah lagi oleh Admin.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Sahkan Laporan!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const formData = new FormData();
                formData.append('laporan_id', laporanId);

                try {
                    const response = await fetch('pages/laporan_kelompok/ajax_review_laporan_kelompok.php?action=tanda_tangan', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();

                    if (res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => {
                            // Reload data untuk menampilkan badge 'Sudah TTD'
                            loadDataLaporanKetua();
                        });
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error!', 'Terjadi kesalahan komunikasi dengan server.', 'error');
                }
            }
        });
    }

    // Fungsi Mengembalikan Laporan ke Draft (Tolak/Revisi)
    function prosesTolakRevisi() {
        const laporanId = document.getElementById('laporan_id_ketua').value;

        Swal.fire({
            title: 'Kembalikan untuk Revisi?',
            text: "Status laporan akan dikembalikan menjadi DRAFT. Admin Kelompok harus memperbaiki data dan mengajukannya kembali kepada Anda.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Tolak & Revisi!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const formData = new FormData();
                formData.append('laporan_id', laporanId);

                try {
                    const response = await fetch('pages/laporan_kelompok/ajax_review_laporan_kelompok.php?action=tolak_laporan', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();

                    if (res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => {
                            // Lempar ketua kembali ke halaman daftar laporan karena form ini sudah DRAFT
                            history.back();
                        });
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error!', 'Terjadi kesalahan komunikasi dengan server.', 'error');
                }
            }
        });
    }
</script>