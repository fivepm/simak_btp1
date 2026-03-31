<!-- Ambil parameter dari URL -->
<script>
    const urlParams = new URLSearchParams(window.location.search);
    const PERIODE_ID = urlParams.get('periode_id');

    if (!PERIODE_ID) {
        Swal.fire('Error!', 'ID Periode tidak ditemukan.', 'error').then(() => history.back());
    }
</script>

<!-- Header Section -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <button onclick="history.back()" class="text-gray-500 hover:text-blue-600 text-sm mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar Laporan
        </button>
        <h2 class="text-2xl font-bold text-gray-900">Form Laporan PJP Tingkat Desa</h2>
        <p class="text-sm text-gray-500 mt-1">Rekapitulasi otomatis dari seluruh kelompok.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" id="btnRefreshDesa" onclick="refreshDataDesa()" class="hidden bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-3 py-2 rounded-lg shadow-sm font-medium text-sm transition-colors">
            <i class="fa-solid fa-arrows-rotate"></i> <span class="hidden sm:inline ml-1">Refresh Data Sistem</span>
        </button>
        <div id="statusBadge" class="bg-gray-100 text-gray-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat...
        </div>
    </div>
</div>

<form id="formLaporanDesa" class="space-y-6 hidden">
    <input type="hidden" id="laporan_desa_id" value="">

    <!-- Card 1: Data Kepengurusan Desa -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-blue-50 px-6 py-4 border-b border-blue-100 flex justify-between items-center">
            <h3 class="font-bold text-blue-800"><i class="fa-solid fa-sitemap mr-2"></i> Pengurus PJP Tingkat Desa</h3>
            <span class="text-xs text-blue-500">*Otomatis</span>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm" id="containerKepengurusanDesa">
                <!-- Di-render JS -->
            </div>
        </div>
    </div>

    <!-- NEW CARD: Perbandingan Rata-Rata Antar Kelompok & Grand Average Desa -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-blue-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-blue-800"><i class="fa-solid fa-chart-column text-blue-600 mr-2"></i> Perbandingan Rata-Rata Antar Kelompok</h3>
        </div>
        <div class="p-6 bg-gray-50/30">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" id="containerPerbandinganKelompok">
                <div class="text-center py-4 text-gray-400 col-span-full"><i class="fa-solid fa-circle-notch fa-spin text-xl mb-2 block"></i> Kalkulasi data...</div>
            </div>
        </div>
    </div>

    <!-- Card 2: Rekapitulasi Per Kelas -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-blue-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-blue-800"><i class="fa-solid fa-chart-pie text-blue-600 mr-2"></i> Rekapitulasi Kehadiran & Capaian (Rata-rata Desa)</h3>
        </div>
        <div class="p-6 space-y-6" id="containerRekapKelas">
            <div class="text-center py-4 text-gray-400"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block"></i> Kalkulasi data kelompok...</div>
        </div>
    </div>

    <!-- Card 3: NEW - Expander Detail Tiap Kelompok -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mt-6">
        <button type="button" onclick="toggleExpander('expanderDetailKelompok', 'iconDetailKelompok')" class="w-full bg-blue-50 px-6 py-4 flex justify-between items-center hover:bg-gray-100 transition-colors focus:outline-none">
            <h3 class="font-bold text-blue-800"><i class="fa-solid fa-layer-group text-blue-600 mr-2"></i> Rincian Laporan Tiap Kelompok <span class="text-xs text-blue-500 ml-2 font-normal">(Klik untuk membuka/menutup)</span></h3>
            <i id="iconDetailKelompok" class="fa-solid fa-chevron-down text-gray-500 transition-transform duration-300 transform rotate-180"></i>
        </button>

        <div id="expanderDetailKelompok" class="border-t border-gray-100 p-6 space-y-4 hidden">
            <!-- Sub-expander tiap kelompok akan di-render di sini oleh JS -->
            <div class="text-center py-4 text-gray-400"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block"></i> Memuat detail kelompok...</div>
        </div>
    </div>

    <!-- Card 4: Catatan / Evaluasi Desa (Dipindah agar lebih luas) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
        <div class="bg-blue-50 px-6 py-4 border-b border-blue-100">
            <h3 class="font-bold text-blue-800"><i class="fa-solid fa-pen-to-square mr-2"></i> Evaluasi / Catatan Tingkat Desa</h3>
        </div>
        <div class="p-6 flex-1 flex flex-col">
            <p class="text-sm text-gray-500 mb-3">Tuliskan evaluasi, tindak lanjut, atau instruksi dari pengurus desa berdasarkan permasalahan kelompok di bawah.</p>
            <textarea id="catatanDesa" rows="5" class="w-full flex-1 border border-gray-300 rounded-lg p-4 text-sm focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm" placeholder="Tulis catatan evaluasi Anda di sini..."></textarea>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 pt-4 border-t border-gray-200" id="containerAksi">
        <button type="button" id="btnDraft" onclick="simpanLaporanDesa('DRAFT')" class="px-6 py-2.5 bg-white border-2 border-blue-600 text-blue-600 hover:bg-blue-50 font-medium rounded-lg shadow-sm transition-colors">
            <i class="fa-regular fa-floppy-disk mr-2"></i> Simpan Draft
        </button>
        <button type="button" id="btnFinal" onclick="simpanLaporanDesa('FINAL')" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition-colors">
            <i class="fa-solid fa-paper-plane mr-2"></i> Simpan Final
        </button>
    </div>
