<?php
// Pastikan file ini di-include dari index.php
?>

<div class="container mx-auto relative px-2 sm:px-4 py-4 sm:py-6">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h3 class="text-gray-800 text-2xl font-bold">Struktur Kepengurusan</h3>
            <p class="text-sm text-gray-500 mt-1">Kelola data Ketua, Pengawas, Wakil, Sekretaris, dan Bendahara</p>
        </div>
    </div>

    <!-- Kotak Utama Data -->
    <div id="containerData" class="space-y-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="text-center py-10 text-gray-500">Memuat data... <i class="fa-solid fa-spinner fa-spin ml-2"></i></div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL TAMBAH PENGURUS                      -->
<!-- ========================================== -->
<div id="modalTambah" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex justify-center items-center backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
        
        <!-- Header Modal -->
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h3 class="text-lg font-bold text-gray-800"><i class="fa-solid fa-user-plus text-blue-500 me-2"></i> Tambah Pengurus</h3>
            <button onclick="tutupModal()" class="text-gray-400 hover:text-red-500 transition-colors text-xl font-bold">
                &times;
            </button>
        </div>

        <!-- Body Modal -->
        <div class="px-6 py-4 space-y-4">
            <!-- Alert Info -->
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg text-xs">
                <i class="fa-solid fa-circle-info me-1"></i> Data <strong>Ketua PJP</strong> disinkronisasi secara otomatis melalui tombol Sinkron di halaman utama.
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Dapukan <span class="text-red-500">*</span></label>
                <select id="inputJabatan" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-white">
                    <option value="">-- Pilih Dapukan --</option>
                    <option value="Pengawas">Pengawas</option>
                    <option value="Wakil">Wakil Ketua</option>
                    <option value="Sekretaris">Sekretaris</option>
                    <option value="Bendahara">Bendahara</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" id="inputNama" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm" placeholder="Contoh: Budi Santoso">
            </div>
        </div>

        <!-- Footer Modal -->
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-gray-50 rounded-b-xl">
            <button onclick="tutupModal()" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium transition-colors">Batal</button>
            <button type="button" id="btnSimpan" onclick="simpanData()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i> Simpan
            </button>
        </div>
        
    </div>
</div>

<script>
const URL_AJAX = 'pages/tpa/ajax_kepengurusan.php';

document.addEventListener('DOMContentLoaded', function() {
    loadData();
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
        document.getElementById('inputJabatan').value = '';
        document.getElementById('inputNama').value = '';
    }, 300);
}

// --- RENDER DATA KEPENGURUSAN ---
async function loadData() {
    const container = document.getElementById('containerData');

    try {
        const response = await fetch(`${URL_AJAX}?action=get_data`);
        const res = await response.json();
        
        if (res.status === 'success') {
            let html = '';
            const data = res.data;

            if (res.admin_tingkat === 'desa') {
                // 1. Rendering Bagian Desa (Editable)
                html += renderEditableSection('Desa', data.desa);

                // 2. Rendering Bagian Kelompok (Read-Only List)
                html += `
                    <div class="mt-8 border-t border-gray-200 pt-6">
                        <div class="flex items-center gap-2 mb-4">
                            <i class="fa-solid fa-network-wired text-xl text-blue-600"></i>
                            <h4 class="text-xl font-bold text-gray-800 tracking-tight">Kepengurusan Kelompok</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                `;
                for (const [kelName, kelData] of Object.entries(data.kelompok)) {
                    html += renderReadOnlyCard(kelName, kelData);
                }
                html += `</div></div>`;
            } else {
                // Rendering Bagian Kelompok Admin Sendiri (Editable)
                const kelName = Object.keys(data.kelompok)[0];
                const kelData = data.kelompok[kelName];
                html += renderEditableSection(`Kelompok ${kelName}`, kelData);
            }

            container.innerHTML = html;
        } else {
            container.innerHTML = `<div class="text-center py-6 text-red-500 text-sm">${res.message}</div>`;
        }
    } catch (error) {
        console.error('Error load data:', error);
        container.innerHTML = `<div class="bg-white rounded-xl shadow-sm border border-gray-100"><div class="text-center py-6 text-red-500 text-sm">Gagal memuat data dari server.</div></div>`;
    }
}

