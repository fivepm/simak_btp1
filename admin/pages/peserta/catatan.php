<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_id = $_SESSION['user_id'] ?? 0;
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL
$selected_peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : null;
$siswa = null;

if ($selected_peserta_id) {
    $siswa = $conn->query("SELECT * FROM peserta WHERE id = $selected_peserta_id")->fetch_assoc();
}

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Ambil peserta_id dari form POST jika ada, atau fallback ke selected GET
    $peserta_id_form = $_POST['peserta_id'] ?? $selected_peserta_id;

    // Redirect URL: Jika peserta_id diketahui, balik ke halaman peserta itu. Jika tidak, balik ke halaman utama (semua).
    $redirect_url = '?page=peserta/catatan';
    if (!empty($peserta_id_form)) {
        $redirect_url .= '&peserta_id=' . $peserta_id_form;
    }

    // --- AKSI: TAMBAH CATATAN ---
    if ($action === 'tambah_catatan') {
        $tanggal_catatan = $_POST['tanggal_catatan'] ?? '';
        $permasalahan = $_POST['permasalahan'] ?? '';
        $tindak_lanjut = $_POST['tindak_lanjut'] ?? '';

        if (empty($peserta_id_form) || empty($tanggal_catatan) || empty($permasalahan)) {
            $error_message = 'Peserta, Tanggal, dan Permasalahan wajib diisi.';
            $swal_notification = "Swal.fire({title: 'Gagal!', text: '$error_message', icon: 'error'});";
        } else {
            $sql = "INSERT INTO catatan_bk (peserta_id, tanggal_catatan, permasalahan, tindak_lanjut, dicatat_oleh_user_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssi", $peserta_id_form, $tanggal_catatan, $permasalahan, $tindak_lanjut, $admin_id);
            if ($stmt->execute()) {
                // Ambil nama siswa untuk log
                $nama_siswa_log = $siswa['nama_lengkap'] ?? 'ID ' . $peserta_id_form;
                writeLog('INSERT', "Membuat Catatan BK untuk siswa: $nama_siswa_log");

                $swal_notification = "Swal.fire({title: 'Berhasil!', text: 'Catatan berhasil ditambahkan.', icon: 'success', showConfirmButton: false, timer: 1500}).then(() => { window.location = '$redirect_url'; });";
            } else {
                $swal_notification = "Swal.fire({title: 'Gagal!', text: 'Database error', icon: 'error'});";
            }
            $stmt->close();
        }
    }

    // --- AKSI: EDIT CATATAN ---
    if ($action === 'edit_catatan') {
        $catatan_id = $_POST['catatan_id'] ?? 0;
        $tanggal_catatan = $_POST['tanggal_catatan'] ?? '';
        $permasalahan = $_POST['permasalahan'] ?? '';
        $tindak_lanjut = $_POST['tindak_lanjut'] ?? '';

        if (empty($catatan_id) || empty($tanggal_catatan) || empty($permasalahan)) {
            $swal_notification = "Swal.fire({title: 'Gagal!', text: 'Data edit tidak lengkap.', icon: 'error'});";
        } else {
            $sql = "UPDATE catatan_bk SET tanggal_catatan=?, permasalahan=?, tindak_lanjut=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $tanggal_catatan, $permasalahan, $tindak_lanjut, $catatan_id);
            if ($stmt->execute()) {
                writeLog('UPDATE', "Memperbarui Catatan BK ID: $catatan_id");
                $swal_notification = "Swal.fire({title: 'Berhasil!', text: 'Catatan diperbarui.', icon: 'success', showConfirmButton: false, timer: 1500}).then(() => { window.location = '$redirect_url'; });";
            } else {
                $swal_notification = "Swal.fire({title: 'Gagal!', text: 'Update error', icon: 'error'});";
            }
            $stmt->close();
        }
    }

    // --- AKSI: HAPUS CATATAN ---
    if ($action === 'hapus_catatan') {
        $catatan_id = $_POST['hapus_id'] ?? 0;
        if (!empty($catatan_id)) {
            $stmt = $conn->prepare("DELETE FROM catatan_bk WHERE id = ?");
            $stmt->bind_param("i", $catatan_id);
            if ($stmt->execute()) {
                writeLog('DELETE', "Menghapus Catatan BK ID: $catatan_id");
                $swal_notification = "Swal.fire({title: 'Berhasil!', text: 'Catatan dihapus.', icon: 'success', showConfirmButton: false, timer: 1500}).then(() => { window.location = '$redirect_url'; });";
            } else {
                $swal_notification = "Swal.fire({title: 'Gagal!', text: 'Hapus error', icon: 'error'});";
            }
            $stmt->close();
        }
    }
}

