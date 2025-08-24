<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Ambil data admin yang sedang login untuk hak akses
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

$success_message = '';
$error_message = '';
$redirect_url = '';

// === BAGIAN BACKEND: PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI: TAMBAH PENGURUS ---
    if ($action === 'tambah_pengurus') {
        $nama_pengurus = trim($_POST['nama_pengurus'] ?? '');
        $jabatan = $_POST['jabatan'] ?? '';
        $kelas = ($jabatan === 'Wali Kelas') ? ($_POST['kelas'] ?? '') : null;

        // HAK AKSES: Logika penentuan tingkat dan kelompok
        if ($admin_tingkat === 'kelompok') {
            $tingkat = 'kelompok';
            $kelompok = $admin_kelompok;
        } else { // Admin Desa
            if ($jabatan === 'Wali Kelas') {
                $tingkat = ($kelas === 'remaja') ? 'desa' : 'kelompok';
                $kelompok = $_POST['kelompok'] ?? '';
            } else {
                $tingkat = $_POST['tingkat'] ?? '';
                $kelompok = ($tingkat === 'kelompok') ? ($_POST['kelompok'] ?? '') : null;
            }
        }

        if (empty($nama_pengurus) || empty($jabatan) || empty($tingkat) || ($tingkat === 'kelompok' && empty($kelompok))) {
            $error_message = 'Data form tidak lengkap.';
        }

        if (empty($error_message)) {
            $sql = "INSERT INTO kepengurusan (nama_pengurus, jabatan, tingkat, kelompok, kelas) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $nama_pengurus, $jabatan, $tingkat, $kelompok, $kelas);
            if ($stmt->execute()) {
                $redirect_url = '?page=master/kepengurusan&status=add_success';
            } else {
                $error_message = 'Gagal menambahkan pengurus. Pastikan tidak ada duplikasi jabatan.';
            }
            $stmt->close();
        }
    }

    // --- AKSI: EDIT PENGURUS ---
    if ($action === 'edit_pengurus') {
        $id = $_POST['edit_id'] ?? 0;
        $nama_pengurus = trim($_POST['edit_nama_pengurus'] ?? '');
        if (empty($id) || empty($nama_pengurus)) {
            $error_message = 'Data untuk edit tidak lengkap.';
        } else {
            $sql = "UPDATE kepengurusan SET nama_pengurus = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $nama_pengurus, $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=master/kepengurusan&status=edit_success';
            } else {
                $error_message = 'Gagal memperbarui pengurus.';
            }
            $stmt->close();
        }
    }

    // --- AKSI: HAPUS PENGURUS ---
    if ($action === 'hapus_pengurus') {
        $id = $_POST['hapus_id'] ?? 0;
        if (empty($id)) {
            $error_message = 'ID tidak valid.';
        } else {
            $sql = "DELETE FROM kepengurusan WHERE id = ?";
            // HAK AKSES: Admin kelompok hanya bisa menghapus dari kelompoknya sendiri
            if ($admin_tingkat === 'kelompok') {
                $sql .= " AND kelompok = ?";
            }
            $stmt = $conn->prepare($sql);
            if ($admin_tingkat === 'kelompok') {
                $stmt->bind_param("is", $id, $admin_kelompok);
            } else {
                $stmt->bind_param("i", $id);
            }
            if ($stmt->execute()) {
                $redirect_url = '?page=master/kepengurusan&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus pengurus.';
            }
            $stmt->close();
        }
    }
}

// Cek notifikasi dari URL
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Pengurus baru berhasil ditambahkan!';
    if ($_GET['status'] === 'edit_success') $success_message = 'Jabatan pengurus berhasil diperbarui!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Pengurus berhasil dihapus!';
}

// === AMBIL DATA UNTUK DITAMPILKAN ===
function group_by_jabatan($data)
{
    $grouped = [];
    foreach ($data as $row) {
        $grouped[$row['jabatan']][] = $row;
    }
    return $grouped;
}

// HAK AKSES: Tentukan daftar kelompok yang akan ditampilkan
$kelompok_list = ($admin_tingkat === 'desa') ? ['bintaran', 'gedongkuning', 'jombor', 'sunten'] : [$admin_kelompok];

