<?php
// Pastikan koneksi database tersedia
if (!isset($conn)) die("Koneksi database gagal.");

// =========================================================
// READ DATA
// =========================================================
$mapel_list = [];
$sql = "SELECT * FROM master_materi ORDER BY id ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $mapel_list[] = $row;
    }
}
?>

<div class="container mx-auto space-y-6">

    <!-- Header Page -->
    <div class="flex flex-col md:flex-row justify-between items-center bg-white p-6 rounded-lg shadow-md border-l-4 border-indigo-600">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Master Materi Induk</h1>
            <p class="text-sm text-gray-500">Atur jenis materi pokok (Kategori) dan kelola daftar isinya.</p>
        </div>
        <div class="mt-4 md:mt-0">
            <button id="btnTambah" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow flex items-center gap-2 transition">
                <i class="fa-solid fa-plus"></i> Tambah Materi
            </button>
        </div>
    </div>

    <!-- Tabel Data -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 w-10 text-center">No</th>
                        <th class="px-6 py-3">Nama Materi Induk</th>
                        <th class="px-6 py-3 text-center">Tipe Input</th>
                        <th class="px-6 py-3 text-center">Satuan</th>
                        <th class="px-6 py-3 text-center">Daftar Isi</th> <!-- Kolom Baru -->
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($mapel_list)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                                <i class="fa-solid fa-book-open text-3xl mb-2"></i><br>
                                Belum ada data Materi Induk. Silakan tambah baru.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1;
                        foreach ($mapel_list as $m):
                            $json = htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-center"><?php echo $no++; ?></td>
                                <td class="px-6 py-4 font-bold text-gray-800 text-base">
                                    <?php echo htmlspecialchars($m['nama_kategori']); ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($m['tipe_input'] == 'RANGE'): ?>
                                        <span class="bg-purple-100 text-purple-700 text-xs px-2 py-1 rounded border border-purple-200">Range</span>
                                    <?php elseif ($m['tipe_input'] == 'CHECKLIST'): ?>
                                        <span class="bg-orange-100 text-orange-700 text-xs px-2 py-1 rounded border border-orange-200">Checklist</span>
                                    <?php else: ?>
                                        <span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded border border-blue-200">Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php echo $m['satuan_default'] ? htmlspecialchars($m['satuan_default']) : '-'; ?>
                                </td>

                                <!-- KOLOM TOMBOL KE HALAMAN DETAIL -->
                                <td class="px-6 py-4 text-center">
                                    <a href="?page=kurikulum/materi_detail&master_id=<?php echo $m['id']; ?>" class="inline-flex items-center gap-1 bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                                        <i class="fa-solid fa-list-ul"></i> Kelola Isi
                                    </a>
                                </td>

                                <td class="px-6 py-4 text-right space-x-2">
                                    <button class="text-indigo-600 hover:text-indigo-900 btn-edit" data-json="<?php echo $json; ?>" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-900 btn-hapus" data-id="<?php echo $m['id']; ?>" data-nama="<?php echo htmlspecialchars($m['nama_kategori']); ?>" title="Hapus">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form (Sama seperti sebelumnya) -->
