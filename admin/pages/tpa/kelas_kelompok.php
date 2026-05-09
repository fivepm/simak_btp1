<?php
// Pastikan file ini di-include dari index.php yang sudah memuat Tailwind CSS dan SweetAlert2
?>

<div class="container mx-auto relative px-2 sm:px-4 py-4 sm:py-6">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h3 class="text-gray-800 text-2xl font-bold">Pengaturan Master</h3>
            <p class="text-sm text-gray-500 mt-1">Kelola data Kelas dan Kelompok</p>
        </div>
    </div>

    <!-- Layout Grid: 1 Kolom di HP, 2 Kolom di Laptop -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- ========================================== -->
        <!-- KOTAK BAGIAN KELAS -->
        <!-- ========================================== -->
        <div class="bg-white p-4 sm:p-6 rounded-xl shadow-md border border-gray-100 relative">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
                <h4 class="text-lg font-semibold text-gray-700 flex items-center gap-2">
                    <i class="bi bi-door-open-fill text-blue-500"></i> Data Kelas
                </h4>
            </div>
            
            <!-- Form Tambah Kelas -->
            <div class="flex gap-2 mb-5">
                <input type="text" id="inputNamaKelas" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm" placeholder="Masukkan nama kelas baru...">
                <button type="button" id="btnTambahKelas" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2 text-sm shadow-sm">
                    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">Tambah</span>
                </button>
            </div>

            <!-- Container Data Kelas (Diisi oleh JS) -->
            <div id="containerDataKelas">
                <div class="text-center py-4 text-gray-500 text-sm">Memuat data...</div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- KOTAK BAGIAN KELOMPOK -->
        <!-- ========================================== -->
        <div class="bg-white p-4 sm:p-6 rounded-xl shadow-md border border-gray-100 relative">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
                <h4 class="text-lg font-semibold text-gray-700 flex items-center gap-2">
                    <i class="bi bi-people-fill text-green-500"></i> Data Kelompok
                </h4>
            </div>

            <!-- Form Tambah Kelompok -->
            <div class="flex gap-2 mb-5">
                <input type="text" id="inputNamaKelompok" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm" placeholder="Masukkan nama kelompok baru...">
                <button type="button" id="btnTambahKelompok" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2 text-sm shadow-sm">
                    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">Tambah</span>
                </button>
            </div>

            <!-- Container Data Kelompok (Diisi oleh JS) -->
            <div id="containerDataKelompok">
                <div class="text-center py-4 text-gray-500 text-sm">Memuat data...</div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadKelas();
    loadKelompok();

    // Fungsi Tambah Kelas
    async function prosesTambahKelas() {
        const input = document.getElementById('inputNamaKelas');
        const btn = document.getElementById('btnTambahKelas');
        
        if(input.value.trim() === '') {
            input.focus();
            return;
        }
        
        btn.disabled = true;
        await postData('add_kelas', { nama_kelas: input.value });
        
        input.value = '';
        btn.disabled = false;
        loadKelas();
    }

    // Trigger Klik & Tombol Enter (Kelas)
    document.getElementById('btnTambahKelas').addEventListener('click', prosesTambahKelas);
    document.getElementById('inputNamaKelas').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') prosesTambahKelas();
    });

    // Fungsi Tambah Kelompok
    async function prosesTambahKelompok() {
        const input = document.getElementById('inputNamaKelompok');
        const btn = document.getElementById('btnTambahKelompok');
        
        if(input.value.trim() === '') {
            input.focus();
            return;
        }
        
        btn.disabled = true;
        await postData('add_kelompok', { nama_kelompok: input.value });
        
        input.value = '';
        btn.disabled = false;
        loadKelompok();
    }

    // Trigger Klik & Tombol Enter (Kelompok)
    document.getElementById('btnTambahKelompok').addEventListener('click', prosesTambahKelompok);
    document.getElementById('inputNamaKelompok').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') prosesTambahKelompok();
    });
});

// Fungsi GET Data via AJAX
async function fetchData(action) {
    try {
        const response = await fetch(`pages/tpa/ajax_kelas_kelompok.php?action=${action}`);
        return await response.json();
    } catch (error) {
        console.error('Fetch Error:', error);
        return { status: 'error', message: 'Gagal terhubung ke server.' };
    }
}

