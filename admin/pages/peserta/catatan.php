<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_id = $_SESSION['user_id'] ?? 0;
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

$success_message = '';
$error_message = '';
$redirect_url = '';

// Ambil filter dari URL
$selected_peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : null;
if ($selected_peserta_id) {
    $siswa = $conn->query("SELECT * FROM peserta WHERE id = $selected_peserta_id")->fetch_assoc();
}

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $peserta_id_form = $_POST['peserta_id'] ?? $selected_peserta_id;
    $redirect_url = '?page=peserta/catatan&peserta_id=' . $peserta_id_form;

    // --- AKSI: TAMBAH CATATAN ---
    if ($action === 'tambah_catatan') {
        $tanggal_catatan = $_POST['tanggal_catatan'] ?? '';
        $permasalahan = $_POST['permasalahan'] ?? '';
        $tindak_lanjut = $_POST['tindak_lanjut'] ?? '';

        if (empty($peserta_id_form) || empty($tanggal_catatan) || empty($permasalahan)) {
            $error_message = 'Peserta, Tanggal, dan Permasalahan wajib diisi.';
        }

        if (empty($error_message)) {
            $sql = "INSERT INTO catatan_bk (peserta_id, tanggal_catatan, permasalahan, tindak_lanjut, dicatat_oleh_user_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssi", $peserta_id_form, $tanggal_catatan, $permasalahan, $tindak_lanjut, $admin_id);
            if ($stmt->execute()) {
                $deskripsi_log = "Membuat *Catatan Peserta Didik* `" . $siswa['nama_lengkap'] . "` (*" . ucwords($siswa['kelompok']) . "* - *" . ucwords($siswa['kelas']) . "*)";
                writeLog('INSERT', $deskripsi_log);
                $redirect_url .= '&status=add_success';
            } else {
                $error_message = 'Gagal menyimpan catatan BK.';
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
            $error_message = 'Data tidak lengkap untuk proses edit.';
        }

        if (empty($error_message)) {
            $sql = "UPDATE catatan_bk SET tanggal_catatan=?, permasalahan=?, tindak_lanjut=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $tanggal_catatan, $permasalahan, $tindak_lanjut, $catatan_id);
            if ($stmt->execute()) {
                $deskripsi_log = "Memperbarui *Catatan Peserta Didik* `" . $siswa['nama_lengkap'] . "` (*" . ucwords($siswa['kelompok']) . "* - *" . ucwords($siswa['kelas']) . "*)";
                writeLog('UPDATE', $deskripsi_log);
                $redirect_url .= '&status=edit_success';
            } else {
                $error_message = 'Gagal memperbarui catatan.';
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
                $deskripsi_log = "Menghapus *Catatan Peserta Didik* `" . $siswa['nama_lengkap'] . "` (*" . ucwords($siswa['kelompok']) . "* - *" . ucwords($siswa['kelas']) . "*)";
                writeLog('DELETE', $deskripsi_log);
                $redirect_url .= '&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus catatan.';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Catatan BK baru berhasil disimpan!';
    if ($_GET['status'] === 'edit_success') $success_message = 'Catatan BK berhasil diperbarui!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Catatan BK berhasil dihapus!';
}

// === AMBIL DATA DARI DATABASE ===
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

$catatan_bk_list = [];
$selected_peserta = null;
if ($selected_peserta_id) {
    $sql_catatan = "SELECT cb.*, u.nama as nama_pencatat 
                    FROM catatan_bk cb 
                    LEFT JOIN users u ON cb.dicatat_oleh_user_id = u.id
                    WHERE cb.peserta_id = ? 
                    ORDER BY cb.tanggal_catatan DESC, cb.created_at DESC";
    $stmt_catatan = $conn->prepare($sql_catatan);
    $stmt_catatan->bind_param("i", $selected_peserta_id);
    $stmt_catatan->execute();
    $result_catatan = $stmt_catatan->get_result();
    if ($result_catatan) {
        while ($row = $result_catatan->fetch_assoc()) {
            $catatan_bk_list[] = $row;
        }
    }

    $stmt_nama = $conn->prepare("SELECT nama_lengkap FROM peserta WHERE id = ?");
    $stmt_nama->bind_param("i", $selected_peserta_id);
    $stmt_nama->execute();
    $selected_peserta = $stmt_nama->get_result()->fetch_assoc();
}
?>
<div class="container mx-auto space-y-6">
    <!-- BAGIAN 1: FILTER PESERTA -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Pilih Peserta</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="peserta/catatan">
            <div class="flex items-center gap-4">
                <select name="peserta_id" onchange="this.form.submit()" class="flex-grow mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                    <option value="">-- Cari dan Pilih Nama Peserta --</option>
                    <?php foreach ($peserta_list as $peserta): ?>
                        <option value="<?php echo $peserta['id']; ?>" <?php echo ($selected_peserta_id === (int)$peserta['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($peserta['nama_lengkap'] . ' (' . ucfirst($peserta['kelas']) . ' - ' . ucfirst($peserta['kelompok']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- BAGIAN 2: FORM & RIWAYAT -->
    <?php if ($selected_peserta_id && $selected_peserta): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Tambah Catatan Baru</h3>
                    <button id="tambahCatatanBtn" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">
                        + Buat Catatan
                    </button>
                </div>
            </div>
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Catatan untuk <span class="text-indigo-600"><?php echo htmlspecialchars($selected_peserta['nama_lengkap']); ?></span></h3>
                <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
                <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>
                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                    <?php if (empty($catatan_bk_list)): ?>
                        <p class="text-center text-gray-500 py-8">Belum ada catatan BK untuk peserta ini.</p>
                        <?php else: foreach ($catatan_bk_list as $catatan): ?>
                            <div class="border-l-4 border-indigo-500 pl-4 py-2 bg-gray-50 rounded">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-gray-800"><?php echo date("d M Y", strtotime($catatan['tanggal_catatan'])); ?></p>
                                        <p class="text-xs text-gray-500">Dicatat oleh: <?php echo htmlspecialchars($catatan['nama_pencatat'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="edit-btn text-indigo-600 hover:text-indigo-800 text-xs" data-catatan='<?php echo json_encode($catatan); ?>'>[Edit]</button>
                                        <button class="hapus-btn text-red-600 hover:text-red-800 text-xs" data-id="<?php echo $catatan['id']; ?>" data-info="Catatan tanggal <?php echo date("d M Y", strtotime($catatan['tanggal_catatan'])); ?>">[Hapus]</button>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="font-semibold text-gray-600">Permasalahan:</p>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($catatan['permasalahan']); ?></p>
                                </div>
                                <div class="mt-2">
                                    <p class="font-semibold text-gray-600">Tindak Lanjut:</p>
                                    <?php if (!empty($catatan['tindak_lanjut'])): ?>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($catatan['tindak_lanjut']); ?></p>
                                    <?php else: ?>
                                        <button class="tambah-tindak-lanjut-btn mt-1 text-sm bg-yellow-100 text-yellow-800 hover:bg-yellow-200 px-2 py-1 rounded" data-catatan='<?php echo json_encode($catatan); ?>'>+ Tambah Tindak Lanjut</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah/Edit/Tindak Lanjut -->
<div id="formModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=peserta/catatan&peserta_id=<?php echo $selected_peserta_id; ?>">
                <input type="hidden" name="action" id="form_action">
                <input type="hidden" name="peserta_id" value="<?php echo $selected_peserta_id; ?>">
                <input type="hidden" name="catatan_id" id="catatan_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 id="modalTitle" class="text-lg font-medium text-gray-900 mb-4">Form Catatan</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Tanggal Catatan*</label><input type="date" name="tanggal_catatan" id="tanggal_catatan" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Permasalahan*</label><textarea name="permasalahan" id="permasalahan" rows="4" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></textarea></div>
                        <div><label class="block text-sm font-medium">Tindak Lanjut</label><textarea name="tindak_lanjut" id="tindak_lanjut" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea></div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div id="hapusModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=peserta/catatan&peserta_id=<?php echo $selected_peserta_id; ?>">
                <input type="hidden" name="action" value="hapus_catatan">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus <strong id="hapus_info"></strong>?</p>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Ya, Hapus</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($redirect_url)): ?>
            window.location.href = '<?php echo $redirect_url; ?>';
        <?php endif; ?>

        const autoHideAlert = (alertId) => {
            const alertElement = document.getElementById(alertId);
            if (alertElement) {
                setTimeout(() => {
                    alertElement.style.transition = 'opacity 0.5s ease';
                    alertElement.style.opacity = '0';
                    setTimeout(() => {
                        alertElement.style.display = 'none';
                    }, 500); // Waktu untuk animasi fade-out
                }, 3000); // 3000 milidetik = 3 detik
            }
        };
        autoHideAlert('success-alert');
        autoHideAlert('error-alert');

        const formModal = document.getElementById('formModal');
        const hapusModal = document.getElementById('hapusModal');
        const btnTambah = document.getElementById('tambahCatatanBtn');

        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        if (btnTambah) {
            btnTambah.onclick = () => {
                formModal.querySelector('form').reset();
                document.getElementById('form_action').value = 'tambah_catatan';
                document.getElementById('catatan_id').value = '';
                document.getElementById('modalTitle').textContent = 'Form Tambah Catatan Baru';
                document.getElementById('permasalahan').readOnly = false;
                document.getElementById('tanggal_catatan').readOnly = false;
                openModal(formModal);
            };
        }

        document.querySelectorAll('.modal-close-btn').forEach(btn => {
            btn.onclick = () => {
                closeModal(formModal);
                closeModal(hapusModal);
            };
        });

        document.querySelector('body').addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.classList.contains('edit-btn')) {
                const data = JSON.parse(target.dataset.catatan);
                document.getElementById('form_action').value = 'edit_catatan';
                document.getElementById('modalTitle').textContent = 'Form Edit Catatan';
                document.getElementById('catatan_id').value = data.id;
                document.getElementById('tanggal_catatan').value = data.tanggal_catatan;
                document.getElementById('permasalahan').value = data.permasalahan;
                document.getElementById('tindak_lanjut').value = data.tindak_lanjut;
                document.getElementById('permasalahan').readOnly = false;
                document.getElementById('tanggal_catatan').readOnly = false;
                openModal(formModal);
            }

            if (target.classList.contains('tambah-tindak-lanjut-btn')) {
                const data = JSON.parse(target.dataset.catatan);
                document.getElementById('form_action').value = 'edit_catatan'; // Actionnya tetap edit
                document.getElementById('modalTitle').textContent = 'Tambah Tindak Lanjut';
                document.getElementById('catatan_id').value = data.id;
                document.getElementById('tanggal_catatan').value = data.tanggal_catatan;
                document.getElementById('permasalahan').value = data.permasalahan;
                document.getElementById('tindak_lanjut').value = ''; // Kosongkan
                document.getElementById('permasalahan').readOnly = true; // Kunci field ini
                document.getElementById('tanggal_catatan').readOnly = true; // Kunci field ini
                openModal(formModal);
            }

            if (target.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = target.dataset.id;
                document.getElementById('hapus_info').textContent = target.dataset.info;
                openModal(hapusModal);
            }
        });
    });
</script>