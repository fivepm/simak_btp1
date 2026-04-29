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
        <h2 class="text-2xl font-bold text-gray-900">Review & Pengesahan Laporan Desa</h2>
        <p class="text-sm text-gray-500 mt-1" id="infoPeriodeKetua">Memuat informasi...</p>
    </div>
    <div id="statusBadge" class="bg-gray-100 text-gray-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center">
        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat...
    </div>
</div>

<div id="containerLaporanKetua" class="space-y-6 hidden">
    <input type="hidden" id="laporan_desa_id_ketua" value="">

    <!-- Pemberitahuan Read-Only -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg">
        <div class="flex">
            <div class="flex-shrink-0"><i class="fa-solid fa-magnifying-glass text-blue-500"></i></div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    Mohon periksa rekapitulasi data tingkat desa di bawah ini. Jika sudah sesuai, silakan tekan tombol <strong>Sahkan Laporan</strong>.
                </p>
            </div>
        </div>
    </div>

    <!-- Card 1: Data Kepengurusan Desa -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-blue-50 px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-blue-800"><i class="fa-solid fa-sitemap text-blue-600 mr-2"></i> Pengurus PJP Tingkat Desa</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm" id="containerKepengurusanDesa"></div>
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

    <!-- Card 2: Rekapitulasi Rata-rata Tingkat Desa -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-blue-50 px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-blue-800"><i class="fa-solid fa-chart-pie text-blue-600 mr-2"></i> Rata-rata Rekapitulasi Kehadiran & Capaian Materi</h3>
        </div>
        <div class="p-6 space-y-6" id="containerRekapKelas"></div>
    </div>

    <!-- Card 4: Expander Detail Tiap Kelompok -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mt-6">
        <button type="button" onclick="toggleExpander('expanderDetailKelompok', 'iconDetailKelompok')" class="w-full bg-blue-50 px-6 py-4 flex justify-between items-center hover:bg-gray-100 transition-colors focus:outline-none">
            <h3 class="font-bold text-blue-800"><i class="fa-solid fa-layer-group text-blue-600 mr-2"></i> Rincian Laporan Tiap Kelompok <span class="text-xs text-blue-500 ml-2 font-normal">(Klik untuk membuka/menutup)</span></h3>
            <i id="iconDetailKelompok" class="fa-solid fa-chevron-down text-gray-500 transition-transform duration-300 transform rotate-180"></i>
        </button>
        <div id="expanderDetailKelompok" class="border-t border-gray-100 p-6 space-y-4 hidden">
            <!-- Sub-expander tiap kelompok akan di-render di sini oleh JS -->
        </div>
    </div>

    <!-- Card 3: Catatan / Evaluasi Desa -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
        <div class="bg-blue-50 px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-blue-800"><i class="fa-solid fa-pen-to-square text-blue-600 mr-2"></i> Evaluasi / Catatan Tingkat Desa</h3>
        </div>
        <div class="p-6">
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-sm text-gray-700 min-h-[100px] whitespace-pre-wrap" id="containerCatatanDesa">
                <!-- Catatan dirender disini -->
            </div>
        </div>
    </div>

    <!-- Area Tanda Tangan & Revisi -->
    <div class="bg-white rounded-xl shadow-sm border border-blue-200 overflow-hidden mt-6" id="containerAksiTTD">
        <div class="p-8 text-center flex flex-col items-center justify-center">
            <h3 class="text-xl font-bold text-gray-800 mb-2">Persetujuan & Pengesahan</h3>
            <p class="text-gray-500 mb-6 max-w-2xl">Pilih aksi di bawah ini. Anda dapat mengesahkan laporan, atau mengembalikannya ke Admin Desa jika terdapat kesalahan input data.</p>

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

    <!-- Area Info Sudah TTD -->
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
            loadDataLaporanKetuaDesa();
        }
    });

    function toUcwords(str) {
        if (!str) return '';
        return str
            .toLowerCase()
            .split(' ')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    async function loadDataLaporanKetuaDesa() {
        try {
            const response = await fetch(`pages/laporan_desa/ajax_review_laporan_desa.php?action=get_laporan_review&periode_id=${PERIODE_ID}`);
            const result = await response.json();

            if (result.status === 'success') {
                const data = result.data;
                document.getElementById('laporan_desa_id_ketua').value = data.laporan_desa_id;
                document.getElementById('infoPeriodeKetua').innerHTML = `Periode: <strong>${data.nama_periode}</strong>`;

                document.getElementById('containerLaporanKetua').classList.remove('hidden');

                renderKepengurusanDesa(data.kepengurusan);
                renderRekapKelas(data.rekap_kelompok.detail_kelas);
                renderPerbandinganKelompok(data.detail_tiap_kelompok);

                // Menampilkan Catatan Desa dalam div (bukan textarea agar read-only rapi)
                const catatanDesa = data.rekap_kelompok.catatan_desa || '<i class="text-gray-400">Tidak ada catatan/evaluasi.</i>';
                document.getElementById('containerCatatanDesa').innerHTML = catatanDesa;

                renderDetailTiapKelompok(data.detail_tiap_kelompok);

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

    // Fungsi Toggle Expander
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
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Pembina</p><p class="font-semibold text-gray-900">${pengurus.pembina || '-'}</p></div>
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Ketua</p><p class="font-semibold text-gray-900">${pengurus.ketua || '-'}</p></div>
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Wakil Ketua</p><p class="font-semibold text-gray-900">${pengurus.wakil || '-'}</p></div>
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Sekretaris</p><p class="font-semibold text-gray-900">${pengurus.sekretaris || '-'}</p></div>
            <div><p class="text-gray-500 mb-1 text-xs uppercase font-bold">Bendahara</p><p class="font-semibold text-gray-900">${pengurus.bendahara || '-'}</p></div>
        `;
    }

    function renderRekapKelas(kelasArray) {
        const container = document.getElementById('containerRekapKelas');
        container.innerHTML = '';

        kelasArray.forEach((k) => {
            const adaMurid = k.ada_murid !== false && parseInt(k.jml_siswa) > 0;
            const adaKehadiran = k.kehadiran && k.kehadiran.hadir !== null;
            const adaCapaian = k.ketercapaian_global !== null;

            let kategoriHTML = '';
            if (k.ketercapaian_kategori && Object.keys(k.ketercapaian_kategori).length > 0) {
                for (const [namaKat, nilai] of Object.entries(k.ketercapaian_kategori)) {
                    if (nilai !== null) {
                        kategoriHTML += `<div class="flex justify-between"><span>${namaKat}</span> <span class="font-semibold">${nilai}%</span></div>`;
                    } else {
                        kategoriHTML += `<div class="flex justify-between"><span>${namaKat}</span> <span class="font-semibold text-gray-400 text-xs italic">N/A</span></div>`;
                    }
                }
            }

            const naTag = `<span class="bg-gray-200 text-gray-500 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase ml-1">Tidak Ada Murid</span>`;

            container.innerHTML += `
                <div class="border rounded-xl p-5 bg-gray-50 border-gray-200 ${!adaMurid ? 'opacity-70' : ''}">
                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200 flex-wrap gap-2">
                        <h4 class="text-lg font-bold text-gray-800 flex items-center flex-wrap gap-1">
                            ${k.nama_kelas}
                            ${!adaMurid ? naTag : ''}
                        </h4>
                        <div class="flex flex-wrap gap-2 text-sm font-medium">
                            <span class="text-blue-700 bg-blue-100 px-2 py-1 rounded"><i class="fa-solid fa-users mr-1"></i> Total ${k.jml_siswa} Siswa</span>
                            <span class="text-purple-700 bg-purple-100 px-2 py-1 rounded"><i class="fa-solid fa-person-chalkboard mr-1"></i> Total ${k.jml_guru} Guru</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Rata-Rata Kehadiran se-Desa</p>
                            ${adaKehadiran ? `
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div class="bg-green-50 p-2 rounded text-center"><span class="block text-green-600 font-bold text-lg">${k.kehadiran.hadir}%</span><span class="text-xs text-green-700">Hadir</span></div>
                                <div class="bg-blue-50 p-2 rounded text-center"><span class="block text-blue-600 font-bold text-lg">${k.kehadiran.izin}%</span><span class="text-xs text-blue-700">Izin</span></div>
                                <div class="bg-yellow-50 p-2 rounded text-center"><span class="block text-yellow-600 font-bold text-lg">${k.kehadiran.sakit}%</span><span class="text-xs text-yellow-700">Sakit</span></div>
                                <div class="bg-red-50 p-2 rounded text-center"><span class="block text-red-600 font-bold text-lg">${k.kehadiran.alpa}%</span><span class="text-xs text-red-700">Alpa</span></div>
                            </div>` : '<p class="text-sm text-gray-400 italic text-center py-4">N/A &mdash; Belum ada data kehadiran</p>'}
                        </div>
                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Rata-Rata Ketercapaian se-Desa</p>
                            ${adaCapaian ? `
                            <div class="mb-2">
                                <div class="flex justify-between text-sm mb-1"><span>Target Kelas</span><span class="font-bold text-blue-600">${k.ketercapaian_global}%</span></div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5"><div class="bg-blue-500 h-2.5 rounded-full" style="width: ${k.ketercapaian_global}%"></div></div>
                            </div>
                            ${kategoriHTML ? `<div class="space-y-1 text-xs mt-3 bg-gray-50 p-2 rounded border border-gray-100">${kategoriHTML}</div>` : ''}`
                            : '<p class="text-sm text-gray-400 italic text-center py-4">N/A &mdash; Belum ada data capaian materi</p>'}
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
        let validGroupHadirCount = 0;
        let validGroupCapaianCount = 0;
        let cardsHTML = '';

        kelompokArray.forEach(k => {
            let totalHadir = 0,
                totalCapaian = 0;
            let countHadir = 0,
                countCapaian = 0;

            if (k.detail_kelas && k.detail_kelas.length > 0) {
                k.detail_kelas.forEach(kelas => {
                    if (kelas.kehadiran && kelas.kehadiran.hadir !== null && kelas.kehadiran.hadir !== undefined) {
                        totalHadir += parseFloat(kelas.kehadiran.hadir);
                        countHadir++;
                    }
                    if (kelas.ketercapaian_global !== null && kelas.ketercapaian_global !== undefined) {
                        totalCapaian += parseFloat(kelas.ketercapaian_global);
                        countCapaian++;
                    }
                });
            }

            let avgHadir = countHadir > 0 ? Math.round(totalHadir / countHadir) : null;
            let avgCapaian = countCapaian > 0 ? Math.round(totalCapaian / countCapaian) : null;

            if (avgHadir !== null) {
                sumGroupHadir += avgHadir;
                validGroupHadirCount++;
            }
            if (avgCapaian !== null) {
                sumGroupCapaian += avgCapaian;
                validGroupCapaianCount++;
            }

            let strHadir = avgHadir !== null ? `${avgHadir}%` : '<span class="text-sm italic text-gray-500">N/A</span>';
            let strCapaian = avgCapaian !== null ? `${avgCapaian}%` : '<span class="text-sm italic text-gray-500">N/A</span>';

            cardsHTML += `
                <div class="border border-gray-200 rounded-xl p-4 bg-white shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-blue-400"></div>
                    <h4 class="font-bold text-gray-800 text-center mb-4 mt-1 text-lg">${toUcwords(k.nama_kelompok)}</h4>
                    
                    <div class="bg-green-50 rounded-lg p-2 flex justify-between items-center mb-2 border border-green-100">
                        <span class="text-xs text-green-700 uppercase font-bold"><i class="fa-solid fa-user-check mr-1"></i> Kehadiran</span>
                        <span class="font-bold text-green-600 text-lg">${strHadir}</span>
                    </div>
                    
                    <div class="bg-blue-50 rounded-lg p-2 flex justify-between items-center border border-blue-100">
                        <span class="text-xs text-blue-700 uppercase font-bold"><i class="fa-solid fa-book-open mr-1"></i> Capaian</span>
                        <span class="font-bold text-blue-600 text-lg">${strCapaian}</span>
                    </div>
                </div>
            `;
        });

        let grandAvgHadir = validGroupHadirCount > 0 ? Math.round(sumGroupHadir / validGroupHadirCount) : null;
        let grandAvgCapaian = validGroupCapaianCount > 0 ? Math.round(sumGroupCapaian / validGroupCapaianCount) : null;

        let strGrandHadir = grandAvgHadir !== null ? `${grandAvgHadir}%` : '<span class="text-xl italic">N/A</span>';
        let strGrandCapaian = grandAvgCapaian !== null ? `${grandAvgCapaian}%` : '<span class="text-xl italic">N/A</span>';

        let grandCardHTML = `
            <div class="col-span-full border-2 border-blue-600 rounded-xl p-5 bg-blue-50 shadow-md flex flex-col sm:flex-row items-center justify-between mb-2">
                <div class="mb-4 sm:mb-0 text-center sm:text-left">
                    <h4 class="font-black text-blue-800 text-xl"><i class="fa-solid fa-building-flag mr-2"></i> Rata-Rata Tingkat Desa</h4>
                    <p class="text-sm text-blue-600 font-medium mt-1">Akumulasi rata-rata yang memiliki data</p>
                </div>
                <div class="flex gap-4 w-full sm:w-auto">
                    <div class="bg-white rounded-xl p-3 flex-1 sm:w-36 flex flex-col items-center border-2 border-green-200 shadow-sm">
                        <span class="text-xs text-green-600 uppercase font-extrabold mb-1 tracking-wider">Kehadiran</span>
                        <span class="font-black text-green-600 text-3xl">${strGrandHadir}</span>
                    </div>
                    <div class="bg-white rounded-xl p-3 flex-1 sm:w-36 flex flex-col items-center border-2 border-blue-200 shadow-sm">
                        <span class="text-xs text-blue-600 uppercase font-extrabold mb-1 tracking-wider">Capaian</span>
                        <span class="font-black text-blue-700 text-3xl">${strGrandCapaian}</span>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = grandCardHTML + cardsHTML;
    }

    function renderDetailTiapKelompok(kelompokArray) {
        const container = document.getElementById('expanderDetailKelompok');
        container.innerHTML = '';

        if (!kelompokArray || kelompokArray.length === 0) {
            container.innerHTML = `<div class="text-center py-4 text-gray-500 italic">Data kelompok tidak tersedia.</div>`;
            return;
        }

        kelompokArray.forEach((k, index) => {
            let totalHadir = 0,
                totalCapaian = 0;
            let countHadir = 0,
                countCapaian = 0;
            let totalSiswa = 0,
                totalGuru = 0;
            let detailKelasHTML = '';

            if (k.detail_kelas && k.detail_kelas.length > 0) {
                k.detail_kelas.forEach(kelas => {
                    const adaMurid = parseInt(kelas.jml_siswa) > 0;
                    const adaKehadiran = kelas.kehadiran && kelas.kehadiran.hadir !== null;
                    const adaCapaian = kelas.ketercapaian_global !== null;

                    if (adaKehadiran) {
                        totalHadir += parseFloat(kelas.kehadiran.hadir);
                        countHadir++;
                    }
                    if (adaCapaian) {
                        totalCapaian += parseFloat(kelas.ketercapaian_global);
                        countCapaian++;
                    }
                    totalSiswa += parseInt(kelas.jml_siswa) || 0;
                    totalGuru += parseInt(kelas.jml_guru) || 0;

                    const naCell = `<span class="text-gray-400 italic text-xs">N/A</span>`;

                    detailKelasHTML += `
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors ${!adaMurid ? 'bg-gray-50/60' : ''}">
                            <td class="py-2.5 px-3 font-medium text-gray-700 whitespace-nowrap">
                                ${kelas.nama_kelas}
                                ${!adaMurid ? '<span class="ml-1 text-[10px] bg-gray-200 text-gray-500 px-1.5 py-0.5 rounded font-bold uppercase">N/A</span>' : ''}
                            </td>
                            <td class="py-2.5 px-3 text-center">${kelas.jml_siswa}</td>
                            <td class="py-2.5 px-3 text-center">${kelas.jml_guru}</td>
                            <td class="py-2.5 px-3 text-center font-semibold">${kelas.tatap_muka ?? 0}x</td>
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                ${adaKehadiran 
                                    ? `<span class="text-green-600 font-medium mr-1" title="Hadir">${kelas.kehadiran.hadir}%</span>|
                                       <span class="text-blue-600 font-medium mx-1" title="Izin">${kelas.kehadiran.izin}%</span>|
                                       <span class="text-yellow-600 font-medium mx-1" title="Sakit">${kelas.kehadiran.sakit}%</span>|
                                       <span class="text-red-600 font-medium ml-1" title="Alpa">${kelas.kehadiran.alpa}%</span>`
                                    : naCell}
                            </td>
                            <td class="py-2.5 px-3 text-center font-bold ${adaCapaian ? 'text-blue-600' : 'text-gray-400'}">
                                ${adaCapaian ? kelas.ketercapaian_global + '%' : naCell}
                            </td>
                        </tr>
                    `;
                });
            }

            let avgHadir = countHadir > 0 ? Math.round(totalHadir / countHadir) : null;
            let avgCapaian = countCapaian > 0 ? Math.round(totalCapaian / countCapaian) : null;

            let strHadir = avgHadir !== null ? `${avgHadir}%` : '<span class="text-xs italic text-gray-500">N/A</span>';
            let strCapaian = avgCapaian !== null ? `${avgCapaian}%` : '<span class="text-xs italic text-gray-500">N/A</span>';

            const checkPjp = k.checklist.pjp ? '<i class="fa-solid fa-circle-check text-green-500 text-lg"></i>' : '<i class="fa-solid fa-circle-xmark text-red-500 text-lg"></i>';
            const checkUnsur = k.checklist.unsur ? '<i class="fa-solid fa-circle-check text-green-500 text-lg"></i>' : '<i class="fa-solid fa-circle-xmark text-red-500 text-lg"></i>';

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

            let ttdBadge = '';
            if (k.status === 'TTD_KETUA') {
                ttdBadge = `<span class="ml-2 bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"><i class="fa-solid fa-check mr-0.5"></i>Sudah TTD</span>`;
            } else if (k.status === 'FINAL') {
                ttdBadge = `<span class="ml-2 bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"><i class="fa-solid fa-clock mr-0.5"></i>Menunggu TTD</span>`;
            } else {
                ttdBadge = `<span class="ml-2 bg-yellow-100 text-yellow-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"><i class="fa-solid fa-pen mr-0.5"></i>Draft</span>`;
            }

            const html = `
                <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <button type="button" onclick="toggleExpander('${accordionId}', '${iconId}')" class="w-full bg-white px-5 py-3.5 flex justify-between items-center hover:bg-gray-50 transition-colors border-b border-transparent">
                        <div class="flex items-center flex-wrap gap-1">
                            <i class="fa-solid fa-users text-blue-600 mr-2"></i>
                            <span class="font-bold text-gray-800 text-base">Kelompok ${toUcwords(k.nama_kelompok)}</span>
                            ${ttdBadge}
                        </div>
                        <i id="${iconId}" class="fa-solid fa-chevron-down text-gray-400 transition-transform duration-300 flex-shrink-0 ml-2"></i>
                    </button>
                    
                    <div id="${accordionId}" class="hidden p-5 bg-gray-50/50 space-y-5 border-t border-gray-200">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-3"><i class="fa-solid fa-chart-simple mr-1"></i> Data Kelompok</p>
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
                                        <span class="font-bold text-green-600">${strHadir}</span>
                                    </div>
                                    <div class="bg-blue-50 p-2 rounded-lg border border-blue-100">
                                        <span class="block text-blue-700 text-[10px] uppercase font-bold">Capaian Materi</span>
                                        <span class="font-bold text-blue-600">${strCapaian}</span>
                                    </div>
                                </div>
                            </div>
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
                            <div class="bg-red-50 p-4 rounded-xl shadow-sm border border-red-100 h-full max-h-[160px] overflow-y-auto">
                                <p class="text-xs font-bold text-red-800 uppercase mb-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Permasalahan</p>
                                ${masalahHTML}
                            </div>
                        </div>

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
                                            <th class="py-3 px-3 font-semibold text-center">Pertemuan</th>
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

    // Fungsi Proses TTD
    function prosesTandaTangan() {
        const laporanDesaId = document.getElementById('laporan_desa_id_ketua').value;

        Swal.fire({
            title: 'Masukkan PIN Anda',
            text: "Untuk mengesahkan laporan ini, silakan masukkan PIN keamanan Anda.",
            input: 'password',
            inputAttributes: {
                autocapitalize: 'off',
                autocorrect: 'off',
                placeholder: '******'
            },
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Verifikasi & Sahkan',
            cancelButtonText: 'Batal',
            preConfirm: (pin) => {
                if (!pin) {
                    Swal.showValidationMessage('PIN tidak boleh kosong');
                }
                return pin;
            }
        }).then(async (result) => {
            if (result.isConfirmed) {
                const pin = result.value;
                Swal.fire({
                    title: 'Memverifikasi...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const formData = new FormData();
                formData.append('laporan_desa_id', laporanDesaId);
                formData.append('pin', pin);

                try {
                    const response = await fetch('pages/laporan_desa/ajax_review_laporan_desa.php?action=tanda_tangan', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();

                    if (res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => {
                            loadDataLaporanKetuaDesa();
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

    // Fungsi Proses Revisi/Tolak
    function prosesTolakRevisi() {
        const laporanDesaId = document.getElementById('laporan_desa_id_ketua').value;

        Swal.fire({
            title: 'Kembalikan untuk Revisi?',
            text: "Status laporan Desa akan dikembalikan menjadi DRAFT. Admin Desa harus memperbaiki catatan/evaluasi dan mengajukannya kembali.",
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
                formData.append('laporan_desa_id', laporanDesaId);

                try {
                    const response = await fetch('pages/laporan_desa/ajax_review_laporan_desa.php?action=tolak_laporan', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();

                    if (res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => {
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