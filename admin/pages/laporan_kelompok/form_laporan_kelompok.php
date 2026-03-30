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
        <button onclick="history.back()" class="text-gray-500 hover:text-primary text-sm mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
        </button>
        <h2 class="text-2xl font-bold text-gray-900">Form Pengisian Laporan PJP</h2>
        <p class="text-sm text-gray-500 mt-1" id="infoPeriodeKelompok">Memuat informasi...</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <!-- Tombol Refresh Data (Hanya muncul saat DRAFT) -->
        <button type="button" id="btnRefresh" onclick="refreshDataLaporan()" class="hidden bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-3 py-2 rounded-lg shadow-sm font-medium text-sm transition-colors">
            <i class="fa-solid fa-arrows-rotate"></i> <span class="hidden sm:inline ml-1">Refresh Data Sistem</span>
        </button>
        <div id="statusBadge" class="bg-gray-100 text-gray-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat...
        </div>
    </div>
</div>

<form id="formLaporanPjp" class="space-y-6 hidden">
    <!-- Simpan ID laporan diam-diam -->
    <input type="hidden" id="laporan_id_input" value="">

    <!-- Grid 2 Kolom untuk Kepengurusan & Checklist -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Card 1: Data Kepengurusan (Readonly) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="font-bold text-gray-800"><i class="fa-solid fa-sitemap text-primary mr-2"></i> Data Kepengurusan</h3>
                <span class="text-xs text-gray-400" title="Data diambil otomatis">*Otomatis</span>
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
                <h3 class="font-bold text-gray-800"><i class="fa-solid fa-list-check text-primary mr-2"></i> Checklist Musyawarah</h3>
            </div>
            <div class="p-6 space-y-4">
                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                    <input type="checkbox" id="checkMusyawarahPjp" class="w-5 h-5 text-primary rounded border-gray-300 focus:ring-primary">
                    <span class="ml-3 font-medium text-gray-700">Musyawarah PJP Kelompok Telah Dilaksanakan</span>
                </label>
                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                    <input type="checkbox" id="checkMusyawarahUnsur" class="w-5 h-5 text-primary rounded border-gray-300 focus:ring-primary">
                    <span class="ml-3 font-medium text-gray-700">Musyawarah Lima Unsur Telah Dilaksanakan</span>
                </label>
            </div>
        </div>

    </div>

    <!-- NEW CARD: Rekapitulasi Rata-Rata Kelompok -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-800"><i class="fa-solid fa-chart-pie text-primary mr-2"></i> Rekapitulasi Rata-Rata Tingkat Kelompok</h3>
            <span class="text-xs text-gray-400">*Dihitung otomatis</span>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="containerRekapKelompok">
                <div class="text-center py-4 text-gray-400 col-span-2"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block"></i> Kalkulasi data...</div>
            </div>
        </div>
    </div>

    <!-- Card 3: Detail Per Kelas -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-800"><i class="fa-solid fa-chalkboard-user text-primary mr-2"></i> Detail Per Kelas</h3>
            <span class="text-xs text-gray-400" title="Kehadiran dan Ketercapaian diambil dari sistem">*Otomatis & Manual</span>
        </div>

        <div class="p-6 space-y-6" id="containerKelas">
            <!-- Kelas Item akan di-render oleh JS -->
            <div class="text-center py-4 text-gray-400"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block"></i> Memuat Data Kelas...</div>
        </div>
    </div>

    <!-- Card 4: Permasalahan -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-800"><i class="fa-solid fa-triangle-exclamation text-red-500 mr-2"></i> Permasalahan yang Dihadapi</h3>
            <button type="button" onclick="tambahPermasalahan()" id="btnTambahMasalah" class="text-sm bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg transition-colors shadow-sm">
                <i class="fa-solid fa-plus mr-1"></i> Tambah Poin
            </button>
        </div>
        <div class="p-6">
            <div id="containerPermasalahan" class="space-y-3">
                <!-- Input permasalahan akan di-generate oleh JS disini -->
            </div>
            <div id="emptyPermasalahan" class="hidden text-center py-6 text-gray-400 text-sm">
                <i class="fa-regular fa-folder-open text-3xl mb-2 block"></i>
                Belum ada data permasalahan yang diinput.
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 pt-4 border-t border-gray-200" id="containerAksi">
        <button type="button" id="btnDraft" onclick="simpanLaporan('DRAFT')" class="px-6 py-2.5 bg-white border-2 border-primary text-primary hover:bg-teal-50 font-medium rounded-lg shadow-sm transition-colors">
            <i class="fa-regular fa-floppy-disk mr-2"></i> Simpan Draft
        </button>
        <button type="button" id="btnFinal" onclick="simpanLaporan('FINAL')" class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white font-medium rounded-lg shadow-sm transition-colors">
            <i class="fa-solid fa-paper-plane mr-2"></i> Simpan Final
        </button>
    </div>