$pengurus_desa = [];
if ($admin_tingkat === 'desa') {
    $sql_desa = "SELECT id, nama_pengurus, jabatan FROM kepengurusan WHERE tingkat = 'desa' AND jabatan != 'Wali Kelas' ORDER BY FIELD(jabatan, 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Pengawas'), nama_pengurus";
    $result_desa = $conn->query($sql_desa);
    if ($result_desa) {
        $pengurus_desa = group_by_jabatan($result_desa->fetch_all(MYSQLI_ASSOC));
    }
}

$pengurus_kelompok = [];
foreach ($kelompok_list as $kelompok) {
    $sql_kel = "SELECT id, nama_pengurus, jabatan FROM kepengurusan WHERE tingkat = 'kelompok' AND kelompok = ? AND jabatan != 'Wali Kelas' ORDER BY FIELD(jabatan, 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Pengawas'), nama_pengurus";
    $stmt_kel = $conn->prepare($sql_kel);
    $stmt_kel->bind_param("s", $kelompok);
    $stmt_kel->execute();
    $result_kel = $stmt_kel->get_result();
    if ($result_kel) {
        $pengurus_kelompok[$kelompok]['inti'] = group_by_jabatan($result_kel->fetch_all(MYSQLI_ASSOC));
    }

    $sql_wali = "SELECT id, nama_pengurus, kelas FROM kepengurusan WHERE jabatan = 'Wali Kelas' AND kelompok = ? ORDER BY FIELD(kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'), nama_pengurus";
    $stmt_wali = $conn->prepare($sql_wali);
    $stmt_wali->bind_param("s", $kelompok);
    $stmt_wali->execute();
    $result_wali = $stmt_wali->get_result();
    if ($result_wali) {
        while ($row = $result_wali->fetch_assoc()) {
            $pengurus_kelompok[$kelompok]['wali_kelas'][$row['kelas']] = $row;
        }
    }
}
?>
<div class="container mx-auto space-y-8">
    <div>
        <h1 class="text-3xl font-semibold text-gray-800">Struktur Kepengurusan PJP</h1>
        <p class="mt-1 text-gray-600">Kelola pengurus PJP tingkat Desa dan Kelompok.</p>
    </div>
    <div class="flex justify-end"><button id="tambahPengurusBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Tambah Pengurus</button></div>

    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <!-- KARTU PJP DESA (Hanya untuk admin desa) -->
    <?php if ($admin_tingkat === 'desa'): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 border-b pb-2 mb-4">PJP Desa</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php $jabatan_list = ['Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Pengawas']; ?>
                <?php foreach ($jabatan_list as $jabatan): ?>
                    <div>
                        <h3 class="font-semibold text-gray-600"><?php echo $jabatan; ?></h3>
                        <ul class="list-disc list-inside text-gray-800 mt-1">
                            <?php if (!empty($pengurus_desa[$jabatan])): foreach ($pengurus_desa[$jabatan] as $p): ?>
                                    <li class="flex items-center justify-between group hover:bg-yellow-200">
                                        <span><?php echo htmlspecialchars($p['nama_pengurus']); ?></span>
                                        <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button class="edit-btn text-indigo-500 hover:text-indigo-700 text-xs" data-id="<?php echo $p['id']; ?>" data-nama="<?php echo htmlspecialchars($p['nama_pengurus']); ?>">[Edit]</button>
                                            <button class="hapus-btn text-red-500 hover:text-red-700 text-xs ml-2" data-id="<?php echo $p['id']; ?>" data-nama="<?php echo htmlspecialchars($p['nama_pengurus']); ?>">[Hapus]</button>
                                        </div>
                                    </li>
                                <?php endforeach;
                            else: ?>
                                <li class="text-gray-400 italic">Belum ada data</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- KARTU PJP KELOMPOK -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php foreach ($kelompok_list as $kelompok): ?>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold text-gray-800 border-b pb-2 mb-4 capitalize"><?php echo $kelompok; ?></h2>
                <div class="space-y-4">
                    <?php $jabatan_list_kelompok = ['Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Pengawas']; ?>
                    <?php foreach ($jabatan_list_kelompok as $jabatan): ?>
                        <div>
                            <h3 class="font-semibold text-gray-600"><?php echo $jabatan; ?></h3>
                            <ul class="list-disc list-inside text-gray-800 mt-1 text-sm">
                                <?php if (!empty($pengurus_kelompok[$kelompok]['inti'][$jabatan])): foreach ($pengurus_kelompok[$kelompok]['inti'][$jabatan] as $p): ?>
                                        <li class="flex items-center justify-between group hover:bg-yellow-200">
                                            <span><?php echo htmlspecialchars($p['nama_pengurus']); ?></span>
                                            <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button class="edit-btn text-indigo-500 hover:text-indigo-700 text-xs" data-id="<?php echo $p['id']; ?>" data-nama="<?php echo htmlspecialchars($p['nama_pengurus']); ?>">[Edit]</button>
                                                <button class="hapus-btn text-red-500 hover:text-red-700 text-xs ml-2" data-id="<?php echo $p['id']; ?>" data-nama="<?php echo htmlspecialchars($p['nama_pengurus']); ?>">[Hapus]</button>
                                            </div>
                                        </li>
                                    <?php endforeach;
                                else: ?>
                                    <li class="text-gray-400 italic">Belum ada data</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                    <div>
                        <h3 class="font-semibold text-gray-600 border-t pt-4 mt-4">Wali Kelas</h3>
                        <div class="space-y-2 mt-2 text-sm">
                            <?php $kelas_list_semua = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah']; ?>
                            <?php foreach ($kelas_list_semua as $kelas): ?>
                                <div class="flex items-center justify-between group">
                                    <span class="text-gray-500 capitalize"><?php echo $kelas; ?>:</span>
                                    <?php if (isset($pengurus_kelompok[$kelompok]['wali_kelas'][$kelas])): $wali = $pengurus_kelompok[$kelompok]['wali_kelas'][$kelas]; ?>
                                        <div class="font-semibold text-gray-800 flex items-center">
                                            <span><?php echo htmlspecialchars($wali['nama_pengurus']); ?></span>
                                            <div class="opacity-0 group-hover:opacity-100 transition-opacity hover:bg-yellow-200">
                                                <button class="edit-btn text-indigo-500 hover:text-indigo-700 text-xs ml-2" data-id="<?php echo $wali['id']; ?>" data-nama="<?php echo htmlspecialchars($wali['nama_pengurus']); ?>">[Edit]</button>
                                                <button class="hapus-btn text-red-500 hover:text-red-700 text-xs ml-2" data-id="<?php echo $wali['id']; ?>" data-nama="<?php echo htmlspecialchars($wali['nama_pengurus']); ?>">[Hapus]</button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">Belum Ditetapkan</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Tambah Pengurus -->
