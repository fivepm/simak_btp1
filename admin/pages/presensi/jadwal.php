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

// Jika admin tingkat kelompok, paksa filter kelompok
if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
}

$redirect_url_base = '?page=presensi/jadwal&periode_id=' . $selected_periode_id . '&kelompok=' . $selected_kelompok . '&kelas=' . $selected_kelas;

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI: TAMBAH JADWAL ---
    if ($action === 'tambah_jadwal') {
        $periode_id = $_POST['periode_id'] ?? 0;
        $kelompok = $_POST['kelompok'] ?? '';
        $kelas = $_POST['kelas'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        $jam_mulai = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?? '';

        if (empty($periode_id) || empty($kelompok) || empty($kelas) || empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) {
            $error_message = 'Gagal: Data filter dan jadwal tidak lengkap.';
        } else {
            $conn->begin_transaction();
            try {
                $sql_jadwal = "INSERT INTO jadwal_presensi (periode_id, kelas, kelompok, tanggal, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_jadwal = $conn->prepare($sql_jadwal);
                $stmt_jadwal->bind_param("isssss", $periode_id, $kelas, $kelompok, $tanggal, $jam_mulai, $jam_selesai);
                $stmt_jadwal->execute();
                $jadwal_id = $stmt_jadwal->insert_id;

                $sql_peserta = "SELECT id FROM peserta WHERE kelas = ? AND kelompok = ? AND status = 'Aktif'";
                $stmt_peserta = $conn->prepare($sql_peserta);
                $stmt_peserta->bind_param("ss", $kelas, $kelompok);
                $stmt_peserta->execute();
                $result_peserta = $stmt_peserta->get_result();

                if ($result_peserta->num_rows > 0) {
                    $sql_rekap = "INSERT INTO rekap_presensi (jadwal_id, peserta_id, status_kehadiran) VALUES (?, ?, 'Alpa')";
                    $stmt_rekap = $conn->prepare($sql_rekap);
                    while ($peserta = $result_peserta->fetch_assoc()) {
                        $stmt_rekap->bind_param("ii", $jadwal_id, $peserta['id']);
                        $stmt_rekap->execute();
                    }
                    $stmt_rekap->close();
                }
                $conn->commit();
                $redirect_url = $redirect_url_base . '&status=add_success';
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Gagal menambahkan jadwal: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Jadwal baru berhasil dibuat dan rekap peserta telah digenerate!';
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
    $sql_jadwal = "SELECT jp.*, g.nama as nama_pengajar , p.nama as nama_penasehat
                   FROM jadwal_presensi jp 
                   LEFT JOIN guru g ON jp.guru_id = g.id
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

?>
<div class="container mx-auto space-y-6">
    <!-- BAGIAN 1: FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Filter Jadwal</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="presensi/jadwal">
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
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-gray-700 text-2xl font-medium">Daftar Jadwal</h3><button id="tambahJadwalBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Tambah Jadwal</button>
            </div>
            <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
            <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>
            <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Tanggal & Jam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Pengajar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Penasehat</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jadwal_list)): ?><tr>
                                <td colspan="3" class="text-center py-4">Belum ada jadwal. Silakan tambahkan.</td>
                            </tr>
                            <?php else: foreach ($jadwal_list as $jadwal): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo date("H:i", strtotime($jadwal['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal['jam_selesai'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($jadwal['nama_pengajar'])): ?>
                                            <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($jadwal['nama_pengajar']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">Belum Diatur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($jadwal['nama_penasehat'])): ?>
                                            <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($jadwal['nama_penasehat']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">Belum Diatur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <a href="?page=presensi/input_presensi&jadwal_id=<?php echo $jadwal['id']; ?>" class="text-indigo-600 hover:text-indigo-900 ml-4 font-bold">Presensi</a>
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

<!-- Modal Tambah Jadwal -->
<div id="tambahJadwalModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="<?php echo $redirect_url_base; ?>">
                <input type="hidden" name="action" value="tambah_jadwal">
                <input type="hidden" name="periode_id" value="<?php echo $selected_periode_id; ?>">
                <input type="hidden" name="kelompok" value="<?php echo $selected_kelompok; ?>">
                <input type="hidden" name="kelas" value="<?php echo $selected_kelas; ?>">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tambah Jadwal Cepat</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Tanggal*</label><input type="date" name="tanggal" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium">Jam Mulai*</label><input type="time" name="jam_mulai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                            <div><label class="block text-sm font-medium">Jam Selesai*</label><input type="time" name="jam_selesai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"><button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan Jadwal</button><button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button></div>
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

        const tambahModal = document.getElementById('tambahJadwalModal');
        const btnTambah = document.getElementById('tambahJadwalBtn');
        if (btnTambah) btnTambah.onclick = () => tambahModal.classList.remove('hidden');
        if (tambahModal) tambahModal.addEventListener('click', e => {
            if (e.target === tambahModal || e.target.closest('.modal-close-btn')) tambahModal.classList.add('hidden');
        });

        const aturGuruModal = document.getElementById('aturGuruModal');
        document.querySelector('#jadwal-section')?.addEventListener('click', function(e) {
            if (e.target.classList.contains('atur-guru-btn')) {
                const jadwalId = e.target.dataset.jadwalId;
                document.getElementById('atur_jadwal_id').value = jadwalId;
                aturGuruModal.classList.remove('hidden');
            }
        });
        if (aturGuruModal) aturGuruModal.addEventListener('click', e => {
            if (e.target === aturGuruModal || e.target.closest('.modal-close-btn')) aturGuruModal.classList.add('hidden');
        });

        <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php if ($_POST['action'] === 'tambah_jadwal'): ?> document.getElementById('tambahJadwalModal').classList.remove('hidden');
            <?php elseif ($_POST['action'] === 'atur_guru'): ?> document.getElementById('aturGuruModal').classList.remove('hidden');
            <?php endif; ?>
        <?php endif; ?>
    });
</script>