</form>

<!-- JS Logic Form -->
<script>
    // State Global
    let currentData = {};
    let permasalahanCount = 0;

    document.addEventListener('DOMContentLoaded', () => {
        if (PERIODE_ID) {
            loadDataLaporan();
        }
    });

    // 1. Fungsi Mengambil Data Laporan
    async function loadDataLaporan() {
        try {
            const response = await fetch(`pages/laporan_kelompok/ajax_laporan_kelompok.php?action=get_laporan&periode_id=${PERIODE_ID}`);
            const result = await response.json();

            if (result.status === 'success') {
                currentData = result.data;
                document.getElementById('laporan_id_input').value = currentData.laporan_id;
                document.getElementById('infoPeriodeKelompok').innerHTML = `Mengisi data laporan untuk periode terpilih.`;

                // Tampilkan Form
                document.getElementById('formLaporanPjp').classList.remove('hidden');

                populateForm(currentData);
                updateUIByStatus(currentData.status);
            } else {
                Swal.fire('Error!', result.message, 'error').then(() => history.back());
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Gagal!', 'Terjadi kesalahan saat memuat form laporan.', 'error');
        }
    }

    // 2. Fungsi Mengisi UI dengan Data dari Backend
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

        // C. Rekapitulasi Rata-Rata Tingkat Kelompok (Otomatis dari JS)
        renderRekapKelompok(data.detail_kelas);

        // D. Detail Kelas
        renderDetailKelas(data.detail_kelas);

        // E. Permasalahan
        const containerMasalah = document.getElementById('containerPermasalahan');
        containerMasalah.innerHTML = '';
        permasalahanCount = 0;

        if (data.permasalahan && data.permasalahan.length > 0) {
            data.permasalahan.forEach(text => tambahPermasalahan(text));
        } else {
            tambahPermasalahan(); // Tambah 1 kosong jika array kosong
        }
    }

    // 3. Render HTML untuk Rekapitulasi Rata-rata Kelompok
    function renderRekapKelompok(kelasArray) {
        if (!kelasArray || kelasArray.length === 0) return;

        let totalHadir = 0,
            totalIzin = 0,
            totalSakit = 0,
            totalAlpa = 0;
        let totalCapaian = 0;
        let count = kelasArray.length;

        kelasArray.forEach(k => {
            totalHadir += (parseFloat(k.kehadiran.hadir) || 0);
            totalIzin += (parseFloat(k.kehadiran.izin) || 0);
            totalSakit += (parseFloat(k.kehadiran.sakit) || 0);
            totalAlpa += (parseFloat(k.kehadiran.alpa) || 0);
            totalCapaian += (parseFloat(k.ketercapaian_global) || 0);
        });

        // Kalkulasi Rata-rata & Pencegahan NaN jika count = 0
        let avgHadir = count > 0 ? Math.round(totalHadir / count) : 0;
        let avgIzin = count > 0 ? Math.round(totalIzin / count) : 0;
        let avgSakit = count > 0 ? Math.round(totalSakit / count) : 0;
        let avgAlpa = count > 0 ? Math.round(totalAlpa / count) : 0;
        let avgCapaian = count > 0 ? Math.round(totalCapaian / count) : 0;

        const container = document.getElementById('containerRekapKelompok');
        container.innerHTML = `
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-center">
                <p class="text-sm font-bold text-gray-700 uppercase mb-4 text-center tracking-wider">Kehadiran Peserta Didik</p>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-green-50 p-4 rounded-xl text-center border border-green-100 shadow-sm">
                        <span class="block text-green-600 font-bold text-2xl mb-1">${avgHadir}%</span>
                        <span class="text-[10px] text-green-700 font-extrabold uppercase tracking-widest block">Hadir</span>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-xl text-center border border-blue-100 shadow-sm">
                        <span class="block text-blue-600 font-bold text-2xl mb-1">${avgIzin}%</span>
                        <span class="text-[10px] text-blue-700 font-extrabold uppercase tracking-widest block">Izin</span>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-xl text-center border border-yellow-100 shadow-sm">
                        <span class="block text-yellow-600 font-bold text-2xl mb-1">${avgSakit}%</span>
                        <span class="text-[10px] text-yellow-700 font-extrabold uppercase tracking-widest block">Sakit</span>
                    </div>
                    <div class="bg-red-50 p-4 rounded-xl text-center border border-red-100 shadow-sm">
                        <span class="block text-red-600 font-bold text-2xl mb-1">${avgAlpa}%</span>
                        <span class="text-[10px] text-red-700 font-extrabold uppercase tracking-widest block">Alpa</span>
                    </div>
                </div>
            </div>

            <!-- Capaian Materi dengan Grafik Lingkaran SVG -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-center items-center">
                <p class="text-sm font-bold text-gray-700 uppercase mb-6 text-center tracking-wider">Capaian Kurikulum Global</p>
                <div class="relative flex items-center justify-center mb-2">
                    <svg class="transform -rotate-90 w-36 h-36">
                        <circle cx="72" cy="72" r="62" stroke="#f3f4f6" stroke-width="14" fill="transparent" />
                        <circle cx="72" cy="72" r="62" stroke="#0f766e" stroke-width="14" fill="transparent" 
                                stroke-dasharray="389.5" stroke-dashoffset="${389.5 - (389.5 * avgCapaian / 100)}" 
                                stroke-linecap="round" class="transition-all duration-1000 ease-out" />
                    </svg>
                    <span class="absolute text-4xl font-black text-primary">${avgCapaian}%</span>
                </div>
                <p class="text-xs text-gray-500 mt-4 text-center bg-gray-50 px-4 py-2 rounded-full border border-gray-100">
                    <i class="fa-solid fa-circle-info text-primary mr-1"></i> Rata-rata dari ${count} kelas aktif
                </p>
            </div>
        `;
    }

    // 4. Render HTML untuk Detail per Kelas
    function renderDetailKelas(kelasArray) {
        const container = document.getElementById('containerKelas');
        container.innerHTML = '';

        kelasArray.forEach((k, index) => {
            // Render Kategori Ketercapaian
            let kategoriHTML = '';
            if (k.ketercapaian_kategori && Object.keys(k.ketercapaian_kategori).length > 0) {
                for (const [namaKat, nilai] of Object.entries(k.ketercapaian_kategori)) {
                    kategoriHTML += `<div class="flex justify-between"><span>${namaKat}</span> <span>${nilai}%</span></div>`;
                }
            } else {
                kategoriHTML = `<div class="text-gray-400 italic">Belum ada data jurnal</div>`;
            }

            const html = `
                <div class="border rounded-xl p-5 bg-gray-50/50">
                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200">
                        <h4 class="text-lg font-bold text-primary">${k.nama_kelas}</h4>
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

                        <!-- Input Manual -->
                        <div class="lg:col-span-2 bg-white p-4 rounded-lg shadow-sm border border-gray-100 flex flex-col justify-center space-y-4">
                            <div>
                                <p class="text-sm font-bold text-gray-700 mb-2">Penyelenggara KBM</p>
                                <div class="flex space-x-4">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="penyelenggara_${index}" value="desa" ${k.penyelenggara === 'desa' ? 'checked' : ''} class="text-primary focus:ring-primary input-penyelenggara">
                                        <span class="ml-2 text-sm">Desa</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="penyelenggara_${index}" value="kelompok" ${k.penyelenggara === 'kelompok' ? 'checked' : ''} class="text-primary focus:ring-primary input-penyelenggara">
                                        <span class="ml-2 text-sm">Kelompok</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Tatap Muka per Minggu</label>
                                <div class="flex items-center">
                                    <input type="number" id="tatap_muka_${index}" min="0" max="10" value="${k.tatap_muka}" class="border border-gray-300 rounded-lg px-3 py-1.5 w-24 focus:ring-primary focus:border-primary outline-none text-center input-tatapmuka">
                                    <span class="ml-2 text-sm text-gray-500">Kali / Minggu</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.innerHTML += html;
        });
    }

    // 5. Manajemen Input Permasalahan
    function tambahPermasalahan(textValue = '') {
        permasalahanCount++;
        const container = document.getElementById('containerPermasalahan');
        document.getElementById('emptyPermasalahan').classList.add('hidden');

        const div = document.createElement('div');
        div.className = 'flex items-start gap-2 animate-fade-in';
        div.id = `masalah_row_${permasalahanCount}`;

        div.innerHTML = `
            <div class="mt-2 text-gray-400"><i class="fa-solid fa-circle-dot text-[10px]"></i></div>
            <textarea rows="2" placeholder="Deskripsikan kendala atau permasalahan..." 
                class="input-permasalahan w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-primary focus:border-primary outline-none transition-all">${textValue}</textarea>
            <button type="button" onclick="hapusPermasalahan(${permasalahanCount})" 
                class="btn-hapus-masalah mt-1 p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Hapus baris">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        `;
        container.appendChild(div);

        if (currentData.status !== 'DRAFT') disableInputs();
    }

    function hapusPermasalahan(id) {
        document.getElementById(`masalah_row_${id}`)?.remove();
        if (document.getElementById('containerPermasalahan').children.length === 0) {
            document.getElementById('emptyPermasalahan').classList.remove('hidden');
        }
    }

    // 6. Mengumpulkan Data dari DOM dan Menyimpan ke Backend
    async function simpanLaporan(targetStatus) {
        let title = targetStatus === 'FINAL' ? 'Simpan Final Laporan?' : 'Simpan Draft?';
        let text = targetStatus === 'FINAL' ?
            'Jika difinalkan, laporan akan menunggu TTD. Admin Desa dapat melihat dan me-review laporan Anda.' :
            'Progres isian Anda akan disimpan sementara.';

        Swal.fire({
            title: title,
            text: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0f766e',
            cancelButtonColor: '#d33',
            confirmButtonText: targetStatus === 'FINAL' ? 'Ya, Finalkan!' : 'Simpan Draft',
            cancelButtonText: 'Batal'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menyimpan...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                // Gather Checklist
                const checklist = {
                    pjp: document.getElementById('checkMusyawarahPjp').checked,
                    unsur: document.getElementById('checkMusyawarahUnsur').checked
                };

                // Gather Permasalahan
                const masalahInputs = document.querySelectorAll('.input-permasalahan');
                const permasalahan = [];
                masalahInputs.forEach(input => {
                    if (input.value.trim() !== '') permasalahan.push(input.value.trim());
                });

                // Gather Detail Kelas
                const updatedDetailKelas = [...currentData.detail_kelas];
                updatedDetailKelas.forEach((k, index) => {
                    const tatapMukaVal = document.getElementById(`tatap_muka_${index}`).value;
                    const penyelenggaraEl = document.querySelector(`input[name="penyelenggara_${index}"]:checked`);

                    k.tatap_muka = parseInt(tatapMukaVal) || 0;
                    k.penyelenggara = penyelenggaraEl ? penyelenggaraEl.value : 'kelompok';
                });

                const formData = new FormData();
                formData.append('laporan_id', document.getElementById('laporan_id_input').value);
                formData.append('status', targetStatus);
                formData.append('checklist_musyawarah', JSON.stringify(checklist));
                formData.append('detail_kelas', JSON.stringify(updatedDetailKelas));
                formData.append('permasalahan', JSON.stringify(permasalahan));

                try {
                    const response = await fetch('pages/laporan_kelompok/ajax_laporan_kelompok.php?action=simpan_laporan', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();

                    if (res.status === 'success') {
                        currentData.status = targetStatus;
                        updateUIByStatus(targetStatus);
                        Swal.fire('Berhasil!', res.message, 'success');
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error!', 'Terjadi kesalahan komunikasi dengan server.', 'error');
                }
            }
        });
    }

    // 7. Refresh Data Live dari Backend
    function refreshDataLaporan() {
        Swal.fire({
            title: 'Refresh Data Sistem?',
            text: "Sistem akan mengambil ulang data Kepengurusan, Kehadiran, dan Ketercapaian materi terbaru. Isian manual Anda (Tatap muka & Masalah) tidak akan hilang.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#0f766e',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Sinkronkan',
            cancelButtonText: 'Batal'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menyinkronkan...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                try {
                    const formData = new FormData();
                    formData.append('laporan_id', document.getElementById('laporan_id_input').value);

                    const response = await fetch('pages/laporan_kelompok/ajax_laporan_kelompok.php?action=refresh_data', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();

                    if (res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => {
                            document.getElementById('formLaporanPjp').classList.add('hidden');
                            loadDataLaporan();
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

    // 8. Mengunci / Membuka Kunci UI Berdasarkan Status
    function updateUIByStatus(status) {
        const badge = document.getElementById('statusBadge');
        const formElements = document.querySelectorAll('#formLaporanPjp input, #formLaporanPjp textarea, .btn-hapus-masalah');
        const btnTambahMasalah = document.getElementById('btnTambahMasalah');
        const containerAksi = document.getElementById('containerAksi');
        const btnRefresh = document.getElementById('btnRefresh');

        if (status === 'DRAFT') {
            badge.className = 'bg-yellow-100 text-yellow-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
            badge.innerHTML = '<i class="fa-solid fa-pen mr-2"></i> Status: DRAFT';

            formElements.forEach(el => el.disabled = false);
            btnTambahMasalah.classList.remove('hidden');
            containerAksi.classList.remove('hidden');
            if (btnRefresh) btnRefresh.classList.remove('hidden');

        } else if (status === 'FINAL') {
            badge.className = 'bg-blue-100 text-blue-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
            badge.innerHTML = '<i class="fa-solid fa-clock mr-2"></i> Status: FINAL (Menunggu TTD)';
            disableInputs(formElements, btnTambahMasalah, containerAksi, btnRefresh);

        } else if (status === 'TTD_KETUA') {
            badge.className = 'bg-green-100 text-green-800 font-bold px-4 py-2 rounded-lg shadow-sm flex items-center';
            badge.innerHTML = '<i class="fa-solid fa-check-double mr-2"></i> Status: SELESAI (Sudah TTD)';
            disableInputs(formElements, btnTambahMasalah, containerAksi, btnRefresh);
        }
    }

    function disableInputs(elements, btnAdd, containerAction, btnRefresh) {
        if (elements) elements.forEach(el => el.disabled = true);
        if (btnAdd) btnAdd.classList.add('hidden');
        if (containerAction) containerAction.classList.add('hidden');
        if (btnRefresh) btnRefresh.classList.add('hidden');
    }
</script>