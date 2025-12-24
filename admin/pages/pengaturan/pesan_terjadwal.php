<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$success_message = '';
$error_message = '';
$redirect_url = '';

// Konfigurasi Paginasi
$limit = 10; // Jumlah baris per halaman
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$offset = ($page - 1) * $limit;

// === BAGIAN BACKEND: PROSES REQUEST ===
// Cek apakah ini permintaan AJAX dari JavaScript
if (isset($_GET['action']) && $_GET['action'] === 'get_users') {
    header('Content-Type: application/json');
    $tipe = $_GET['tipe'] ?? '';
    $users = [];
    if ($tipe === 'guru') {
        $result = $conn->query("SELECT id, nama, nomor_wa FROM guru ORDER BY nama ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
    } elseif ($tipe === 'penasehat') {
        $result = $conn->query("SELECT id, nama, nomor_wa FROM penasehat ORDER BY nama ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
    }
    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

// === PROSES POST DARI FORM STANDAR (CRUD) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_pesan') {
        $tipe_user_tujuan = $_POST['tipe_user_tujuan'] ?? '';
        $user_tujuan_id = $_POST['user_tujuan_id'] ?? 0;
        $nomor_tujuan = $_POST['nomor_tujuan'] ?? '';
        $isi_pesan = $_POST['isi_pesan'] ?? '';
        $waktu_kirim = $_POST['waktu_kirim'] ?? '';

        if (empty($tipe_user_tujuan) || empty($user_tujuan_id) || empty($nomor_tujuan) || empty($isi_pesan) || empty($waktu_kirim)) {
            $error_message = 'Semua field wajib diisi.';
        } else {
            $sql = "INSERT INTO pesan_terjadwal (penerima_id, tipe_penerima, nomor_tujuan, isi_pesan, waktu_kirim, status) VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $user_tujuan_id, $tipe_user_tujuan, $nomor_tujuan, $isi_pesan, $waktu_kirim);
            if ($stmt->execute()) {
                $redirect_url = '?page=pengaturan/pesan_terjadwal&status=add_success';
            } else {
                $error_message = 'Gagal menambahkan pesan.';
            }
        }
    }

    if ($action === 'edit_pesan') {
        $id = $_POST['edit_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        $isi_pesan = $_POST['isi_pesan'] ?? '';
        $waktu_kirim = $_POST['waktu_kirim'] ?? '';

        if (empty($id) || empty($status) || empty($isi_pesan) || empty($waktu_kirim)) {
            $error_message = 'Data untuk edit tidak lengkap.';
        } else {
            $sql = "UPDATE pesan_terjadwal SET isi_pesan = ?, waktu_kirim = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $isi_pesan, $waktu_kirim, $status, $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=pengaturan/pesan_terjadwal&status=edit_success';
            } else {
                $error_message = 'Gagal memperbarui pesan.';
            }
        }
    }

    if ($action === 'hapus_pesan') {
        $id = $_POST['hapus_id'] ?? 0;
        if (!empty($id)) {
            $stmt = $conn->prepare("DELETE FROM pesan_terjadwal WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=pengaturan/pesan_terjadwal&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus pesan.';
            }
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Pesan baru berhasil dijadwalkan!';
    if ($_GET['status'] === 'edit_success') $success_message = 'Pesan berhasil diperbarui!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Pesan berhasil dihapus!';
}

// === AMBIL DATA DARI DATABASE ===
$total_results = $conn->query("SELECT COUNT(id) as total FROM pesan_terjadwal")->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);

$pesan_list = [];
$sql = "SELECT 
            pt.id, pt.nomor_tujuan, pt.isi_pesan, pt.waktu_kirim, pt.status, pt.tipe_penerima,
            COALESCE(g.nama, p.nama) as nama_penerima
        FROM pesan_terjadwal pt
        LEFT JOIN guru g ON pt.penerima_id = g.id AND pt.tipe_penerima = 'guru'
        LEFT JOIN penasehat p ON pt.penerima_id = p.id AND pt.tipe_penerima = 'penasehat'
        ORDER BY pt.waktu_kirim DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pesan_list[] = $row;
    }
}
?>

