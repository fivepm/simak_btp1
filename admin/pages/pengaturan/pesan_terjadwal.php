<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$success_message = '';
$error_message = '';
$redirect_url = '';

// --- PENGATURAN PAGINASI ---
$limit = 10; // Jumlah baris per halaman
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'simpan_pesan') {
        $id = $_POST['id'] ?? 0;
        $nomor_tujuan = trim($_POST['nomor_tujuan'] ?? '');
        $isi_pesan = trim($_POST['isi_pesan'] ?? '');
        $waktu_kirim = $_POST['waktu_kirim'] ?? '';
        $status = $_POST['status'] ?? 'pending';

        if (empty($nomor_tujuan) || empty($isi_pesan) || empty($waktu_kirim)) {
            $error_message = 'Nomor Tujuan, Isi Pesan, dan Waktu Kirim wajib diisi.';
        }

        if (empty($error_message)) {
            if (empty($id)) { // Proses Tambah
                $sql = "INSERT INTO pesan_terjadwal (nomor_tujuan, isi_pesan, waktu_kirim, status) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $nomor_tujuan, $isi_pesan, $waktu_kirim, $status);
            } else { // Proses Edit
                $sql = "UPDATE pesan_terjadwal SET nomor_tujuan=?, isi_pesan=?, waktu_kirim=?, status=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $nomor_tujuan, $isi_pesan, $waktu_kirim, $status, $id);
            }

            if ($stmt->execute()) {
                $redirect_url = '?page=pengaturan/pesan_terjadwal&status=save_success';
            } else {
                $error_message = 'Gagal menyimpan pesan.';
            }
            $stmt->close();
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
            $stmt->close();
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'save_success') $success_message = 'Pesan berhasil disimpan!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Pesan berhasil dihapus!';
}

// === AMBIL DATA UNTUK DITAMPILKAN ===
// Hitung total data untuk paginasi
$total_records_result = $conn->query("SELECT COUNT(id) as total FROM pesan_terjadwal");
$total_records = $total_records_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// Ambil data sesuai halaman
$pesan_list = [];
// PERUBAHAN: Query sekarang mencari nama pemilik nomor
$sql = "SELECT 
            pt.*,
            COALESCE(g.nama, pn.nama, CONCAT('Ortu dari ', p.nama_lengkap)) as nama_tujuan
        FROM 
            pesan_terjadwal pt
        LEFT JOIN 
            guru g ON pt.nomor_tujuan = g.nomor_wa
        LEFT JOIN 
            peserta p ON pt.nomor_tujuan = p.nomor_hp_orang_tua
        LEFT JOIN
            penasehat pn ON pt.nomor_tujuan = pn.nomor_wa
        ORDER BY 
            pt.waktu_kirim DESC 
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
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Pesan Terjadwal</h3>
        <button id="tambahBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Tambah Pesan</button>
    </div>

    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu Kirim</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tujuan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Isi Pesan</th>
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
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo date("d M Y, H:i", strtotime($pesan['waktu_kirim'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <!-- PERUBAHAN: Tampilkan nama jika ada -->
                                <?php if (!empty($pesan['nama_tujuan'])): ?>
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($pesan['nama_tujuan']); ?></div>
                                <?php endif; ?>
                                <div class="text-sm text-gray-500 font-mono"><?php echo htmlspecialchars($pesan['nomor_tujuan']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 max-w-sm truncate"><?php echo htmlspecialchars($pesan['isi_pesan']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            <?php if ($pesan['status'] == 'terkirim') echo 'bg-green-100 text-green-800';
                            elseif ($pesan['status'] == 'gagal') echo 'bg-red-100 text-red-800';
                            else echo 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst($pesan['status']); ?>
                                </span></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($pesan['status'] !== 'terkirim'): ?>
                                    <button class="edit-btn text-indigo-600 hover:text-indigo-900" data-pesan='<?php echo json_encode($pesan); ?>'>Edit</button>
                                    <button class="hapus-btn text-red-600 hover:text-red-900 ml-4" data-id="<?php echo $pesan['id']; ?>" data-info="pesan ke <?php echo htmlspecialchars($pesan['nomor_tujuan']); ?>">Hapus</button>
                                <?php else: ?>
                                    <span class="text-gray-400 italic">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINASI -->
    <div class="mt-6 flex justify-between items-center">
        <span class="text-sm text-gray-700">Menampilkan <?php echo count($pesan_list); ?> dari <?php echo $total_records; ?> data</span>
        <div class="flex items-center space-x-1">
            <?php if ($page > 1): ?>
                <a href="?page=pengaturan/pesan_terjadwal&p=<?php echo $page - 1; ?>" class="px-3 py-1 bg-gray-200 rounded-md text-sm hover:bg-gray-300">&laquo;</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=pengaturan/pesan_terjadwal&p=<?php echo $i; ?>" class="px-3 py-1 rounded-md text-sm <?php echo ($i == $page) ? 'bg-green-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=pengaturan/pesan_terjadwal&p=<?php echo $page + 1; ?>" class="px-3 py-1 bg-gray-200 rounded-md text-sm hover:bg-gray-300">&raquo;</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div id="formModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pengaturan/pesan_terjadwal">
                <input type="hidden" name="action" value="simpan_pesan">
                <input type="hidden" name="id" id="pesan_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 id="modalTitle" class="text-lg font-medium text-gray-900 mb-4">Form Pesan</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nomor Tujuan*</label><input type="text" name="nomor_tujuan" id="nomor_tujuan" placeholder="Contoh: 628123456789" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Waktu Kirim*</label><input type="datetime-local" name="waktu_kirim" id="waktu_kirim" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Status*</label><select name="status" id="status" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                                <option value="pending">Pending</option>
                                <option value="terkirim">Terkirim</option>
                                <option value="gagal">Gagal</option>
                            </select></div>
                        <div><label class="block text-sm font-medium">Isi Pesan*</label><textarea name="isi_pesan" id="isi_pesan" rows="5" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></textarea></div>
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
        const btnTambah = document.getElementById('tambahBtn');

        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        btnTambah.onclick = () => {
            formModal.querySelector('form').reset();
            document.getElementById('pesan_id').value = '';
            document.getElementById('modalTitle').textContent = 'Form Tambah Pesan';
            openModal(formModal);
        };

        document.querySelectorAll('.modal-close-btn').forEach(btn => {
            btn.onclick = () => {
                closeModal(formModal);
                closeModal(hapusModal);
            };
        });

        document.querySelector('tbody').addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.classList.contains('edit-btn')) {
                const data = JSON.parse(target.dataset.pesan);
                document.getElementById('modalTitle').textContent = 'Form Edit Pesan';
                document.getElementById('pesan_id').value = data.id;
                document.getElementById('nomor_tujuan').value = data.nomor_tujuan;
                document.getElementById('isi_pesan').value = data.isi_pesan;
                // Format datetime-local (YYYY-MM-DDTHH:mm)
                document.getElementById('waktu_kirim').value = data.waktu_kirim.replace(' ', 'T');
                document.getElementById('status').value = data.status;
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