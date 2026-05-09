<?php
// Pastikan file ini di-include dari index.php
?>

<div class="container mx-auto relative px-2 sm:px-4 py-4 sm:py-6">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h3 class="text-gray-800 text-2xl font-bold">Penugasan Wali Kelas</h3>
            <p class="text-sm text-gray-500 mt-1">Pantau dan kelola Wali Kelas di setiap kelompok</p>
        </div>
        <button onclick="bukaModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition transform hover:scale-105 flex items-center gap-2 text-sm">
            <i class="fa-solid fa-circle-plus"></i> <span>Tugaskan Wali Kelas</span>
        </button>
    </div>

    <!-- Kotak Utama Data (Berisi Grid Card per Kelompok) -->
    <div id="containerData">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="text-center py-10 text-gray-500">Memuat data... <i class="fa-solid fa-spinner fa-spin ml-2"></i></div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL TAMBAH WALI KELAS (CASCADING)        -->
<!-- ========================================== -->
<div id="modalTambah" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex justify-center items-center backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
        
        <!-- Header Modal -->
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h3 class="text-lg font-bold text-gray-800"><i class="fa-solid fa-id-badge text-blue-500 me-2"></i> Tambah Penugasan</h3>
            <button onclick="tutupModal()" class="text-gray-400 hover:text-red-500 transition-colors text-xl font-bold">
                &times;
            </button>
        </div>

        <!-- Body Modal -->
        <div class="px-6 py-4 space-y-4">
            
            <!-- Container Kelompok (Hanya Muncul untuk Admin Desa) -->
            <div id="containerKelompok" class="hidden">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Kelompok <span class="text-red-500">*</span></label>
                <select id="inputKelompok" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-white"></select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Guru <span class="text-red-500">*</span></label>
                <select id="inputGuru" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-gray-100" disabled>
                    <option value="">-- Memuat Data --</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Kelas <span class="text-red-500">*</span></label>
                <select id="inputKelas" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-gray-100" disabled>
                    <option value="">-- Pilih Guru Terlebih Dahulu --</option>
                </select>
            </div>
            
        </div>

        <!-- Footer Modal -->
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-gray-50 rounded-b-xl">
            <button onclick="tutupModal()" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium transition-colors">Batal</button>
            <button type="button" id="btnSimpan" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i> Simpan
            </button>
        </div>
        
    </div>
</div>

<script>
const URL_AJAX = 'pages/tpa/ajax_wali_kelas.php';

document.addEventListener('DOMContentLoaded', function() {
    loadData();
    loadOptions();

    document.getElementById('btnSimpan').addEventListener('click', simpanData);

    // --- EVENT: Saat Kelompok Berubah (Khusus Admin Desa) ---
    document.getElementById('inputKelompok').addEventListener('change', async function() {
        const kel = this.value;
        const selectGuru = document.getElementById('inputGuru');
        const selectKelas = document.getElementById('inputKelas');
        
        // Reset Kelas
        selectKelas.innerHTML = '<option value="">-- Pilih Guru Terlebih Dahulu --</option>';
        selectKelas.disabled = true;
        selectKelas.classList.add('bg-gray-100');

        if(!kel) {
            selectGuru.innerHTML = '<option value="">-- Pilih Kelompok Terlebih Dahulu --</option>';
            selectGuru.disabled = true;
            selectGuru.classList.add('bg-gray-100');
            return;
        }

        selectGuru.innerHTML = '<option value="">-- Memuat Guru... --</option>';
        selectGuru.disabled = true;

        const formData = new FormData();
        formData.append('action', 'get_guru_by_kelompok');
        formData.append('kelompok', kel);

        try {
            const response = await fetch(URL_AJAX, { method: 'POST', body: formData });
            const res = await response.json();
            
            if(res.status === 'success') {
                let htmlGuru = '<option value="">-- Pilih Guru --</option>';
                if(res.data.length > 0) {
                    res.data.forEach(g => htmlGuru += `<option value="${g.id}">${g.nama}</option>`);
                    selectGuru.disabled = false;
                    selectGuru.classList.remove('bg-gray-100');
                } else {
                    htmlGuru = '<option value="">-- Tidak ada guru di kelompok ini --</option>';
                }
                selectGuru.innerHTML = htmlGuru;
            }
        } catch (e) {
            selectGuru.innerHTML = '<option value="">-- Gagal memuat data --</option>';
        }
    });

    // --- EVENT: Saat Guru Berubah ---
    document.getElementById('inputGuru').addEventListener('change', async function() {
        const idGuru = this.value;
        const selectKelas = document.getElementById('inputKelas');
        
        if(!idGuru) {
            selectKelas.innerHTML = '<option value="">-- Pilih Guru Terlebih Dahulu --</option>';
            selectKelas.disabled = true;
            selectKelas.classList.add('bg-gray-100');
            return;
        }

        selectKelas.innerHTML = '<option value="">-- Memuat Kelas... --</option>';
        selectKelas.disabled = true;

        const formData = new FormData();
        formData.append('action', 'get_kelas_by_guru');
        formData.append('id_guru', idGuru);

        try {
            const response = await fetch(URL_AJAX, { method: 'POST', body: formData });
            const res = await response.json();
            
            if(res.status === 'success') {
                let htmlKelas = '<option value="">-- Pilih Kelas --</option>';
                if(res.data.length > 0) {
                    res.data.forEach(k => htmlKelas += `<option value="${k.id}">${k.nama_kelas}</option>`);
                    selectKelas.disabled = false;
                    selectKelas.classList.remove('bg-gray-100');
                } else {
                    htmlKelas = '<option value="">-- Guru ini belum mengampu kelas apapun --</option>';
                }
                selectKelas.innerHTML = htmlKelas;
            }
        } catch (e) {
            selectKelas.innerHTML = '<option value="">-- Gagal memuat data --</option>';
        }
    });
});