</form>

<script>
    let currentDataDesa = {};

    document.addEventListener('DOMContentLoaded', () => {
        loadDataLaporanDesa();
    });

    async function loadDataLaporanDesa() {
        try {
            const response = await fetch(`pages/laporan_desa/ajax_form_laporan_desa.php?action=get_laporan_desa&periode_id=${PERIODE_ID}`);
            const result = await response.json();

            if (result.status === 'success') {
                currentDataDesa = result.data;
                document.getElementById('laporan_desa_id').value = currentDataDesa.laporan_desa_id || '';

                document.getElementById('formLaporanDesa').classList.remove('hidden');

                renderPerbandinganKelompok(currentDataDesa.detail_tiap_kelompok);

                renderKepengurusanDesa(currentDataDesa.kepengurusan);
                renderRekapKelas(currentDataDesa.rekap_kelompok.detail_kelas);

                // MENGGUNAKAN DATA BARU: Render Expander Detail Tiap Kelompok
                renderDetailTiapKelompok(currentDataDesa.detail_tiap_kelompok);

                document.getElementById('catatanDesa').value = currentDataDesa.rekap_kelompok.catatan_desa || '';
                updateUIStatus(currentDataDesa.status);
            } else {
                Swal.fire('Tidak Dapat Diakses!', result.message, 'error').then(() => history.back());
            }
        } catch (error) {
            Swal.fire('Gagal!', 'Terjadi kesalahan saat memuat rekap desa.', 'error');
        }
    }

    // Fungsi Global Toggle Expander
    function toggleExpander(elementId, iconId) {
        const element = document.getElementById(elementId);
        const icon = document.getElementById(iconId);
        if (element.classList.contains('hidden')) {
            element.classList.remove('hidden');
            icon.classList.add('rotate-180');
        } else {
            element.classList.add('hidden');
            icon.classList.remove('rotate-180');
        }
    }

    function renderKepengurusanDesa(pengurus) {
        document.getElementById('containerKepengurusanDesa').innerHTML = `
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Pembina</p><p class="font-semibold text-gray-900">${pengurus.pembina}</p></div>
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Ketua</p><p class="font-semibold text-gray-900">${pengurus.ketua}</p></div>
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Wakil Ketua</p><p class="font-semibold text-gray-900">${pengurus.wakil}</p></div>
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Sekretaris</p><p class="font-semibold text-gray-900">${pengurus.sekretaris}</p></div>
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Bendahara</p><p class="font-semibold text-gray-900">${pengurus.bendahara}</p></div>
        `;
    }

    function renderRekapKelas(kelasArray) {
        const container = document.getElementById('containerRekapKelas');
        container.innerHTML = '';

        kelasArray.forEach((k) => {
            let kategoriHTML = '';
            if (k.ketercapaian_kategori && Object.keys(k.ketercapaian_kategori).length > 0) {
                for (const [namaKat, nilai] of Object.entries(k.ketercapaian_kategori)) {
                    kategoriHTML += `<div class="flex justify-between"><span>${namaKat}</span> <span class="font-semibold">${nilai}%</span></div>`;
                }
            }

            container.innerHTML += `
                <div class="border rounded-xl p-5 bg-gray-50 border-gray-200">
                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200">
                        <h4 class="text-lg font-bold text-gray-800">${k.nama_kelas}</h4>
                        <div class="flex space-x-3 text-sm font-medium">
                            <span class="text-blue-700 bg-blue-100 px-2 py-1 rounded"><i class="fa-solid fa-users mr-1"></i> Total ${k.jml_siswa} Siswa</span>
                            <span class="text-purple-700 bg-purple-100 px-2 py-1 rounded"><i class="fa-solid fa-person-chalkboard mr-1"></i> Total ${k.jml_guru} Guru</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Rata-Rata Kehadiran se-Desa</p>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div class="bg-green-50 p-2 rounded text-center"><span class="block text-green-600 font-bold text-lg">${k.kehadiran.hadir}%</span><span class="text-xs text-green-700">Hadir</span></div>
                                <div class="bg-blue-50 p-2 rounded text-center"><span class="block text-blue-600 font-bold text-lg">${k.kehadiran.izin}%</span><span class="text-xs text-blue-700">Izin</span></div>
                                <div class="bg-yellow-50 p-2 rounded text-center"><span class="block text-yellow-600 font-bold text-lg">${k.kehadiran.sakit}%</span><span class="text-xs text-yellow-700">Sakit</span></div>
                                <div class="bg-red-50 p-2 rounded text-center"><span class="block text-red-600 font-bold text-lg">${k.kehadiran.alpa}%</span><span class="text-xs text-red-700">Alpa</span></div>
                            </div>
                        </div>
                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Rata-Rata Ketercapaian se-Desa</p>
                            <div class="mb-2">
                                <div class="flex justify-between text-sm mb-1"><span>Target Kelas</span><span class="font-bold text-blue-600">${k.ketercapaian_global}%</span></div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5"><div class="bg-blue-500 h-2.5 rounded-full" style="width: ${k.ketercapaian_global}%"></div></div>
                            </div>
                            <div class="space-y-1 text-xs mt-3 bg-gray-50 p-2 rounded border border-gray-100">${kategoriHTML}</div>
                        </div>
                    </div>
                </div>
            `;
        });
    }

    // FUNGSI BARU: Render Perbandingan Rata-rata Kelompok & Rata-rata Desa
    function renderPerbandinganKelompok(kelompokArray) {
        const container = document.getElementById('containerPerbandinganKelompok');
        container.innerHTML = '';

        if (!kelompokArray || kelompokArray.length === 0) {
            container.innerHTML = `<div class="text-center py-4 text-gray-500 italic col-span-full">Data kelompok tidak tersedia.</div>`;
            return;
        }

        let sumGroupHadir = 0;
        let sumGroupCapaian = 0;
        let validGroupCount = 0;
        let cardsHTML = '';

        // 1. Loop tiap kelompok untuk menghitung rata-ratanya masing-masing
        kelompokArray.forEach(k => {
            let totalHadir = 0,
                totalCapaian = 0;
            let count = k.detail_kelas ? k.detail_kelas.length : 0;

            if (count > 0) {
                k.detail_kelas.forEach(kelas => {
                    totalHadir += parseFloat(kelas.kehadiran.hadir) || 0;
                    totalCapaian += parseFloat(kelas.ketercapaian_global) || 0;
                });
            }

            // Rata-rata 1 kelompok
            let avgHadir = count > 0 ? Math.round(totalHadir / count) : 0;
            let avgCapaian = count > 0 ? Math.round(totalCapaian / count) : 0;

            // Tambahkan ke variabel akumulasi Desa (hanya jika kelompok ini ada datanya)
            if (count > 0) {
                sumGroupHadir += avgHadir;
                sumGroupCapaian += avgCapaian;
                validGroupCount++;
            }

            // Render Card untuk kelompok ini
            cardsHTML += `
                <div class="border border-gray-200 rounded-xl p-4 bg-white shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-blue-400"></div>
                    <h4 class="font-bold text-gray-800 text-center mb-4 mt-1 text-lg">${k.nama_kelompok}</h4>
                    
                    <div class="bg-green-50 rounded-lg p-2 flex justify-between items-center mb-2 border border-green-100">
                        <span class="text-xs text-green-700 uppercase font-bold"><i class="fa-solid fa-user-check mr-1"></i> Kehadiran</span>
                        <span class="font-bold text-green-600 text-lg">${avgHadir}%</span>
                    </div>
                    
                    <div class="bg-blue-50 rounded-lg p-2 flex justify-between items-center border border-blue-100">
                        <span class="text-xs text-blue-700 uppercase font-bold"><i class="fa-solid fa-book-open mr-1"></i> Capaian</span>
                        <span class="font-bold text-blue-600 text-lg">${avgCapaian}%</span>
                    </div>
                </div>
            `;
        });

        // 2. Hitung Grand Average (Rata-rata Tingkat Desa dari 4 kelompok)
        let grandAvgHadir = validGroupCount > 0 ? Math.round(sumGroupHadir / validGroupCount) : 0;
        let grandAvgCapaian = validGroupCount > 0 ? Math.round(sumGroupCapaian / validGroupCount) : 0;

        // 3. Render Card Utama Desa (Warna lebih pekat dan ukurannya lebar)
        let grandCardHTML = `
            <div class="col-span-full border-2 border-blue-600 rounded-xl p-5 bg-blue-50 shadow-md flex flex-col sm:flex-row items-center justify-between mb-2">
                <div class="mb-4 sm:mb-0 text-center sm:text-left">
                    <h4 class="font-black text-blue-800 text-xl"><i class="fa-solid fa-building-flag mr-2"></i> Rata-Rata Tingkat Desa</h4>
                    <p class="text-sm text-blue-600 font-medium mt-1">Akumulasi rata-rata dari ${validGroupCount} kelompok</p>
                </div>
                <div class="flex gap-4 w-full sm:w-auto">
                    <div class="bg-white rounded-xl p-3 flex-1 sm:w-36 flex flex-col items-center border-2 border-green-200 shadow-sm">
                        <span class="text-xs text-green-600 uppercase font-extrabold mb-1 tracking-wider">Kehadiran</span>
                        <span class="font-black text-green-600 text-3xl">${grandAvgHadir}%</span>
                    </div>
                    <div class="bg-white rounded-xl p-3 flex-1 sm:w-36 flex flex-col items-center border-2 border-blue-200 shadow-sm">
                        <span class="text-xs text-blue-600 uppercase font-extrabold mb-1 tracking-wider">Capaian</span>
                        <span class="font-black text-blue-700 text-3xl">${grandAvgCapaian}%</span>
                    </div>
                </div>
            </div>
        `;

        // 4. Gabungkan HTML (Card Desa di atas, lalu pemisah, lalu 4 Card Kelompok)
        container.innerHTML = grandCardHTML + cardsHTML;
    }

    // FUNGSI BARU: Render Expander Detail Tiap Kelompok
    function renderDetailTiapKelompok(kelompokArray) {
        const container = document.getElementById('expanderDetailKelompok');
        container.innerHTML = '';

        if (!kelompokArray || kelompokArray.length === 0) {
            container.innerHTML = `<div class="text-center py-4 text-gray-500 italic">Data kelompok tidak tersedia.</div>`;
            return;
        }

        kelompokArray.forEach((k, index) => {
            // Kalkulasi Rata-rata per kelompok ini
            let totalHadir = 0,
                totalIzin = 0,
                totalSakit = 0,
                totalAlpa = 0,
                totalCapaian = 0;
            let totalSiswa = 0,
                totalGuru = 0;
            let detailKelasHTML = '';
            let count = k.detail_kelas ? k.detail_kelas.length : 0;

            if (count > 0) {
                k.detail_kelas.forEach(kelas => {
                    totalHadir += parseFloat(kelas.kehadiran.hadir) || 0;
                    totalIzin += parseFloat(kelas.kehadiran.izin) || 0;
                    totalSakit += parseFloat(kelas.kehadiran.sakit) || 0;
                    totalAlpa += parseFloat(kelas.kehadiran.alpa) || 0;
                    totalCapaian += parseFloat(kelas.ketercapaian_global) || 0;
                    totalSiswa += parseInt(kelas.jml_siswa) || 0;
                    totalGuru += parseInt(kelas.jml_guru) || 0;

                    detailKelasHTML += `
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="py-2.5 px-3 font-medium text-gray-700 whitespace-nowrap">${kelas.nama_kelas}</td>
                            <td class="py-2.5 px-3 text-center">${kelas.jml_siswa}</td>
                            <td class="py-2.5 px-3 text-center">${kelas.jml_guru}</td>
                            <td class="py-2.5 px-3 text-center">${kelas.tatap_muka}x</td>
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                <span class="text-green-600 font-medium mr-1" title="Hadir">${kelas.kehadiran.hadir}%</span>|
                                <span class="text-blue-600 font-medium mx-1" title="Izin">${kelas.kehadiran.izin}%</span>|
                                <span class="text-yellow-600 font-medium mx-1" title="Sakit">${kelas.kehadiran.sakit}%</span>|
                                <span class="text-red-600 font-medium ml-1" title="Alpa">${kelas.kehadiran.alpa}%</span>
                            </td>
                            <td class="py-2.5 px-3 text-center font-bold text-blue-600">${kelas.ketercapaian_global}%</td>
                        </tr>
                    `;
                });
            }

            let avgHadir = count > 0 ? Math.round(totalHadir / count) : 0;
            let avgCapaian = count > 0 ? Math.round(totalCapaian / count) : 0;

            // HTML Checklist
            const checkPjp = k.checklist.pjp ? '<i class="fa-solid fa-circle-check text-green-500 text-lg"></i>' : '<i class="fa-solid fa-circle-xmark text-red-500 text-lg"></i>';
            const checkUnsur = k.checklist.unsur ? '<i class="fa-solid fa-circle-check text-green-500 text-lg"></i>' : '<i class="fa-solid fa-circle-xmark text-red-500 text-lg"></i>';

            // HTML Permasalahan
            let masalahHTML = '<ul class="list-disc list-inside text-sm text-gray-700 space-y-1.5">';
            if (k.permasalahan && k.permasalahan.length > 0) {
                k.permasalahan.forEach(m => {
                    masalahHTML += `<li>${m.replace(/\n/g, '<br>')}</li>`;
                });
            } else {
                masalahHTML = '<p class="text-sm text-gray-400 italic">Tidak ada permasalahan.</p>';
            }
            masalahHTML += '</ul>';

            const accordionId = `acc_kelompok_${index}`;
            const iconId = `icon_kelompok_${index}`;

            const html = `
                <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <button type="button" onclick="toggleExpander('${accordionId}', '${iconId}')" class="w-full bg-white px-5 py-3.5 flex justify-between items-center hover:bg-blue-50 transition-colors border-b border-transparent">
                        <div class="flex items-center">
                            <i class="fa-solid fa-users text-blue-600 mr-3"></i>
                            <span class="font-bold text-gray-800 text-base">Kelompok ${k.nama_kelompok}</span>
                        </div>
                        <i id="${iconId}" class="fa-solid fa-chevron-down text-gray-400 transition-transform duration-300"></i>
                    </button>
                    
                    <div id="${accordionId}" class="hidden p-5 bg-gray-50/50 space-y-5 border-t border-gray-200">
                        
                        <!-- Grid 3 Kolom: Ringkasan, Checklist, Permasalahan -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                            <!-- Ringkasan Kelompok -->
                            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-3"><i class="fa-solid fa-chart-simple mr-1"></i> Rata-Rata Kelompok</p>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div class="bg-gray-50 p-2 rounded-lg border border-gray-100">
                                        <span class="block text-gray-500 text-[10px] uppercase font-bold">Total Siswa</span>
                                        <span class="font-bold text-gray-800">${totalSiswa} <i class="fa-solid fa-user text-xs"></i></span>
                                    </div>
                                    <div class="bg-gray-50 p-2 rounded-lg border border-gray-100">
                                        <span class="block text-gray-500 text-[10px] uppercase font-bold">Total Guru</span>
                                        <span class="font-bold text-gray-800">${totalGuru} <i class="fa-solid fa-person-chalkboard text-xs"></i></span>
                                    </div>
                                    <div class="bg-green-50 p-2 rounded-lg border border-green-100">
                                        <span class="block text-green-700 text-[10px] uppercase font-bold">Rata-rata Hadir</span>
                                        <span class="font-bold text-green-600">${avgHadir}%</span>
                                    </div>
                                    <div class="bg-blue-50 p-2 rounded-lg border border-blue-100">
                                        <span class="block text-blue-700 text-[10px] uppercase font-bold">Capaian Materi</span>
                                        <span class="font-bold text-blue-600">${avgCapaian}%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Checklist Musyawarah -->
                            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-3"><i class="fa-solid fa-list-check mr-1"></i> Checklist Musyawarah</p>
                                <div class="space-y-3">
                                    <div class="flex items-center text-sm bg-gray-50 p-2 rounded-lg border border-gray-100">
                                        ${checkPjp} <span class="ml-2 font-medium text-gray-700">Musyawarah PJP Kelompok</span>
                                    </div>
                                    <div class="flex items-center text-sm bg-gray-50 p-2 rounded-lg border border-gray-100">
                                        ${checkUnsur} <span class="ml-2 font-medium text-gray-700">Musyawarah 5 Unsur</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Permasalahan -->
                            <div class="bg-red-50 p-4 rounded-xl shadow-sm border border-red-100 h-full max-h-[160px] overflow-y-auto">
                                <p class="text-xs font-bold text-red-800 uppercase mb-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Permasalahan Input</p>
                                ${masalahHTML}
                            </div>
                        </div>

                        <!-- Tabel Detail Per Kelas -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                                <p class="text-xs font-bold text-gray-600 uppercase">Tabel Detail Per Kelas</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-gray-50/50 border-b border-gray-200 text-gray-600 text-xs uppercase">
                                        <tr>
                                            <th class="py-3 px-3 font-semibold">Nama Kelas</th>
                                            <th class="py-3 px-3 font-semibold text-center">Siswa</th>
                                            <th class="py-3 px-3 font-semibold text-center">Guru</th>
                                            <th class="py-3 px-3 font-semibold text-center">Tatap Muka</th>
                                            <th class="py-3 px-3 font-semibold text-center tracking-wider">Hadir | Izin | Sakit | Alpa</th>
                                            <th class="py-3 px-3 font-semibold text-center">Capaian Materi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${detailKelasHTML || '<tr><td colspan="6" class="text-center py-4 text-gray-500 italic">Data kelas tidak tersedia.</td></tr>'}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            `;
            container.innerHTML += html;
        });
    }

    async function simpanLaporanDesa(targetStatus) {
        Swal.fire({
            title: targetStatus === 'FINAL' ? 'Finalkan Laporan Desa?' : 'Simpan Draft?',
            text: targetStatus === 'FINAL' ? "Laporan akan dikirim ke Ketua PJP Desa untuk ditandatangani." : "Simpan sementara hasil evaluasi ini.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            confirmButtonText: targetStatus === 'FINAL' ? 'Ya, Finalkan!' : 'Simpan Draft'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menyimpan...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                currentDataDesa.rekap_kelompok.catatan_desa = document.getElementById('catatanDesa').value;

                const formData = new FormData();
                formData.append('periode_id', PERIODE_ID);
                formData.append('laporan_desa_id', currentDataDesa.laporan_desa_id || '');
                formData.append('status', targetStatus);
                formData.append('kepengurusan', JSON.stringify(currentDataDesa.kepengurusan));
                formData.append('rekap_kelompok', JSON.stringify(currentDataDesa.rekap_kelompok));

                try {
                    const response = await fetch('pages/laporan_desa/ajax_form_laporan_desa.php?action=simpan_laporan_desa', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();

                    if (res.status === 'success') {
                        currentDataDesa.status = targetStatus;
                        updateUIStatus(targetStatus);
                        Swal.fire('Berhasil!', res.message, 'success');
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error!', 'Terjadi kesalahan server.', 'error');
                }
            }
        });
    }

    function refreshDataDesa() {
        const laporanId = document.getElementById('laporan_desa_id').value;
        if (!laporanId) return;

        Swal.fire({
            title: 'Sinkronkan Ulang?',
            text: "Jika ada kelompok yang baru saja merevisi laporannya, klik ini untuk menarik data rekap terbaru. Catatan evaluasi manual Anda tidak akan hilang.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Tarik Data Terbaru!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menyinkronkan...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                try {
                    const formData = new FormData();
                    formData.append('laporan_desa_id', laporanId);
                    const response = await fetch('pages/laporan_desa/ajax_form_laporan_desa.php?action=refresh_data', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();

                    if (res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => {
                            document.getElementById('formLaporanDesa').classList.add('hidden');
                            loadDataLaporanDesa();
                        });
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error!', 'Terjadi kesalahan server.', 'error');
                }
            }
        });
    }

    function updateUIStatus(status) {
        const badge = document.getElementById('statusBadge');
        const textarea = document.getElementById('catatanDesa');
        const aksi = document.getElementById('containerAksi');
        const btnRefresh = document.getElementById('btnRefreshDesa');

        if (status === 'DRAFT') {
            badge.className = 'bg-yellow-100 text-yellow-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
            badge.innerHTML = '<i class="fa-solid fa-pen mr-2"></i> Status: DRAFT';
            textarea.disabled = false;
            aksi.classList.remove('hidden');
            if (currentDataDesa.laporan_desa_id) btnRefresh.classList.remove('hidden');
            else btnRefresh.classList.add('hidden');
        } else if (status === 'FINAL') {
            badge.className = 'bg-blue-100 text-blue-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
            badge.innerHTML = '<i class="fa-solid fa-clock mr-2"></i> Status: FINAL (Menunggu TTD)';
            textarea.disabled = true;
            aksi.classList.add('hidden');
            btnRefresh.classList.add('hidden');
        } else if (status === 'TTD_KETUA') {
            badge.className = 'bg-green-100 text-green-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
            badge.innerHTML = '<i class="fa-solid fa-check-double mr-2"></i> Status: SELESAI (Sudah TTD)';
            textarea.disabled = true;
            aksi.classList.add('hidden');
            btnRefresh.classList.add('hidden');
        }
    }
</script>