<div id="tambahPengurusModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=master/kepengurusan">
                <input type="hidden" name="action" value="tambah_pengurus">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Tambah Pengurus</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nama Pengurus*</label><input type="text" name="nama_pengurus" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Dapukan*</label><select name="jabatan" id="jabatan_select" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="">-- Pilih Dapukan --</option>
                                <option value="Ketua">Ketua</option>
                                <option value="Wakil">Wakil</option>
                                <option value="Sekretaris">Sekretaris</option>
                                <option value="Bendahara">Bendahara</option>
                                <option value="Pengawas">Pengawas</option>
                                <option value="Wali Kelas">Wali Kelas</option>
                            </select></div>

                        <?php if ($admin_tingkat === 'desa'): ?>
                            <div id="tingkat_field" class="hidden"><label class="block text-sm font-medium">Tingkat*</label><select name="tingkat" id="tingkat_select" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                                    <option value="">-- Pilih Tingkat --</option>
                                    <option value="desa">Desa</option>
                                    <option value="kelompok">Kelompok</option>
                                </select></div>
                        <?php endif; ?>

                        <div id="kelompok_field" class="hidden"><label class="block text-sm font-medium">Kelompok*</label>
                            <?php if ($admin_tingkat === 'kelompok'): ?>
                                <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                            <?php else: ?>
                                <select name="kelompok" id="kelompok_select" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                                    <option value="">-- Pilih Kelompok --</option>
                                    <option value="bintaran">Bintaran</option>
                                    <option value="gedongkuning">Gedongkuning</option>
                                    <option value="jombor">Jombor</option>
                                    <option value="sunten">Sunten</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div id="kelas_field" class="hidden"><label class="block text-sm font-medium">Wali Kelas untuk*</label><select name="kelas" id="kelas_select" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md"></select></div>
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

