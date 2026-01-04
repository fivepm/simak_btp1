<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Ambil data admin yang sedang login untuk hak akses
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';
$admin_role = $_SESSION['user_role'] ?? '';

$redirect_url = '?page=pengaturan/template_pesan';

// === BAGIAN BACKEND: PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI: TAMBAH TEMPLATE ---
    if ($action === 'tambah_template') {
        $tipe_pesan = $_POST['tipe_pesan'] ?? '';
        $kelas = $_POST['kelas'] ?? '';
        $template = $_POST['template'] ?? '';

        // HAK AKSES: Tentukan kelompok berdasarkan tingkat admin
        if ($admin_tingkat === 'kelompok') {
            $kelompok = $admin_kelompok;
        } else {
            $kelompok = ($_POST['kelompok'] === 'semua') ? null : ($_POST['kelompok'] ?? null);
        }

        if (empty($tipe_pesan) || empty($kelas) || empty($template)) {
            $error_message = 'Tipe, Kelas, dan Isi Template wajib diisi.';
            $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $error_message',
                        icon: 'error'
                    });
                ";
        }

        if (empty($error_message)) {
            $sql = "INSERT INTO template_pesan (tipe_pesan, kelas, kelompok, template) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $tipe_pesan, $kelas, $kelompok, $template);
            if ($stmt->execute()) {
                // === PENCATATAN LOG AKTIVITAS ===
                $tipe_pesan_log = ucwords(str_replace('_', ' ', $tipe_pesan));
                $kelompok_log = ($kelompok != null) ? ucwords($kelompok) : "Semua Kelompok";
                $kelas_log = ($kelas != 'default') ? ucwords($kelas) : "Semua Kelas";

                $deskripsi_log = "Menambahkan *Template Pesan* :  *$tipe_pesan_log* ($kelompok_log - $kelas_log).";
                writeLog('INSERT', $deskripsi_log);
                // =================================

                $swal_notification = "
                Swal.fire({
                title: 'Berhasil!', 
                text: 'Template Pesan Berhasil ditambahkan.', 
                icon: 'success', 
                showConfirmButton: false, 
                timer: 2000
                }).then(() => { window.location = '$redirect_url'; });";
            } else {
                $error_message = 'Gagal menambahkan template. Kombinasi Tipe, Kelas, dan Kelompok mungkin sudah ada.';
                $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $error_message',
                        icon: 'error'
                    });
                ";
            }
            $stmt->close();
        }
    }

    // --- AKSI: EDIT TEMPLATE ---
    if ($action === 'edit_template') {
        $id = $_POST['edit_id'] ?? 0;
        $template = $_POST['edit_template'] ?? '';
        if (empty($id) || empty($template)) {
            $error_message = 'Data untuk edit tidak lengkap.';
            $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $error_message',
                        icon: 'error'
                    });
                ";
        } else {
            $q_cek = $conn->query("SELECT * FROM template_pesan WHERE id = $id");
            if ($row_cek = $q_cek->fetch_assoc()) {
                $tipe_pesan_log = ucwords(str_replace('_', ' ', $row_cek['tipe_pesan']));
                $kelompok_log = ($row_cek['kelompok'] != null) ? ucwords($row_cek['kelompok']) : 'Semua Kelompok';
                $kelas_log = ($row_cek['kelas'] != 'default') ? ucwords($row_cek['kelas']) : 'Semua Kelas';
            }

            $sql = "UPDATE template_pesan SET template = ? WHERE id = ?";
            // HAK AKSES: Pastikan admin kelompok hanya bisa edit template kelompoknya atau template umum
            if ($admin_tingkat === 'kelompok') {
                $sql .= " AND (kelompok = ? OR kelompok IS NULL)";
            }
            $stmt = $conn->prepare($sql);
            if ($admin_tingkat === 'kelompok') {
                $stmt->bind_param("sis", $template, $id, $admin_kelompok);
            } else {
                $stmt->bind_param("si", $template, $id);
            }
            if ($stmt->execute()) {
                // === CCTV ===
                $deskripsi_log = "Memperbarui *Template Pesan* :  *$tipe_pesan_log* ($kelompok_log - $kelas_log).";
                writeLog('UPDATE', $deskripsi_log);
                // =================================
                $swal_notification = "
                Swal.fire({
                title: 'Berhasil!', 
                text: 'Template Pesan Berhasil diperbarui.', 
                icon: 'success', 
                showConfirmButton: false, 
                timer: 2000
                }).then(() => { window.location = '$redirect_url'; });";
            } else {
                $error_message = 'Gagal memperbarui template.';
                $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $error_message',
                        icon: 'error'
                    });
                ";
            }
            $stmt->close();
        }
    }

    // --- AKSI: HAPUS TEMPLATE ---
    if ($action === 'hapus_template') {
        $id = $_POST['hapus_id'] ?? 0;
        if (empty($id)) {
            $error_message = 'ID tidak valid.';
            $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $error_message',
                        icon: 'error'
                    });
                ";
        } else {
            $q_cek = $conn->query("SELECT * FROM template_pesan WHERE id = $id");
            if ($row_cek = $q_cek->fetch_assoc()) {
                $tipe_pesan_log = ucwords(str_replace('_', ' ', $row_cek['tipe_pesan']));
                $kelompok_log = ($row_cek['kelompok'] != null) ? ucwords($row_cek['kelompok']) : 'Semua Kelompok';
                $kelas_log = ($row_cek['kelas'] != 'default') ? ucwords($row_cek['kelas']) : 'Semua Kelas';
            }

            $sql = "DELETE FROM template_pesan WHERE id = ?";
            // HAK AKSES: Pastikan admin kelompok hanya bisa hapus template kelompoknya atau template umum
            if ($admin_tingkat === 'kelompok') {
                $sql .= " AND (kelompok = ? OR kelompok IS NULL)";
            }
            $stmt = $conn->prepare($sql);
            if ($admin_tingkat === 'kelompok') {
                $stmt->bind_param("is", $id, $admin_kelompok);
            } else {
                $stmt->bind_param("i", $id);
            }
            if ($stmt->execute()) {
                // === CCTV ===
                $deskripsi_log = "Menghapus *Template Pesan* :  *$tipe_pesan_log* ($kelompok_log - $kelas_log).";
                writeLog('DELETE', $deskripsi_log);
                // =================================
                $swal_notification = "
                Swal.fire({
                title: 'Berhasil!', 
                text: 'Template Berhasil dihapus.', 
                icon: 'success', 
                showConfirmButton: false, 
                timer: 2000
                }).then(() => { window.location = '$redirect_url'; });";
            } else {
                $error_message = 'Gagal menghapus template.';
                $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $error_message',
                        icon: 'error'
                    });
                ";
            }
            $stmt->close();
        }
    }
}