// --- FUNGSI MODAL ---
function bukaModal() {
    const modal = document.getElementById('modalTambah');
    const content = document.getElementById('modalContent');
    modal.classList.remove('hidden');
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function tutupModal() {
    const modal = document.getElementById('modalTambah');
    const content = document.getElementById('modalContent');
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        // Reset Dropdowns
        const isDesa = document.getElementById('containerKelompok').classList.contains('hidden') === false;
        
        if (isDesa) {
            document.getElementById('inputKelompok').value = '';
            document.getElementById('inputGuru').innerHTML = '<option value="">-- Pilih Kelompok Terlebih Dahulu --</option>';
            document.getElementById('inputGuru').disabled = true;
            document.getElementById('inputGuru').classList.add('bg-gray-100');
        } else {
            document.getElementById('inputGuru').value = '';
        }

        document.getElementById('inputKelas').innerHTML = '<option value="">-- Pilih Guru Terlebih Dahulu --</option>';
        document.getElementById('inputKelas').disabled = true;
        document.getElementById('inputKelas').classList.add('bg-gray-100');
    }, 300);
}

// --- INISIALISASI OPTION ---
async function loadOptions() {
    try {
        const response = await fetch(`${URL_AJAX}?action=get_options`);
        const res = await response.json();
        
        if (res.status === 'success') {
            const selectGuru = document.getElementById('inputGuru');
            
            if (res.admin_tingkat === 'desa') {
                // Admin Desa: Tampilkan input Kelompok, kunci Guru
                document.getElementById('containerKelompok').classList.remove('hidden');
                
                let htmlKel = '<option value="">-- Pilih Kelompok --</option>';
                res.kelompok.forEach(k => htmlKel += `<option value="${k.nama_kelompok}">${k.nama_kelompok}</option>`);
                document.getElementById('inputKelompok').innerHTML = htmlKel;
                
                selectGuru.innerHTML = '<option value="">-- Pilih Kelompok Terlebih Dahulu --</option>';
                selectGuru.disabled = true;
                selectGuru.classList.add('bg-gray-100');
            } else {
                // Admin Kelompok: Sembunyikan input Kelompok, langsung isi Guru
                document.getElementById('containerKelompok').classList.add('hidden');
                
                let htmlGuru = '<option value="">-- Pilih Guru --</option>';
                if (res.guru && res.guru.length > 0) {
                    res.guru.forEach(g => htmlGuru += `<option value="${g.id}">${g.nama}</option>`);
                } else {
                    htmlGuru = '<option value="">-- Tidak ada guru --</option>';
                }
                selectGuru.innerHTML = htmlGuru;
                selectGuru.disabled = false;
                selectGuru.classList.remove('bg-gray-100');
            }
        }
    } catch (error) {
        console.error('Error load options:', error);
    }
}