<!-- Modal Edit Pengurus -->
<div id="editPengurusModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=master/kepengurusan">
                <input type="hidden" name="action" value="edit_pengurus">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Ganti Nama Pengurus</h3>
                    <div><label class="block text-sm font-medium">Nama Pengurus Baru*</label><input type="text" name="edit_nama_pengurus" id="edit_nama_pengurus" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Update</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div id="hapusPengurusModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=master/kepengurusan">
                <input type="hidden" name="action" value="hapus_pengurus">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus jabatan <strong id="hapus_nama"></strong>?</p>
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

        const modals = {
            tambah: document.getElementById('tambahPengurusModal'),
            edit: document.getElementById('editPengurusModal'),
            hapus: document.getElementById('hapusPengurusModal')
        };
        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');
        document.getElementById('tambahPengurusBtn').onclick = () => openModal(modals.tambah);
        Object.values(modals).forEach(modal => {
            if (modal) modal.addEventListener('click', e => {
                if (e.target === modal || e.target.closest('.modal-close-btn')) closeModal(modal);
            });
        });

        document.querySelector('body').addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = target.dataset.id;
                document.getElementById('hapus_nama').textContent = `dari ${target.dataset.nama}`;
                openModal(modals.hapus);
            }
            if (target.classList.contains('edit-btn')) {
                document.getElementById('edit_id').value = target.dataset.id;
                document.getElementById('edit_nama_pengurus').value = target.dataset.nama;
                openModal(modals.edit);
            }
        });

        const jabatanSelect = document.getElementById('jabatan_select');
        const tingkatField = document.getElementById('tingkat_field');
        const tingkatSelect = document.getElementById('tingkat_select');
        const kelompokField = document.getElementById('kelompok_field');
        const kelasField = document.getElementById('kelas_field');
        const kelasSelect = document.getElementById('kelas_select');
        const adminTingkat = '<?php echo $admin_tingkat; ?>';

        function toggleFields() {
            const jabatan = jabatanSelect.value;
            const tingkat = tingkatSelect ? tingkatSelect.value : 'kelompok';

            // Sembunyikan semua field dinamis
            if (tingkatField) tingkatField.classList.add('hidden');
            kelompokField.classList.add('hidden');
            kelasField.classList.add('hidden');

            if (jabatan === 'Wali Kelas') {
                kelompokField.classList.remove('hidden');
                kelasField.classList.remove('hidden');
                updateKelasOptions();
            } else if (jabatan) {
                if (tingkatField) tingkatField.classList.remove('hidden');
                if (tingkat === 'kelompok') {
                    kelompokField.classList.remove('hidden');
                }
            }
        }

        function updateKelasOptions() {
            kelasSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';
            const kelasKelompok = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'pra nikah'];
            const kelasDesa = ['remaja'];

            if (adminTingkat === 'desa') {
                [...kelasKelompok, ...kelasDesa].forEach(k => {
                    kelasSelect.innerHTML += `<option value="${k}">${k.charAt(0).toUpperCase() + k.slice(1)}</option>`;
                });
            } else { // adminTingkat === 'kelompok'
                kelasKelompok.forEach(k => {
                    kelasSelect.innerHTML += `<option value="${k}">${k.charAt(0).toUpperCase() + k.slice(1)}</option>`;
                });
            }
        }

        jabatanSelect.addEventListener('change', toggleFields);
        if (tingkatSelect) tingkatSelect.addEventListener('change', toggleFields);

        <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            openModal(modals.tambah);
        <?php endif; ?>
    });
</script>