// === AMBIL DAFTAR PESERTA (DROPDOWN) ===
$peserta_list = [];
$sql_peserta = "SELECT id, nama_lengkap, kelas, kelompok FROM peserta WHERE status = 'Aktif'";
if ($admin_tingkat === 'kelompok') {
    $sql_peserta .= " AND kelompok = ?";
}
$sql_peserta .= " ORDER BY nama_lengkap ASC";
$stmt_peserta = $conn->prepare($sql_peserta);
if ($admin_tingkat === 'kelompok') {
    $stmt_peserta->bind_param("s", $admin_kelompok);
}
$stmt_peserta->execute();
$result_peserta = $stmt_peserta->get_result();
if ($result_peserta) {
    while ($row = $result_peserta->fetch_assoc()) {
        $peserta_list[] = $row;
    }
}

// === AMBIL DATA CATATAN (LOGIKA BARU) ===
$catatan_bk_list = [];

if ($selected_peserta_id) {
    // --- KASUS A: Jika Siswa Dipilih (Tampilkan khusus siswa itu) ---
    $sql_catatan = "SELECT cb.*, u.nama as nama_pencatat 
                    FROM catatan_bk cb 
                    LEFT JOIN users u ON cb.dicatat_oleh_user_id = u.id
                    WHERE cb.peserta_id = ? 
                    ORDER BY cb.tanggal_catatan DESC, cb.created_at DESC";
    $stmt_catatan = $conn->prepare($sql_catatan);
    $stmt_catatan->bind_param("i", $selected_peserta_id);
    $stmt_catatan->execute();
    $result_catatan = $stmt_catatan->get_result();
} else {
    // --- KASUS B: Jika Tidak Ada Siswa Dipilih (Tampilkan SEMUA) ---
    $sql_catatan = "SELECT cb.*, u.nama as nama_pencatat, p.nama_lengkap, p.kelas, p.kelompok, p.id as real_peserta_id
                    FROM catatan_bk cb 
                    JOIN peserta p ON cb.peserta_id = p.id
                    LEFT JOIN users u ON cb.dicatat_oleh_user_id = u.id
                    WHERE 1=1";

    $params = [];
    $types = "";

    // Filter berdasarkan hak akses admin
    if ($admin_tingkat === 'kelompok') {
        $sql_catatan .= " AND p.kelompok = ?";
        $params[] = $admin_kelompok;
        $types .= "s";
    }

    $sql_catatan .= " ORDER BY cb.tanggal_catatan DESC, cb.created_at DESC LIMIT 50"; // Limit 50 agar tidak berat

    $stmt_catatan = $conn->prepare($sql_catatan);
    if (!empty($params)) {
        $stmt_catatan->bind_param($types, ...$params);
    }
    $stmt_catatan->execute();
    $result_catatan = $stmt_catatan->get_result();
}

if ($result_catatan) {
    while ($row = $result_catatan->fetch_assoc()) {
        $catatan_bk_list[] = $row;
    }
}
?>

