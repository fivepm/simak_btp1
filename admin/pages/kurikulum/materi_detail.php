<?php
if (!isset($conn)) die("Koneksi database gagal.");

// 1. Ambil Parameter master_id dari URL
$selected_master_id = isset($_GET['master_id']) ? (int)$_GET['master_id'] : 0;

// Validasi: Jika tidak ada ID, kembalikan ke halaman master
if ($selected_master_id === 0) {
    echo "<script>window.location='?page=master_materi';</script>";
    exit;
}

// 2. Ambil Info Master Materi (Induk)
$stmt = $conn->prepare("SELECT * FROM master_materi WHERE id = ?");
$stmt->bind_param("i", $selected_master_id);
$stmt->execute();
$master_data = $stmt->get_result()->fetch_assoc();

if (!$master_data) {
    echo "<div class='bg-red-100 text-red-700 p-4 rounded'>Data Mata Pelajaran tidak ditemukan.</div>";
    exit;
}

// 3. Ambil Detail Materi
$detail_list = [];
$stmt_det = $conn->prepare("SELECT * FROM master_materi_detail WHERE master_materi_id = ? ORDER BY id ASC");
$stmt_det->bind_param("i", $selected_master_id);
$stmt_det->execute();
$result = $stmt_det->get_result();
while ($row = $result->fetch_assoc()) {
    $detail_list[] = $row;
}
?>