<div id="modalForm" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 hidden backdrop-blur-sm px-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100">
        <form id="formMateri">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="inputId">

            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 id="modalTitle" class="text-lg font-bold text-gray-800">Tambah Materi Induk</h3>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Materi Induk</label>
                    <input type="text" name="nama_kategori" id="inputNama" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Contoh: Makna Al-Quran" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Input Jurnal</label>
                    <select name="tipe_input" id="inputTipe" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500" onchange="toggleSatuan()">
                        <option value="RANGE">Range (Dari... Sampai...)</option>
                        <option value="CHECKLIST">Checklist (Poin Centang)</option>
                        <option value="MANUAL">Manual (Input Angka Volume)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1" id="hintTipe">Pilih cara guru mengisi jurnal.</p>
                </div>
                <div id="groupSatuan">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Satuan Hitung (Default)</label>
                    <input type="text" name="satuan_default" id="inputSatuan" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Contoh: Ayat, Halaman">
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition">Simpan</button>
                <button type="button" onclick="closeModal()" class="bg-white border border-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg hover:bg-gray-50 transition">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // === 1. FIX ANIMASI LOADING INDEX ===
        const indexOverlay = document.getElementById('loading-overlay');
        if (indexOverlay) {
            indexOverlay.classList.remove('show');
            indexOverlay.style.display = '';
        }

        // === 2. EVENT NAVIGASI ===
        // Agar animasi loading muncul saat pindah ke Halaman Detail
        document.querySelectorAll('.navigasi-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (indexOverlay) indexOverlay.classList.add('show');
                const href = this.getAttribute('href');
                setTimeout(() => window.location.href = href, 300);
            });
        });
    });

    const modal = document.getElementById('modalForm');
    const form = document.getElementById('formMateri');
    const title = document.getElementById('modalTitle');
    const action = document.getElementById('formAction');
    const inputId = document.getElementById('inputId');
    const inputNama = document.getElementById('inputNama');
    const inputTipe = document.getElementById('inputTipe');
    const inputSatuan = document.getElementById('inputSatuan');
    const hintTipe = document.getElementById('hintTipe');

    function openModal() {
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    window.toggleSatuan = function() {
        const val = inputTipe.value;
        const groupSatuan = document.getElementById('groupSatuan');
        if (val === 'RANGE') {
            hintTipe.textContent = 'Cocok untuk materi berurutan (Ayat/Halaman).';
            groupSatuan.classList.remove('hidden');
            inputSatuan.placeholder = 'Contoh: Ayat, Halaman';
        } else if (val === 'CHECKLIST') {
            hintTipe.textContent = 'Cocok untuk Tata Krama/Adab (Selesai/Belum).';
            groupSatuan.classList.add('hidden');
            inputSatuan.value = '';
        } else {
            hintTipe.textContent = 'Cocok untuk Hafalan.';
            groupSatuan.classList.remove('hidden');
            inputSatuan.placeholder = 'Contoh: Baris, Juz';
        }
    }

    document.getElementById('btnTambah').addEventListener('click', () => {
        form.reset();
        action.value = 'tambah';
        inputId.value = '';
        title.textContent = 'Tambah Materi Induk';
        toggleSatuan();
        openModal();
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-edit');
        if (btn) {
            const data = JSON.parse(btn.dataset.json);
            action.value = 'edit';
            inputId.value = data.id;
            title.textContent = 'Edit Materi Induk';
            inputNama.value = data.nama_kategori;
            inputTipe.value = data.tipe_input;
            inputSatuan.value = data.satuan_default;
            toggleSatuan();
            openModal();
        }
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // Mencegah event bubbling ke Index

        Swal.fire({
            title: 'Menyimpan...',
            didOpen: () => Swal.showLoading()
        });
        const formData = new FormData(form);
        fetch('pages/kurikulum/proses_master_materi.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                // Kita tidak perlu hideGlobalOverlay disini karena sudah di preventDefault()
                // Tapi Swal akan menutup loadingnya sendiri saat di-replace
                if (data.status === 'success') {
                    closeModal();
                    Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Sistem error', 'error'));
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-hapus');
        if (btn) {
            Swal.fire({
                title: 'Hapus Materi Induk?',
                text: `Yakin hapus "${btn.dataset.nama}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        didOpen: () => Swal.showLoading()
                    });
                    const formData = new FormData();
                    formData.append('action', 'hapus');
                    formData.append('id', btn.dataset.id);
                    fetch('pages/kurikulum/proses_master_materi.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                        icon: 'success',
                                        title: 'Terhapus!',
                                        text: data.message,
                                        timer: 1500,
                                        showConfirmButton: false
                                    })
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Gagal', data.message, 'error');
                            }
                        });
                }
            });
        }
    });
</script>