<div class="container mx-auto space-y-6">
    <!-- FILTER PESERTA -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Pilih Peserta</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="peserta/catatan">
            <div class="flex items-center gap-4">
                <select name="peserta_id" onchange="this.form.submit()" class="flex-grow mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm">
                    <option value="">-- Tampilkan Semua Catatan (Terbaru) --</option>
                    <?php foreach ($peserta_list as $peserta): ?>
                        <option value="<?php echo $peserta['id']; ?>" <?php echo ($selected_peserta_id === (int)$peserta['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($peserta['nama_lengkap'] . ' (' . ucfirst($peserta['kelas']) . ' - ' . ucfirst($peserta['kelompok']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- KONTEN UTAMA -->
    <?php if ($selected_peserta_id && $siswa): ?>

        <!-- TAMPILAN KHUSUS SATU SISWA (SPLIT LAYOUT) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-lg shadow-md sticky top-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Tambah Catatan Baru</h3>
                    <button id="tambahCatatanBtn" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg shadow transition">
                        + Buat Catatan
                    </button>
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-100">
                        <p class="font-bold text-blue-800 text-sm">Biodata Singkat</p>
                        <p class="text-sm text-gray-600 mt-1">Nama: <strong><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></strong></p>
                        <p class="text-sm text-gray-600">Kelas: <?php echo ucfirst($siswa['kelas']); ?></p>
                        <p class="text-sm text-gray-600">Kelompok: <?php echo ucfirst($siswa['kelompok']); ?></p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Catatan: <span class="text-indigo-600"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></span></h3>
                <?php renderCatatanList($catatan_bk_list, false); // false = jangan tampilkan nama siswa di card 
                ?>
            </div>
        </div>

    <?php else: ?>

        <!-- TAMPILAN SEMUA CATATAN (FULL WIDTH) -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Timeline Catatan Terbaru (Semua Siswa)</h3>
            <?php renderCatatanList($catatan_bk_list, true); // true = tampilkan nama siswa di card 
            ?>
        </div>

    <?php endif; ?>
</div>

<?php
// FUNGSI HELPER UNTUK RENDER LIST (SUPAYA TIDAK DUPLIKASI KODE)
function renderCatatanList($list, $showName)
{
    if (empty($list)) {
        echo '<div class="text-center py-10 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                <i class="fa-regular fa-folder-open text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">Belum ada catatan yang ditemukan.</p>
              </div>';
        return;
    }

    echo '<div class="space-y-4">';
    foreach ($list as $catatan) {
        $namaSiswaHTML = '';
        if ($showName) {
            $namaSiswaHTML = '
                <div class="mb-2 pb-2 border-b border-indigo-100 flex justify-between items-center">
                    <span class="font-bold text-lg text-indigo-700">' . htmlspecialchars($catatan['nama_lengkap']) . '</span>
                    <span class="text-xs font-semibold bg-indigo-50 text-indigo-600 px-2 py-1 rounded">' . ucfirst($catatan['kelas']) . ' - ' . ucfirst($catatan['kelompok']) . '</span>
                </div>
            ';
        }

        // Siapkan data JSON untuk JS
        // Penting: Jika ini tampilan "Semua", kita perlu menyisipkan real_peserta_id ke dalam data JSON agar modal edit tahu ID pesertanya
        if (isset($catatan['real_peserta_id'])) {
            $catatan['peserta_id'] = $catatan['real_peserta_id'];
        }
        $jsonData = htmlspecialchars(json_encode($catatan), ENT_QUOTES, 'UTF-8');

        echo '
        <div class="border-l-4 border-indigo-500 pl-5 py-4 bg-white hover:bg-gray-50 transition rounded-r-lg shadow-sm border border-gray-100">
            ' . $namaSiswaHTML . '
            <div class="flex justify-between items-start">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fa-regular fa-calendar text-gray-400"></i>
                        <p class="font-bold text-gray-800">' . date("d M Y", strtotime($catatan['tanggal_catatan'])) . '</p>
                    </div>
                    <p class="text-xs text-gray-500 mb-2">Dicatat oleh: <span class="font-medium text-gray-700">' . htmlspecialchars($catatan['nama_pencatat'] ?? 'System') . '</span></p>
                </div>
                <div class="flex space-x-2">
                    <button class="edit-btn text-indigo-600 hover:text-indigo-800 text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 px-2 py-1 rounded transition" 
                        data-catatan="' . $jsonData . '">
                        <i class="fa-solid fa-pen"></i> Edit
                    </button>
                    <button class="hapus-btn text-red-600 hover:text-red-800 text-xs font-semibold bg-red-50 hover:bg-red-100 px-2 py-1 rounded transition" 
                        data-id="' . $catatan['id'] . '" 
                        data-info="Catatan tanggal ' . date("d M Y", strtotime($catatan['tanggal_catatan'])) . '"
                        data-peserta-id="' . ($catatan['peserta_id'] ?? '') . '">
                        <i class="fa-solid fa-trash"></i> Hapus
                    </button>
                </div>
            </div>
            
            <div class="mt-3">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Permasalahan</p>
                <p class="text-sm text-gray-800 whitespace-pre-wrap mt-1 leading-relaxed">' . htmlspecialchars($catatan['permasalahan']) . '</p>
            </div>
            
            <div class="mt-4 pt-3 border-t border-gray-100">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Tindak Lanjut</p>
                ' . (!empty($catatan['tindak_lanjut'])
            ? '<p class="text-sm text-gray-800 whitespace-pre-wrap mt-1 leading-relaxed">' . htmlspecialchars($catatan['tindak_lanjut']) . '</p>'
            : '<button class="tambah-tindak-lanjut-btn mt-2 text-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 px-3 py-1.5 rounded font-medium transition" data-catatan="' . $jsonData . '">+ Tambah Tindak Lanjut</button>'
        ) . '
            </div>
        </div>';
    }
    echo '</div>';
}
?>

<!-- Modal Form (Universal) -->
<div id="formModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-500 opacity-75 modal-backdrop"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-lg z-30">
            <form method="POST" action="">
                <input type="hidden" name="action" id="form_action">
                <!-- Field Peserta ID Hidden (Akan diisi JS saat Edit) -->
                <input type="hidden" name="peserta_id" id="modal_peserta_id" value="<?php echo $selected_peserta_id ?? ''; ?>">
                <input type="hidden" name="catatan_id" id="catatan_id">

                <div class="bg-white px-6 pt-6 pb-4">
                    <h3 id="modalTitle" class="text-xl font-semibold text-gray-900 mb-6 border-b pb-2">Form Catatan</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Catatan*</label>
                            <input type="date" name="tanggal_catatan" id="tanggal_catatan" class="w-full shadow-sm sm:text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Permasalahan*</label>
                            <textarea name="permasalahan" id="permasalahan" rows="4" class="w-full p-2 shadow-sm sm:text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required placeholder="Tulis detail permasalahan..."></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tindak Lanjut</label>
                            <textarea name="tindak_lanjut" id="tindak_lanjut" rows="3" class="w-full p-2 shadow-sm sm:text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" placeholder="Rencana atau tindakan yang sudah dilakukan..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-2">
                    <button type="submit" class="w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none sm:text-sm">Simpan</button>
                    <button type="button" class="modal-close-btn w-auto inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div id="hapusModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-500 opacity-75 modal-backdrop"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-sm z-30">
            <form method="POST" action="">
                <input type="hidden" name="action" value="hapus_catatan">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <input type="hidden" name="peserta_id" id="hapus_peserta_id"> <!-- Penting untuk redirect -->

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fa-solid fa-triangle-exclamation text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Catatan?</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Anda yakin ingin menghapus <strong id="hapus_info"></strong>? Data yang dihapus tidak dapat dikembalikan.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-row-reverse gap-2">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Ya, Hapus</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const formModal = document.getElementById('formModal');
        const hapusModal = document.getElementById('hapusModal');
        const btnTambah = document.getElementById('tambahCatatanBtn');

        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        // Logic Tombol Tambah (Hanya muncul jika siswa dipilih)
        if (btnTambah) {
            btnTambah.onclick = () => {
                formModal.querySelector('form').reset();
                document.getElementById('form_action').value = 'tambah_catatan';
                document.getElementById('catatan_id').value = '';
                // Pastikan peserta ID terisi (dari PHP variabel global)
                document.getElementById('modal_peserta_id').value = '<?php echo $selected_peserta_id; ?>';
                document.getElementById('modalTitle').textContent = 'Buat Catatan Baru';
                document.getElementById('permasalahan').readOnly = false;
                document.getElementById('tanggal_catatan').readOnly = false;
                openModal(formModal);
            };
        }

        // Logic Tutup Modal (Tombol Batal & Backdrop)
        document.querySelectorAll('.modal-close-btn, .modal-backdrop').forEach(el => {
            el.onclick = () => {
                closeModal(formModal);
                closeModal(hapusModal);
            };
        });

        // Event Delegation untuk Tombol Edit/Hapus/Tindak Lanjut
        document.body.addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            // EDIT BUTTON
            if (target.classList.contains('edit-btn')) {
                const data = JSON.parse(target.dataset.catatan);
                document.getElementById('form_action').value = 'edit_catatan';
                document.getElementById('modalTitle').textContent = 'Edit Catatan';

                document.getElementById('catatan_id').value = data.id;
                document.getElementById('modal_peserta_id').value = data.peserta_id; // Set ID peserta dinamis

                document.getElementById('tanggal_catatan').value = data.tanggal_catatan;
                document.getElementById('permasalahan').value = data.permasalahan;
                document.getElementById('tindak_lanjut').value = data.tindak_lanjut;

                document.getElementById('permasalahan').readOnly = false;
                document.getElementById('tanggal_catatan').readOnly = false;
                openModal(formModal);
            }

            // TAMBAH TINDAK LANJUT BUTTON
            if (target.classList.contains('tambah-tindak-lanjut-btn')) {
                const data = JSON.parse(target.dataset.catatan);
                document.getElementById('form_action').value = 'edit_catatan';
                document.getElementById('modalTitle').textContent = 'Tambah Tindak Lanjut';

                document.getElementById('catatan_id').value = data.id;
                document.getElementById('modal_peserta_id').value = data.peserta_id; // Set ID peserta dinamis

                document.getElementById('tanggal_catatan').value = data.tanggal_catatan;
                document.getElementById('permasalahan').value = data.permasalahan;
                document.getElementById('tindak_lanjut').value = '';

                document.getElementById('permasalahan').readOnly = true; // Kunci field
                document.getElementById('tanggal_catatan').readOnly = true; // Kunci field
                openModal(formModal);
            }

            // HAPUS BUTTON
            if (target.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = target.dataset.id;
                document.getElementById('hapus_peserta_id').value = target.dataset.pesertaId; // Set ID untuk redirect
                document.getElementById('hapus_info').textContent = target.dataset.info;
                openModal(hapusModal);
            }
        });
    });
</script>