// Komponen: Card Editable (Bisa ditambah & dihapus, Group by Koma)
function renderEditableSection(title, scopeData) {
    const namaKetua = scopeData.ketua ? scopeData.ketua.nama_pengurus : '<span class="text-gray-400 italic font-normal">Belum disinkronisasi</span>';
    
    let listPengurus = '';
    let hasPengurus = false;

    if (scopeData.pengurus) {
        for (const [jabatan, persons] of Object.entries(scopeData.pengurus)) {
            // Hanya render baris jika jabatan tersebut memiliki orang
            if (persons && persons.length > 0) {
                hasPengurus = true;
                
                // Rangkai nama-nama dengan koma dan tombol hapus kecil (x)
                let personsHtml = persons.map(p => {
                    return `<span class="inline-flex items-center text-sm font-semibold text-gray-800">
                                ${p.nama_pengurus}
                                <button onclick="hapusData(${p.id})" class="text-red-400 hover:text-red-600 focus:outline-none ml-1 rounded-full hover:bg-red-50 h-5 w-5 flex items-center justify-center transition-colors" title="Hapus ${p.nama_pengurus}">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </button>
                            </span>`;
                }).join('<span class="text-gray-800 mr-2 font-semibold">,</span>');

                listPengurus += `
                    <li class="py-3 flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4 group border-b border-gray-100 last:border-0">
                        <div class="w-full sm:w-1/4 sm:pt-0.5">
                            <span class="text-[11px] font-bold text-blue-600 uppercase tracking-wider block">${jabatan}</span>
                        </div>
                        <div class="w-full sm:w-3/4 flex flex-wrap items-center leading-relaxed">
                            ${personsHtml}
                        </div>
                    </li>
                `;
            }
        }
    }

    if (!hasPengurus) {
        listPengurus = '<div class="text-center py-6 text-sm text-gray-500 border border-dashed border-gray-200 rounded-lg">Belum ada pengurus lain yang ditambahkan.</div>';
    } else {
        listPengurus = `<ul class="">${listPengurus}</ul>`;
    }

    return `
    <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center gap-3">
            <i class="fa-solid fa-users-gear text-white text-xl"></i>
            <h4 class="font-bold text-white text-lg tracking-wide">Pengurus ${ucwords(title)}</h4>
        </div>
        
        <div class="p-6">
            <!-- Baris Ketua PJP (Sinkronisasi) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-blue-50/50 p-4 rounded-xl border border-blue-100 mb-6 gap-4">
                <div>
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block mb-1">Ketua PJP ${ucwords(title)}</span>
                    <span class="text-lg font-bold text-gray-900">${namaKetua}</span>
                </div>
                <button onclick="syncKetua()" class="w-full sm:w-auto bg-white border border-blue-200 hover:bg-blue-50 text-blue-700 px-4 py-2 rounded-lg text-sm font-bold shadow-sm flex justify-center items-center gap-2 transition-colors">
                    <i class="fa-solid fa-arrows-rotate"></i> Sinkron Ketua
                </button>
            </div>

            <!-- Bagian Pengurus Lainnya -->
            <div>
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
                    <h5 class="font-bold text-gray-700 text-sm uppercase tracking-wide">Jajaran Pengurus</h5>
                    <button onclick="bukaModal()" class="bg-green-50 text-green-700 hover:bg-green-600 hover:text-white border border-green-200 hover:border-green-600 font-bold text-xs px-3 py-1.5 rounded-lg flex items-center gap-1 transition-colors">
                        <i class="fa-solid fa-plus"></i> Tambah
                    </button>
                </div>
                ${listPengurus}
            </div>
        </div>
    </div>
    `;
}

const ucwords = (str) =>
  str.toLowerCase().replace(/\b\w/g, (char) => char.toUpperCase());

// Komponen: Card Read-Only (Hanya untuk Admin Desa melihat Kelompok, Group by Koma)
function renderReadOnlyCard(kelName, scopeData) {
    const namaKetua = scopeData.ketua ? scopeData.ketua.nama_pengurus : '<span class="text-gray-400 italic font-normal">Belum ada</span>';
    
    let listPengurus = '';
    let hasPengurus = false;
    
    if (scopeData.pengurus) {
        for (const [jabatan, persons] of Object.entries(scopeData.pengurus)) {
            if (persons && persons.length > 0) {
                hasPengurus = true;
                // Hanya join namanya dengan koma tanpa icon hapus
                let personsNames = persons.map(p => p.nama_pengurus).join(', ');
                listPengurus += `
                    <li class="py-2.5 flex flex-col border-b border-gray-50 last:border-0">
                        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">${jabatan}</span>
                        <span class="text-sm font-medium text-gray-800 leading-snug">${personsNames}</span>
                    </li>
                `;
            }
        }
    }

    if (!hasPengurus) {
        listPengurus = '<li class="py-4 text-xs text-center text-gray-400 italic border-t border-gray-50">Belum ada pengurus lain</li>';
    }

    return `
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
        <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex items-center gap-2">
            <i class="fa-solid fa-location-dot text-slate-500"></i>
            <h4 class="font-bold text-slate-700 text-base">${ucwords(kelName)}</h4>
        </div>
        <div class="p-4 flex-1">
            <div class="mb-3 pb-3 border-b border-dashed border-gray-200">
                <span class="text-[10px] font-bold text-indigo-600 uppercase block tracking-wider">Ketua PJP</span>
                <span class="text-sm font-bold text-gray-800">${namaKetua}</span>
            </div>
            <ul class="divide-y divide-gray-50">
                ${listPengurus}
            </ul>
        </div>
    </div>
    `;
}

// --- PROSES SINKRONISASI KETUA ---
async function syncKetua() {
    Swal.fire({
        title: 'Sinkronisasi Ketua',
        text: "Sistem akan mengambil nama Ketua PJP dari tabel Pengguna. Lanjutkan?",
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Sinkronkan!'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });

            try {
                const formData = new FormData();
                formData.append('action', 'sync_ketua');
                const response = await fetch(URL_AJAX, { method: 'POST', body: formData });
                const res = await response.json();
                
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, timer: 1500, showConfirmButton: false });
                    loadData();
                } else {
                    Swal.fire({ icon: 'error', title: 'Oops...', text: res.message });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Kesalahan', text: 'Koneksi ke backend gagal.' });
            }
        }
    });
}

// --- PROSES SIMPAN PENGURUS BARU ---
async function simpanData() {
    const jabatan = document.getElementById('inputJabatan').value;
    const nama = document.getElementById('inputNama').value;
    const btn = document.getElementById('btnSimpan');

    if (!jabatan || !nama) {
        Swal.fire({ icon: 'warning', title: 'Oops...', text: 'Silakan isi Jabatan dan Nama Lengkap!' });
        return;
    }

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

    const formData = new FormData();
    formData.append('action', 'add_pengurus');
    formData.append('jabatan', jabatan);
    formData.append('nama_pengurus', nama);

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

// --- PROSES HAPUS PENGURUS ---
function hapusData(id) {
    Swal.fire({
        title: 'Hapus Pengurus?',
        text: "Data pengurus ini akan dihapus permanen dari jabatan tersebut.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete_pengurus');
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