// Fungsi POST Data via AJAX dengan SweetAlert
async function postData(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    for (let key in data) {
        formData.append(key, data[key]);
    }

    try {
        const response = await fetch('pages/tpa/ajax_kelas_kelompok.php', { method: 'POST', body: formData });
        const res = await response.json();
        
        if(res.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: res.message,
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Oops...', text: res.message });
        }
    } catch (error) {
        console.error('Post Error:', error);
        Swal.fire({ icon: 'error', title: 'Kesalahan Sistem', text: 'Gagal memproses data.' });
    }
}

// Render UI Kelas (Mendukung 2 Layout: Desktop & Mobile)
async function loadKelas() {
    const res = await fetchData('get_kelas');
    const container = document.getElementById('containerDataKelas');
    
    if(res.status === 'success' && res.data.length > 0) {
        let tableRows = '';
        let cardList = '';
        
        res.data.forEach((item, index) => {
            const no = index + 1;
            const btnHapus = `<button onclick="hapusData('delete_kelas', ${item.id}, 'loadKelas')" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-2 py-1 rounded transition-colors text-sm"><i class="bi bi-trash"></i> Hapus</button>`;
            
            // Baris untuk Tabel Desktop
            tableRows += `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">${no}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800">${item.nama_kelas}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm">${btnHapus}</td>
                </tr>`;
            
            // Card untuk List Mobile
            cardList += `
                <div class="flex justify-between items-center p-3 border border-gray-200 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors">
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">No. ${no}</span>
                        <span class="text-sm font-semibold text-gray-800">${item.nama_kelas}</span>
                    </div>
                    <div>${btnHapus}</div>
                </div>`;
        });

        container.innerHTML = `
            <!-- Bagian Desktop (Tabel) -->
            <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Kelas</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${tableRows}
                    </tbody>
                </table>
            </div>

            <!-- Bagian Mobile (Card List) -->
            <div class="block md:hidden space-y-2 max-h-[400px] overflow-y-auto pr-1">
                ${cardList}
            </div>
        `;
    } else {
        container.innerHTML = `<div class="text-center py-6 px-4 bg-gray-50 rounded-lg border border-dashed border-gray-300"><p class="text-gray-500 text-sm">Belum ada data kelas.</p></div>`;
    }
}

// Render UI Kelompok (Mendukung 2 Layout: Desktop & Mobile)
async function loadKelompok() {
    const res = await fetchData('get_kelompok');
    const container = document.getElementById('containerDataKelompok');
    
    if(res.status === 'success' && res.data.length > 0) {
        let tableRows = '';
        let cardList = '';
        
        res.data.forEach((item, index) => {
            const no = index + 1;
            const btnHapus = `<button onclick="hapusData('delete_kelompok', ${item.id}, 'loadKelompok')" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-2 py-1 rounded transition-colors text-sm"><i class="bi bi-trash"></i> Hapus</button>`;
            
            // Baris untuk Tabel Desktop
            tableRows += `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">${no}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800">${item.nama_kelompok}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm">${btnHapus}</td>
                </tr>`;
            
            // Card untuk List Mobile
            cardList += `
                <div class="flex justify-between items-center p-3 border border-gray-200 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors">
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">No. ${no}</span>
                        <span class="text-sm font-semibold text-gray-800">${item.nama_kelompok}</span>
                    </div>
                    <div>${btnHapus}</div>
                </div>`;
        });

        container.innerHTML = `
            <!-- Bagian Desktop (Tabel) -->
            <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Kelompok</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${tableRows}
                    </tbody>
                </table>
            </div>

            <!-- Bagian Mobile (Card List) -->
            <div class="block md:hidden space-y-2 max-h-[400px] overflow-y-auto pr-1">
                ${cardList}
            </div>
        `;
    } else {
        container.innerHTML = `<div class="text-center py-6 px-4 bg-gray-50 rounded-lg border border-dashed border-gray-300"><p class="text-gray-500 text-sm">Belum ada data kelompok.</p></div>`;
    }
}

// Fungsi Hapus menggunakan Swal Confirm
function hapusData(action, id, reloadFunc) {
    Swal.fire({
        title: 'Hapus data ini?',
        text: "Data yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then(async (result) => {
        if (result.isConfirmed) {
            await postData(action, { id: id });
            window[reloadFunc](); // Panggil ulang fungsi render terkait
        }
    });
}
</script>