// --- MENGAMBIL & MERENDER SEMUA KELAS (CHECKLIST PANDUAN) ---
async function loadData() {
    const container = document.getElementById('containerData');
    container.innerHTML = `<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"><div class="text-center py-10 text-gray-500">Memuat data... <i class="fa-solid fa-spinner fa-spin ml-2"></i></div></div>`;

    try {
        const response = await fetch(`${URL_AJAX}?action=get_data`);
        const res = await response.json();
        
        if(res.status === 'success' && Object.keys(res.data).length > 0) {
            let htmlGrid = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">';
            
            // Loop per kelompok
            for (const [kelompokName, kelasArray] of Object.entries(res.data)) {
                
                htmlGrid += `
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                    <div class="bg-blue-50 px-4 py-3 border-b border-blue-100 flex items-center gap-2">
                        <i class="fa-solid fa-location-dot text-blue-600"></i>
                        <h4 class="font-bold text-gray-800 text-base tracking-wide">${kelompokName}</h4>
                    </div>
                    <div class="p-0 flex-1">
                        <ul class="divide-y divide-gray-100">
                `;

                // Loop per kelas di dalam kelompok tersebut
                kelasArray.forEach(item => {
                    let statusWali = '';
                    let actionBtn = '';

                    // LOGIKA TAMPILAN
                    if (item.jml_murid === 0) {
                        // 1. Jika tidak ada murid
                        statusWali = `<span class="text-gray-400 text-xs italic"><i class="fa-solid fa-circle-info"></i> Tidak ada murid</span>`;
                        if (item.id_wali) {
                            statusWali += `<div class="font-semibold text-gray-600 text-sm mt-0.5">${item.nama_guru}</div>`;
                            // Tombol Hapus (Diberi text dan background merah tegas)
                            actionBtn = `<button onclick="hapusData(${item.id_wali})" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded text-xs font-bold transition-colors shadow flex items-center gap-1" title="Hapus Wali Kelas"><i class="fa-solid fa-trash"></i> Hapus</button>`;
                        }
                    } else if (!item.id_wali) {
                        // 2. Jika ada murid tapi belum ada wali
                        statusWali = `<span class="text-orange-500 font-medium text-xs bg-orange-50 px-2 py-0.5 rounded border border-orange-200"><i class="fa-solid fa-triangle-exclamation"></i> Belum ada Wali Kelas</span>`;
                    } else {
                        // 3. Normal (Ada murid, ada wali)
                        statusWali = `<div class="font-bold text-gray-800 text-sm"><i class="fa-solid fa-user-check text-green-500 mr-1"></i> ${item.nama_guru}</div>`;
                        // Tombol Hapus (Diberi text dan background merah tegas)
                        actionBtn = `<button onclick="hapusData(${item.id_wali})" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded text-xs font-bold transition-colors shadow flex items-center gap-1" title="Hapus Wali Kelas"><i class="fa-solid fa-trash"></i> Hapus</button>`;
                    }

                    // Tampilkan Jml Murid kecil di samping nama kelas
                    const badgeMurid = item.jml_murid > 0 ? `<span class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded ml-1">${item.jml_murid} Siswa</span>` : '';

                    htmlGrid += `
                            <li class="p-4 hover:bg-gray-50 flex justify-between items-center transition-colors group">
                                <div class="w-full">
                                    <div class="text-xs text-gray-500 mb-2 uppercase tracking-wider font-semibold">${item.nama_kelas} ${badgeMurid}</div>
                                    <div class="flex justify-between items-center">
                                        <div>${statusWali}</div>
                                        <div>${actionBtn}</div>
                                    </div>
                                </div>
                            </li>
                    `;
                });

                htmlGrid += `
                        </ul>
                    </div>
                </div>
                `;
            }

            htmlGrid += '</div>';
            container.innerHTML = htmlGrid;
            
        } else {
            container.innerHTML = `
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="text-center py-12 px-4">
                        <div class="bg-gray-100 h-16 w-16 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-folder-open text-2xl text-gray-400"></i></div>
                        <p class="text-gray-500 text-sm">Belum ada data kelompok/kelas.</p>
                    </div>
                </div>`;
        }
    } catch (error) {
        console.error('Error load data:', error);
        container.innerHTML = `<div class="bg-white rounded-xl shadow-sm border border-gray-100"><div class="text-center py-6 text-red-500 text-sm">Gagal memuat data dari server.</div></div>`;
    }
}

// --- PROSES SIMPAN ---
async function simpanData() {
    const idGuru = document.getElementById('inputGuru').value;
    const idKelas = document.getElementById('inputKelas').value;
    const tahunAjaran = '-'; 
    const btn = document.getElementById('btnSimpan');

    if (!idGuru || !idKelas) {
        Swal.fire({ icon: 'warning', title: 'Oops...', text: 'Silakan pilih Guru dan Kelas terlebih dahulu!' });
        return;
    }

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

    const formData = new FormData();
    formData.append('action', 'add_data');
    formData.append('id_guru', idGuru);
    formData.append('id_kelas', idKelas);
    formData.append('tahun_ajaran', tahunAjaran);

    try {
        const response = await fetch(URL_AJAX, { method: 'POST', body: formData });
        const res = await response.json();
        
        if (res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, timer: 1500, showConfirmButton: false });
            tutupModal();
            loadData(); 
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal', text: res.message });
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Kesalahan Server', text: 'Koneksi ke backend gagal.' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// --- PROSES HAPUS ---
function hapusData(id) {
    Swal.fire({
        title: 'Cabut Penugasan?',
        text: "Guru ini tidak lagi menjadi Wali untuk kelas tersebut.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, cabut!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete_data');
            formData.append('id', id);

            try {
                const response = await fetch(URL_AJAX, { method: 'POST', body: formData });
                const res = await response.json();
                
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Terhapus!', text: res.message, timer: 1500, showConfirmButton: false });
                    loadData(); 
                } else {
                    Swal.fire({ icon: 'error', title: 'Oops...', text: res.message });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Kesalahan Server', text: 'Gagal menghubungi server.' });
            }
        }
    });
}
</script>