<div class="container mx-auto space-y-6">

    <!-- HEADER NAVIGATION -->
    <div class="flex items-center gap-4">
        <a href="?page=kurikulum/master_materi" class="bg-white p-3 rounded-full shadow hover:bg-gray-50 text-gray-600 transition">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Kelola Isi: <span class="text-indigo-600"><?php echo htmlspecialchars($master_data['nama_kategori']); ?></span>
            </h1>
            <p class="text-sm text-gray-500">
                Tipe Input: <strong class="uppercase text-indigo-600"><?php echo $master_data['tipe_input']; ?></strong>
                <?php if ($master_data['satuan_default']): ?>
                    | Satuan: <strong><?php echo $master_data['satuan_default']; ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- KONTEN TABEL -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-700">Daftar Materi / Surat / Poin</h3>
            <button id="btnTambah" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow text-sm flex items-center gap-2 transition">
                <i class="fa-solid fa-plus"></i> Tambah Item
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-white border-b">
                    <tr>
                        <th class="px-6 py-3 w-10 text-center">No</th>
                        <?php if ($master_data['tipe_input'] !== 'CHECKLIST'): ?>
                            <th class="px-6 py-3">Nama Surat / Kitab</th>
                        <?php else: ?>
                            <th class="px-6 py-3">Nama Poin / Bab</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 text-center">
                            Total Isi
                            <?php if ($master_data['tipe_input'] !== 'CHECKLIST'): ?>
                                <span class="text-xs text-gray-400 normal-case">(<?php echo $master_data['satuan_default'] ?? 'Unit'; ?>)</span>
                            <?php endif; ?>
                        </th>
                        <th class="px-6 py-3">Keterangan</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($detail_list)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                                <i class="fa-solid fa-list-ul text-3xl mb-2"></i><br>
                                Belum ada detail materi. Silakan tambah baru.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1;
                        foreach ($detail_list as $d):
                            $json = htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-center"><?php echo $no++; ?></td>
                                <td class="px-6 py-4 font-medium text-gray-900 text-base">
                                    <?php echo htmlspecialchars($d['judul_detail']); ?>
                                </td>
                                <td class="px-6 py-4 text-center font-bold text-indigo-600">
                                    <?php
                                    // LOGIKA TAMPILAN TABEL: Jika Checklist, tampilkan strip
                                    if ($master_data['tipe_input'] === 'CHECKLIST') {
                                        echo '<span class="text-gray-400">-</span>';
                                    } else {
                                        echo number_format($d['total_isi']);
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 text-gray-500">
                                    <?php echo htmlspecialchars($d['keterangan'] ?? '-'); ?>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button class="text-blue-600 hover:text-blue-800 btn-edit" data-json="<?php echo $json; ?>" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 btn-hapus" data-id="<?php echo $d['id']; ?>" data-judul="<?php echo htmlspecialchars($d['judul_detail']); ?>" title="Hapus">
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

<!-- MODAL FORM -->
<div id="modalForm" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 hidden backdrop-blur-sm px-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100">
        <form id="formDetail">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="inputId">

            <!-- Master ID (Hidden) -->
            <input type="hidden" name="master_materi_id" id="inputMasterId" value="<?php echo $selected_master_id; ?>">

            <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center">
                <h3 id="modalTitle" class="text-lg font-bold text-white">Tambah Detail Materi</h3>
                <button type="button" onclick="closeModal()" class="text-indigo-100 hover:text-white">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div class="bg-indigo-50 p-3 rounded text-sm text-indigo-800 mb-4">
                    Menambahkan ke: <strong><?php echo htmlspecialchars($master_data['nama_kategori']); ?></strong>
                    <br>
                    <span class="text-xs text-gray-500">Tipe: <?php echo $master_data['tipe_input']; ?></span>
                </div>

                <!-- Judul Detail -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Poin / Surat / Kitab</label>
                    <input type="text" name="judul_detail" id="inputJudul" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Contoh: Berbicara Sopan" required>
                </div>

                <!-- Total Isi (Hanya muncul jika BUKAN CHECKLIST) -->
                <div id="groupTotalIsi">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Total Isi (<span id="labelSatuan"><?php echo $master_data['satuan_default'] ?? 'Unit'; ?></span>)
                    </label>
                    <input type="number" name="total_isi" id="inputTotal" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Contoh: 286">
                    <p class="text-xs text-gray-500 mt-1">Masukkan angka batas akhir (Jml Ayat / Jml Halaman).</p>
                </div>

                <!-- Keterangan -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan (Opsional)</label>
                    <textarea name="keterangan" id="inputKet" rows="2" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
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
    const currentMasterType = "<?php echo $master_data['tipe_input']; ?>";

    document.addEventListener("DOMContentLoaded", function() {
        // 1. FIX ANIMASI LOADING INDEX
        const indexOverlay = document.getElementById('loading-overlay');
        if (indexOverlay) {
            indexOverlay.classList.remove('show');
            indexOverlay.style.display = '';
        }

        // 2. NAVIGASI BACK
        const btnKembali = document.getElementById('btnKembali');
        if (btnKembali && indexOverlay) {
            btnKembali.addEventListener('click', function(e) {
                e.preventDefault();
                indexOverlay.classList.add('show');
                const href = this.getAttribute('href');
                setTimeout(() => window.location.href = href, 300);
            });
        }
    });

    const modal = document.getElementById('modalForm');
    const form = document.getElementById('formDetail');
    const title = document.getElementById('modalTitle');
    const action = document.getElementById('formAction');
    const inputId = document.getElementById('inputId');
    const inputJudul = document.getElementById('inputJudul');
    const inputTotal = document.getElementById('inputTotal');
    const inputKet = document.getElementById('inputKet');
    const groupTotalIsi = document.getElementById('groupTotalIsi');

    function openModal() {
        modal.classList.remove('hidden');
        if (currentMasterType === 'CHECKLIST') {
            groupTotalIsi.classList.add('hidden');
            inputTotal.required = false;
            inputTotal.value = 0;
        } else {
            groupTotalIsi.classList.remove('hidden');
            inputTotal.required = true;
        }
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    const btnTambah = document.getElementById('btnTambah');
    if (btnTambah) {
        btnTambah.addEventListener('click', () => {
            form.reset();
            action.value = 'tambah';
            inputId.value = '';
            title.textContent = 'Tambah Detail Materi';
            openModal();
        });
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-edit');
        if (btn) {
            const data = JSON.parse(btn.dataset.json);
            action.value = 'edit';
            inputId.value = data.id;
            title.textContent = 'Edit Detail Materi';
            inputJudul.value = data.judul_detail;
            inputTotal.value = data.total_isi;
            inputKet.value = data.keterangan;
            openModal();
        }
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // PENTING

        Swal.fire({
            title: 'Menyimpan...',
            didOpen: () => Swal.showLoading()
        });
        const formData = new FormData(form);
        fetch('pages/kurikulum/proses_materi_detail.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
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
            .catch(err => Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error'));
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-hapus');
        if (btn) {
            Swal.fire({
                title: 'Hapus Detail?',
                text: `Anda yakin ingin menghapus "${btn.dataset.judul}"?`,
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
                    fetch('pages/kurikulum/proses_materi_detail.php', {
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