<div class="container mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-gray-700 text-2xl font-medium">Kelola Pesan Terjadwal</h3>
            <p class="text-sm text-gray-500">Daftar semua notifikasi otomatis yang akan dikirim oleh sistem.</p>
        </div>
        <!-- <button id="tambahBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">+ Jadwalkan Pesan</button> -->
    </div>

    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penerima</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Isi Pesan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu Kirim</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($pesan_list)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">Belum ada pesan terjadwal.</td>
                    </tr>
                    <?php else: foreach ($pesan_list as $pesan): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($pesan['nama_penerima'] ?? 'Penerima Tidak Dikenal'); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($pesan['nomor_tujuan']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 max-w-sm whitespace-pre-wrap"><?php echo htmlspecialchars($pesan['isi_pesan']); ?></td>
                            <td class="px-6 py-4 text-sm"><?php echo date('d M Y, H:i', strtotime($pesan['waktu_kirim'])); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php
                                    if ($pesan['status'] == 'terkirim') echo 'bg-green-100 text-green-800';
                                    elseif ($pesan['status'] == 'gagal') echo 'bg-red-100 text-red-800';
                                    else echo 'bg-yellow-100 text-yellow-800';
                                    ?>"><?php echo ucfirst($pesan['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($pesan['status'] !== 'terkirim'): ?>
                                    <button class="edit-btn text-indigo-600 hover:text-indigo-900"
                                        data-id="<?php echo $pesan['id']; ?>"
                                        data-isi="<?php echo htmlspecialchars($pesan['isi_pesan']); ?>"
                                        data-waktu="<?php echo date('Y-m-d\TH:i', strtotime($pesan['waktu_kirim'])); ?>"
                                        data-status="<?php echo $pesan['status']; ?>">Edit</button>
                                    <button class="hapus-btn text-red-600 hover:text-red-900 ml-4"
                                        data-id="<?php echo $pesan['id']; ?>"
                                        data-info="pesan ke <?php echo htmlspecialchars($pesan['nama_penerima'] ?? $pesan['nomor_tujuan']); ?>">Hapus</button>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-between items-center mt-4">
        <span class="text-sm text-gray-700">Menampilkan halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>
        <div>
            <?php if ($page > 1): ?><a href="?page=pengaturan/pesan_terjadwal&halaman=<?php echo $page - 1; ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-50">Sebelumnya</a><?php endif; ?>
            <?php if ($page < $total_pages): ?><a href="?page=pengaturan/pesan_terjadwal&halaman=<?php echo $page + 1; ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-50 ml-2">Berikutnya</a><?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tambah Pesan -->
<div id="tambahModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form id="tambahForm" method="POST" action="?page=pengaturan/pesan_terjadwal">
                <input type="hidden" name="action" value="tambah_pesan">
                <input type="hidden" name="nomor_tujuan" id="tambah_nomor_tujuan">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Jadwalkan Pesan Manual</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Tipe Penerima*</label>
                            <select name="tipe_user_tujuan" id="tambah_tipe_penerima" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="">-- Pilih Tipe --</option>
                                <option value="guru">Guru</option>
                                <option value="penasehat">Penasehat</option>
                            </select>
                        </div>
                        <div id="penerima_container" class="hidden">
                            <label class="block text-sm font-medium">Pilih Penerima*</label>
                            <select name="user_tujuan_id" id="tambah_penerima" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <!-- Opsi diisi oleh JavaScript -->
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium">Waktu Kirim*</label><input type="datetime-local" name="waktu_kirim" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Isi Pesan*</label><textarea name="isi_pesan" rows="5" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></textarea></div>
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

<!-- Modal Edit Pesan -->
<div id="editModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pengaturan/pesan_terjadwal">
                <input type="hidden" name="action" value="edit_pesan">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Pesan Terjadwal</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Waktu Kirim*</label><input type="datetime-local" name="waktu_kirim" id="edit_waktu_kirim" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Isi Pesan*</label><textarea name="isi_pesan" id="edit_isi_pesan" rows="5" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></textarea></div>
                        <div><label class="block text-sm font-medium">Status*</label><select name="status" id="edit_status" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="pending">Pending</option>
                                <option value="gagal">Gagal</option>
                            </select></div>
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

<!-- Modal Hapus Pesan -->
<div id="hapusModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pengaturan/pesan_terjadwal">
                <input type="hidden" name="action" value="hapus_pesan">
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
        <?php if (!empty($redirect_url)): ?>window.location.href = '<?php echo $redirect_url; ?>';
    <?php endif; ?>

    const modals = {
        tambah: document.getElementById('tambahModal'),
        edit: document.getElementById('editModal'),
        hapus: document.getElementById('hapusModal')
    };

    const openModal = (modal) => modal.classList.remove('hidden');
    const closeModal = (modal) => modal.classList.add('hidden');

    if (document.getElementById('tambahBtn')) {
        document.getElementById('tambahBtn').onclick = () => openModal(modals.tambah);
    }

    Object.values(modals).forEach(modal => {
        if (modal) modal.addEventListener('click', e => {
            if (e.target === modal || e.target.closest('.modal-close-btn')) closeModal(modal);
        });
    });

    document.querySelector('body').addEventListener('click', function(event) {
        const target = event.target.closest('button');
        if (!target) return;
        if (target.classList.contains('edit-btn')) {
            document.getElementById('edit_id').value = target.dataset.id;
            document.getElementById('edit_isi_pesan').value = target.dataset.isi;
            document.getElementById('edit_waktu_kirim').value = target.dataset.waktu;
            document.getElementById('edit_status').value = target.dataset.status;
            openModal(modals.edit);
        }
        if (target.classList.contains('hapus-btn')) {
            document.getElementById('hapus_id').value = target.dataset.id;
            document.getElementById('hapus_info').textContent = target.dataset.info;
            openModal(modals.hapus);
        }
    });

    const tipePenerimaSelect = document.getElementById('tambah_tipe_penerima');
    const penerimaContainer = document.getElementById('penerima_container');
    const penerimaSelect = document.getElementById('tambah_penerima');
    const nomorTujuanInput = document.getElementById('tambah_nomor_tujuan');

    if (tipePenerimaSelect) {
        tipePenerimaSelect.addEventListener('change', async function() {
            const tipe = this.value;
            penerimaSelect.innerHTML = '<option value="">Memuat...</option>';
            nomorTujuanInput.value = '';

            if (tipe) {
                penerimaContainer.classList.remove('hidden');
                try {
                    const response = await fetch(`?page=pengaturan/pesan_terjadwal&action=get_users&tipe=${tipe}`);
                    const result = await response.json();

                    penerimaSelect.innerHTML = '<option value="">-- Pilih Penerima --</option>';
                    if (result.success && result.data.length > 0) {
                        result.data.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = user.nama;
                            option.dataset.nomor = user.nomor_wa;
                            penerimaSelect.appendChild(option);
                        });
                    } else {
                        penerimaSelect.innerHTML = '<option value="" disabled>Tidak ada data</option>';
                    }
                } catch (error) {
                    penerimaSelect.innerHTML = '<option value="" disabled>Gagal memuat data</option>';
                }
            } else {
                penerimaContainer.classList.add('hidden');
            }
        });
    }

    if (penerimaSelect) {
        penerimaSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            nomorTujuanInput.value = selectedOption.dataset.nomor || '';
        });
    }

    const autoHideAlert = (alertId) => {
        const alertElement = document.getElementById(alertId);
        if (alertElement) {
            setTimeout(() => {
                alertElement.style.transition = 'opacity 0.5s ease';
                alertElement.style.opacity = '0';
                setTimeout(() => {
                    alertElement.style.display = 'none';
                }, 500);
            }, 3000);
        }
    };
    autoHideAlert('success-alert');
    autoHideAlert('error-alert');
    });
</script>