// === AMBIL DATA UNTUK DITAMPILKAN ===
$template_list = [];
$sql = "SELECT * FROM template_pesan";
// HAK AKSES: Filter data yang ditampilkan untuk admin kelompok
if ($admin_tingkat === 'kelompok') {
    $sql .= " WHERE (kelompok = ? OR kelompok IS NULL) AND tipe_pesan <> 'tambah_admin'";
}
if ($admin_role !== 'superadmin' && $admin_tingkat === 'kelompok') {
    $sql .= " AND tipe_pesan <> 'tambah_super_admin'";
}
if ($admin_role !== 'superadmin' && $admin_tingkat === 'desa') {
    $sql .= " WHERE tipe_pesan <> 'tambah_super_admin'";
}
$sql .= " ORDER BY tipe_pesan, kelompok, kelas";
$stmt = $conn->prepare($sql);
if ($admin_tingkat === 'kelompok') {
    $stmt->bind_param("s", $admin_kelompok);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $template_list[] = $row;
    }
}
?>
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Kelola Template Pesan</h3>
        <button id="tambahBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Tambah Template</button>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe Pesan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kelompok</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kelas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Isi Template</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($template_list)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">Belum ada template.</td>
                    </tr>
                    <?php else: foreach ($template_list as $template): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-semibold"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $template['tipe_pesan']))); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize font-semibold">
                                <?php echo htmlspecialchars($template['kelompok'] ?? 'Semua Kelompok'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize font-semibold"><?php echo htmlspecialchars($template['kelas']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <pre class="font-sans whitespace-pre-wrap"><?php echo htmlspecialchars($template['template']); ?></pre>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php
                                // HAK AKSES: Tampilkan tombol hanya jika diizinkan
                                $can_manage = ($admin_tingkat === 'desa' || ($admin_tingkat === 'kelompok' && $template['kelompok'] === $admin_kelompok));
                                if ($can_manage):
                                ?>
                                    <button class="edit-btn text-indigo-600 hover:text-indigo-900" data-template='<?php echo json_encode($template); ?>'>Edit</button>
                                    <button class="hapus-btn text-red-600 hover:text-red-900 ml-4" data-id="<?php echo $template['id']; ?>" data-info="<?php echo htmlspecialchars($template['tipe_pesan'] . ' - ' . $template['kelas']); ?>">Hapus</button>
                                <?php else: ?>
                                    <span class="text-gray-400 italic">Hanya Admin Desa</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div id="tambahModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pengaturan/template_pesan">
                <input type="hidden" name="action" value="tambah_template">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Tambah Template</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Tipe Pesan*</label>
                            <select name="tipe_pesan" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="notifikasi_alpa">Notifikasi Alpa</option>
                                <option value="jurnal_harian">Jurnal Harian</option>
                                <?php if ($admin_tingkat === 'desa'): ?>
                                    <option value="tambah_admin">Tambah Admin</option>
                                <?php endif; ?>
                                <?php if ($admin_role === 'superadmin'): ?>
                                    <option value="tambah_super_admin">Tambah Super Admin</option>
                                <?php endif; ?>
                                <option value="pengingat_jadwal_guru">Pengingat Guru</option>
                                <option value="pengingat_jadwal_penasehat">Pengingat Penasehat</option>
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium">Untuk Kelompok*</label>
                            <?php if ($admin_tingkat === 'kelompok'): ?>
                                <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                            <?php else: ?>
                                <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                    <option value="semua">Semua Kelompok (Umum)</option>
                                    <option value="bintaran">Bintaran</option>
                                    <option value="gedongkuning">Gedongkuning</option>
                                    <option value="jombor">Jombor</option>
                                    <option value="sunten">Sunten</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div><label class="block text-sm font-medium">Untuk Kelas*</label><select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="default">Default (Semua Kelas)</option>
                                <option value="paud">PAUD</option>
                                <option value="caberawit a">Caberawit A</option>
                                <option value="caberawit b">Caberawit B</option>
                                <option value="pra remaja">Pra Remaja</option>
                                <option value="remaja">Remaja</option>
                                <option value="pra nikah">Pra Nikah</option>
                            </select></div>
                        <div><label class="block text-sm font-medium">Isi Template*</label><textarea name="template" rows="5" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></textarea>
                            <p class="text-xs text-gray-500 mt-1">Gunakan placeholder: [nama], [tanggal], [kelas], [kelompok].</p>
                        </div>
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

<!-- Modal Edit -->
<div id="editModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pengaturan/template_pesan">
                <input type="hidden" name="action" value="edit_template">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Edit Template</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Tipe Pesan</label><input type="text" id="edit_tipe_pesan" class="mt-1 block w-full bg-gray-100 shadow-sm sm:text-sm border-gray-300 rounded-md" disabled></div>
                        <div><label class="block text-sm font-medium">Kelompok</label><input type="text" id="edit_kelompok" class="mt-1 block w-full bg-gray-100 shadow-sm sm:text-sm border-gray-300 rounded-md" disabled></div>
                        <div><label class="block text-sm font-medium">Kelas</label><input type="text" id="edit_kelas" class="mt-1 block w-full bg-gray-100 shadow-sm sm:text-sm border-gray-300 rounded-md" disabled></div>
                        <div><label class="block text-sm font-medium">Isi Template*</label><textarea name="edit_template" id="edit_template" rows="5" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></textarea>
                            <p class="text-xs text-gray-500 mt-1">Gunakan placeholder: [nama], [tanggal], [kelas], [kelompok].</p>
                        </div>
                    </div>
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
<div id="hapusModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pengaturan/template_pesan">
                <input type="hidden" name="action" value="hapus_template">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus template untuk <strong id="hapus_info"></strong>?</p>
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
        const modals = {
            tambah: document.getElementById('tambahModal'),
            edit: document.getElementById('editModal'),
            hapus: document.getElementById('hapusModal')
        };
        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');
        document.getElementById('tambahBtn').onclick = () => openModal(modals.tambah);
        Object.values(modals).forEach(modal => {
            if (modal) modal.addEventListener('click', e => {
                if (e.target === modal || e.target.closest('.modal-close-btn')) closeModal(modal);
            });
        });

        document.querySelector('tbody').addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.classList.contains('edit-btn')) {
                const data = JSON.parse(target.dataset.template);
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_tipe_pesan').value = data.tipe_pesan;
                document.getElementById('edit_kelompok').value = data.kelompok || 'Semua Kelompok';
                document.getElementById('edit_kelas').value = data.kelas;
                document.getElementById('edit_template').value = data.template;
                openModal(modals.edit);
            }

            if (target.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = target.dataset.id;
                document.getElementById('hapus_info').textContent = target.dataset.info;
                openModal(modals.hapus);
            }
        });

        <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php if ($_POST['action'] === 'tambah_template'): ?>
                openModal(modals.tambah);
            <?php elseif ($_POST['action'] === 'edit_template'): ?>
                openModal(modals.edit);
            <?php endif; ?>
        <?php endif; ?>
    });
</script>