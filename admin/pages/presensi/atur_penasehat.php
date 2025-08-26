<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

$success_message = '';
$error_message = '';
$redirect_url = '';

// Ambil filter dari URL
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : null;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : null;
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : null;

if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
}
$redirect_url_base = '?page=presensi/atur_penasehat&periode_id=' . $selected_periode_id . '&kelompok=' . $selected_kelompok . '&kelas=' . $selected_kelas;

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'atur_penasehat') {
        $jadwal_id = $_POST['jadwal_id'] ?? 0;
        $penasehat_id = $_POST['penasehat_id'] ?? 0;
        $jam_mulai_nasehat = $_POST['jam_mulai_penasehat'] ?? '';
        $jam_selesai_nasehat = $_POST['jam_selesai_penasehat'] ?? '';

        // Jika penasehat dikosongkan, set jadi NULL
        if (empty($penasehat_id)) {
            $penasehat_id = null;
        }

        if (empty($jadwal_id)) {
            $error_message = 'Jadwal tidak valid.';
        } elseif ($penasehat_id !== null && (empty($jam_mulai_nasehat) || empty($jam_selesai_nasehat))) {
            $error_message = 'Jika menugaskan penasehat, jam mulai dan selesai nasehat wajib diisi untuk pengingat.';
        } else {
            $conn->begin_transaction();
            try {
                // 1. Hapus pengingat lama yang masih 'pending' untuk jadwal ini
                $stmt_delete = $conn->prepare("DELETE FROM pesan_terjadwal WHERE jadwal_id = ? AND status = 'pending'");
                $stmt_delete->bind_param("i", $jadwal_id);
                $stmt_delete->execute();

                // 2. Update penasehat_id di jadwal_presensi
                $stmt_update = $conn->prepare("UPDATE jadwal_presensi SET penasehat_id = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $penasehat_id, $jadwal_id);
                $stmt_update->execute();

                // 3. Jika penasehat baru ditambahkan, buat pengingat baru
                if ($penasehat_id !== null) {
                    $sql_data = "SELECT p.nama, p.nomor_wa, j.tanggal, j.jam_mulai, j.kelas, j.kelompok 
                                 FROM penasehat p JOIN jadwal_presensi j ON j.id = ? WHERE p.id = ?";
                    $stmt_data = $conn->prepare($sql_data);
                    $stmt_data->bind_param("ii", $jadwal_id, $penasehat_id);
                    $stmt_data->execute();
                    $data_notif = $stmt_data->get_result()->fetch_assoc();

                    if ($data_notif && !empty($data_notif['nomor_wa'])) {
                        $waktu_jadwal = new DateTime($data_notif['tanggal'] . ' ' . $data_notif['jam_mulai']);
                        $waktu_jadwal->modify('-4 hours');
                        $waktu_kirim = $waktu_jadwal->format('Y-m-d H:i:s');

                        $data_pesan = [
                            '[nama]' => $data_notif['nama'],
                            '[tanggal]' => date("d M Y", strtotime($data_notif['tanggal'])),
                            '[jam]' => date("H:i", strtotime($jam_mulai_nasehat)) . ' - ' . date("H:i", strtotime($jam_selesai_nasehat)),
                            '[kelas]' => ucfirst($data_notif['kelas']),
                            '[kelompok]' => ucfirst($data_notif['kelompok'])
                        ];
                        $pesan_final = getFormattedMessage($conn, 'pengingat_jadwal_penasehat', 'default', null, $data_pesan);

                        $stmt_pesan = $conn->prepare("INSERT INTO pesan_terjadwal (jadwal_id, nomor_tujuan, isi_pesan, waktu_kirim) VALUES (?, ?, ?, ?)");
                        $stmt_pesan->bind_param("isss", $jadwal_id, $data_notif['nomor_wa'], $pesan_final, $waktu_kirim);
                        $stmt_pesan->execute();
                    }
                }

                $conn->commit();
                $redirect_url = $redirect_url_base . '&status=atur_penasehat_success';
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Gagal mengatur penasehat: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'atur_penasehat_success') {
    $success_message = 'Penasehat berhasil diatur untuk jadwal ini!';
}

// === AMBIL DATA DARI DATABASE ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

$jadwal_list = [];
if ($selected_periode_id && $selected_kelompok && $selected_kelas) {
    $sql_jadwal = "SELECT jp.*, p.nama as nama_penasehat 
                   FROM jadwal_presensi jp 
                   LEFT JOIN penasehat p ON jp.penasehat_id = p.id
                   WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ? 
                   ORDER BY jp.tanggal DESC, jp.jam_mulai DESC";
    $stmt_jadwal = $conn->prepare($sql_jadwal);
    $stmt_jadwal->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_jadwal->execute();
    $result_jadwal = $stmt_jadwal->get_result();
    if ($result_jadwal) {
        while ($row = $result_jadwal->fetch_assoc()) {
            $jadwal_list[] = $row;
        }
    }
}

$penasehat_list = [];
$result_penasehat = $conn->query("SELECT id, nama FROM penasehat ORDER BY nama ASC");
if ($result_penasehat) {
    while ($row = $result_penasehat->fetch_assoc()) {
        $penasehat_list[] = $row;
    }
}
?>
<div class="container mx-auto space-y-6">
    <!-- BAGIAN 1: FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Pilih Jadwal untuk Mengatur Penasehat</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="presensi/atur_penasehat">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div><label class="block text-sm font-medium">Periode</label><select name="periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required><?php foreach ($periode_list as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_periode']); ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled><input type="hidden" name="kelompok" value="<?php echo $admin_kelompok; ?>">
                    <?php else: ?>
                        <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div><label class="block text-sm font-medium">Kelas</label><select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required><?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                                                                                                                                                                                foreach ($kelas_opts as $k): ?><?php if ($admin_tingkat === 'kelompok' && $k === 'remaja') continue; ?><option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option><?php endforeach; ?></select></div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Tampilkan</button></div>
            </div>
        </form>
    </div>

    <!-- BAGIAN 2: MANAJEMEN JADWAL -->
    <?php if ($selected_periode_id && $selected_kelompok && $selected_kelas): ?>
        <div id="jadwal-section">
            <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
            <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>
            <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Tanggal & Jam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Penasehat</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jadwal_list)): ?><tr>
                                <td colspan="3" class="text-center py-4">Tidak ada jadwal yang cocok dengan filter.</td>
                            </tr>
                            <?php else: foreach ($jadwal_list as $jadwal): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo date("H:i", strtotime($jadwal['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal['jam_selesai'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($jadwal['nama_penasehat'])): ?>
                                            <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($jadwal['nama_penasehat']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">Belum Diatur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <button class="atur-penasehat-btn text-green-600 hover:text-green-900" data-jadwal='<?php echo json_encode($jadwal); ?>'>Atur Penasehat</button>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Atur Penasehat -->
<div id="aturPenasehatModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="<?php echo $redirect_url_base; ?>">
                <input type="hidden" name="action" value="atur_penasehat">
                <input type="hidden" name="jadwal_id" id="atur_jadwal_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Atur Penasehat</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Pilih Penasehat</label>
                            <select name="penasehat_id" id="penasehat_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                                <option value="">-- Kosongkan / Hapus Penasehat --</option>
                                <?php foreach ($penasehat_list as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium">Jam Mulai Nasehat (untuk WA)</label><input type="time" name="jam_mulai_penasehat" id="jam_mulai_penasehat" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                            <div><label class="block text-sm font-medium">Jam Selesai Nasehat (untuk WA)</label><input type="time" name="jam_selesai_penasehat" id="jam_selesai_penasehat" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
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

        const aturPenasehatModal = document.getElementById('aturPenasehatModal');
        const jadwalSection = document.getElementById('jadwal-section');

        if (jadwalSection) {
            jadwalSection.addEventListener('click', function(e) {
                if (e.target.classList.contains('atur-penasehat-btn')) {
                    const data = JSON.parse(e.target.dataset.jadwal);
                    document.getElementById('atur_jadwal_id').value = data.id;
                    document.getElementById('penasehat_id').value = data.penasehat_id || '';
                    // Kosongkan field jam saat modal dibuka
                    document.getElementById('jam_mulai_penasehat').value = '';
                    document.getElementById('jam_selesai_penasehat').value = '';
                    aturPenasehatModal.classList.remove('hidden');
                }
            });
        }

        if (aturPenasehatModal) {
            aturPenasehatModal.addEventListener('click', e => {
                if (e.target === aturPenasehatModal || e.target.closest('.modal-close-btn')) {
                    aturPenasehatModal.classList.add('hidden');
                }
            });
        }
    });
</script>