<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Panggil helper yang dibutuhkan
require_once __DIR__ . '/../../helpers/fonnte_helper.php';
require_once __DIR__ . '/../../helpers/template_helper.php';

$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

$success_message = '';
$error_message = '';
$redirect_url = '';

// Ambil filter dari URL
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : null;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
}

$redirect_url_base = '?page=presensi/atur_penasehat&periode_id=' . $selected_periode_id . '&kelompok=' . urlencode($selected_kelompok) . '&kelas=' . urlencode($selected_kelas);

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI: TAMBAH PENASEHAT KE JADWAL ---
    if ($action === 'tambah_penasehat_jadwal') {
        $jadwal_id = $_POST['jadwal_id'] ?? 0;
        $penasehat_id = $_POST['penasehat_id'] ?? 0;
        $jam_mulai_pengingat = $_POST['jam_mulai_pengingat'] ?? '';

        if (empty($jadwal_id) || empty($penasehat_id) || empty($jam_mulai_pengingat)) {
            $error_message = 'Data tidak lengkap untuk menugaskan penasehat.';
        } else {
            $conn->begin_transaction();
            try {
                // 1. Tambahkan penasehat ke jadwal
                $sql_insert = "INSERT INTO jadwal_penasehat (jadwal_id, penasehat_id) VALUES (?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("ii", $jadwal_id, $penasehat_id);
                $stmt_insert->execute();

                // 2. Ambil data yang diperlukan untuk pesan
                $stmt_data = $conn->prepare("SELECT p.nama, p.nomor_wa, jp.tanggal, jp.kelas, jp.kelompok FROM penasehat p, jadwal_presensi jp WHERE jp.id = ? AND p.id = ?");
                $stmt_data->bind_param("ii", $jadwal_id, $penasehat_id);
                $stmt_data->execute();
                $data_pesan = $stmt_data->get_result()->fetch_assoc();

                // 3. Buat dan simpan pesan terjadwal
                if ($data_pesan && !empty($data_pesan['nomor_wa'])) {
                    // --- LOGIKA BARU DIMULAI DI SINI ---

                    // 1. Tentukan nilai default
                    $jam_pengingat = 4; // Default 4 jam

                    // 2. Ambil kelompok dan kelas dari data yang sedang diproses
                    $kelompok_jadwal = $data_pesan['kelompok'];
                    $kelas_jadwal = $data_pesan['kelas'];

                    // 3. Cari aturan khusus di database
                    $stmt_aturan = $conn->prepare("SELECT waktu_pengingat_jam FROM pengaturan_pengingat WHERE kelompok = ? AND kelas = ?");
                    $stmt_aturan->bind_param("ss", $kelompok_jadwal, $kelas_jadwal);
                    $stmt_aturan->execute();
                    $result_aturan = $stmt_aturan->get_result();

                    if ($result_aturan->num_rows > 0) {
                        // Jika aturan khusus ditemukan, timpa nilai default
                        $aturan = $result_aturan->fetch_assoc();
                        $jam_pengingat = (int)$aturan['waktu_pengingat_jam'];
                    }
                    $stmt_aturan->close();

                    // 4. Gunakan variabel dinamis $jam_pengingat untuk menghitung waktu kirim
                    $waktu_kirim = date('Y-m-d H:i:s', strtotime($data_pesan['tanggal'] . ' ' . $jam_mulai_pengingat . " -{$jam_pengingat} hours"));

                    // --- AKHIR LOGIKA BARU ---
                    // $waktu_kirim = date('Y-m-d H:i:s', strtotime($data_pesan['tanggal'] . ' ' . $jam_mulai_pengingat . ' -4 hours'));

                    $placeholders = [
                        '[nama]' => $data_pesan['nama'],
                        '[kelas]' => ucfirst($data_pesan['kelas']),
                        '[kelompok]' => ucfirst($data_pesan['kelompok']),
                        '[tanggal]' => date('d M Y', strtotime($data_pesan['tanggal'])),
                        '[jam]' => $jam_mulai_pengingat
                    ];
                    $pesan_final = getFormattedMessage($conn, 'pengingat_jadwal_penasehat', 'default', null, $placeholders);

                    $sql_pesan = "INSERT INTO pesan_terjadwal (jadwal_id, penerima_id, tipe_penerima, nomor_tujuan, isi_pesan, waktu_kirim, status) VALUES (?, ?, 'penasehat', ?, ?, ?, 'pending')";
                    $stmt_pesan = $conn->prepare($sql_pesan);
                    $stmt_pesan->bind_param("iisss", $jadwal_id, $penasehat_id, $data_pesan['nomor_wa'], $pesan_final, $waktu_kirim);
                    $stmt_pesan->execute();
                }

                $conn->commit();
                $redirect_url = $redirect_url_base . '&status=add_success';
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Gagal menugaskan penasehat. Mungkin penasehat sudah ditugaskan di jadwal ini.';
            }
        }
    }

    // --- AKSI: HAPUS PENASEHAT DARI JADWAL ---
    if ($action === 'hapus_penasehat_jadwal') {
        $jadwal_id = $_POST['jadwal_id'] ?? 0;
        $penasehat_id = $_POST['penasehat_id'] ?? 0;
        if (!empty($jadwal_id) && !empty($penasehat_id)) {
            $conn->begin_transaction();
            try {
                // Hapus penugasan penasehat
                $stmt_hapus = $conn->prepare("DELETE FROM jadwal_penasehat WHERE jadwal_id = ? AND penasehat_id = ?");
                $stmt_hapus->bind_param("ii", $jadwal_id, $penasehat_id);
                $stmt_hapus->execute();

                // Hapus pesan terjadwal terkait
                $stmt_hapus_pesan = $conn->prepare("DELETE FROM pesan_terjadwal WHERE jadwal_id = ? AND penerima_id = ? AND tipe_penerima = 'penasehat'");
                $stmt_hapus_pesan->bind_param("ii", $jadwal_id, $penasehat_id);
                $stmt_hapus_pesan->execute();

                $conn->commit();
                $redirect_url = $redirect_url_base . '&status=delete_success';
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Gagal menghapus penasehat dari jadwal.';
            }
        }
    }
}


if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Penasehat berhasil ditugaskan dan pengingat WA telah dijadwalkan!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Penasehat berhasil dihapus dari jadwal!';
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
if ($selected_periode_id && $selected_kelompok !== 'semua' && $selected_kelas !== 'semua') {
    $sql_jadwal = "SELECT jp.id, jp.tanggal, jp.jam_mulai, jp.jam_selesai, jp.kelas, jp.kelompok, COALESCE(pp.waktu_pengingat_jam, 4) AS jam_untuk_pengingat
                   FROM jadwal_presensi jp
                   LEFT JOIN pengaturan_pengingat pp ON jp.kelompok = pp.kelompok AND jp.kelas = pp.kelas
                   WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?";
    if ($admin_tingkat === 'kelompok') {
        $sql_jadwal .= " AND kelompok = '$admin_kelompok'";
    }
    $sql_jadwal .= " ORDER BY jp.tanggal DESC, jp.jam_mulai DESC";

    $stmt_jadwal = $conn->prepare($sql_jadwal);
    $stmt_jadwal->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_jadwal->execute();
    $result_jadwal = $stmt_jadwal->get_result();

    if ($result_jadwal) {
        while ($row = $result_jadwal->fetch_assoc()) {
            $row['penasehat_ditugaskan'] = [];
            $stmt_penasehat = $conn->prepare("SELECT p.id, p.nama FROM jadwal_penasehat jp JOIN penasehat p ON jp.penasehat_id = p.id WHERE jp.jadwal_id = ?");
            $stmt_penasehat->bind_param("i", $row['id']);
            $stmt_penasehat->execute();
            $result_penasehat_list = $stmt_penasehat->get_result();
            if ($result_penasehat_list) {
                while ($p_row = $result_penasehat_list->fetch_assoc()) {
                    $row['penasehat_ditugaskan'][] = $p_row;
                }
            }
            $jadwal_list[] = $row;
        }
    }
}

// Ambil semua penasehat untuk modal
$penasehat_options = [];
$result_penasehat_opts = $conn->query("SELECT id, nama FROM penasehat ORDER BY nama ASC");
if ($result_penasehat_opts) {
    while ($row = $result_penasehat_opts->fetch_assoc()) {
        $penasehat_options[] = $row;
    }
}
?>

<div class="container mx-auto space-y-6">
    <!-- FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Filter Jadwal</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="presensi/atur_penasehat">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div><label class="block text-sm font-medium">Periode</label><select name="periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required><?php foreach ($periode_list as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_periode']); ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled><input type="hidden" name="kelompok" value="<?php echo $admin_kelompok; ?>">
                    <?php else: ?>
                        <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                            <option value="semua" <?php echo ($selected_kelompok == 'semua') ? 'selected' : ''; ?>>-- Pilih Kelompok --</option>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div><label class="block text-sm font-medium">Kelas</label>
                    <select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                        <option value="semua" <?php echo ($selected_kelas == 'semua') ? 'selected' : ''; ?>>-- Pilih Kelas --</option>
                        <?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                        foreach ($kelas_opts as $k): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Tampilkan Jadwal</button></div>
            </div>
        </form>
    </div>

    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <!-- TABEL JADWAL -->
    <?php if ($selected_periode_id && $selected_kelompok !== 'semua' && $selected_kelas !== 'semua'): ?>
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <h3 class="text-xl font-medium text-gray-800 mb-4">Daftar Jadwal</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Tanggal & Jam</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Penasehat Bertugas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jadwal_list)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4">Tidak ada jadwal yang cocok dengan filter.</td>
                        </tr>
                        <?php else: foreach ($jadwal_list as $jadwal): ?>
                            <?php
                            // Ambil jam pengingat dinamis dari hasil query
                            $jam_pengingat = $jadwal['jam_untuk_pengingat'];
                            // Hitung waktu kirim WA
                            $waktu_kirim_wa = date('H:i', strtotime($jadwal['jam_mulai'] . " -{$jam_pengingat} hours"));
                            ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-medium"><?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date("H:i", strtotime($jadwal['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal['jam_selesai'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        <?php if (empty($jadwal['penasehat_ditugaskan'])): ?>
                                            <span class="text-gray-400 italic">Belum ada penasehat</span>
                                            <?php else: foreach ($jadwal['penasehat_ditugaskan'] as $p): ?>
                                                <div class="flex items-center justify-between group">
                                                    <span><?php echo htmlspecialchars($p['nama']); ?></span>
                                                    <form method="POST" action="<?php echo $redirect_url_base; ?>" class="inline ml-2 opacity-0 group-hover:opacity-100" onsubmit="return confirm('Anda yakin ingin menghapus penasehat ini dari jadwal? Pesan pengingat WA juga akan dibatalkan.');">
                                                        <input type="hidden" name="action" value="hapus_penasehat_jadwal">
                                                        <input type="hidden" name="jadwal_id" value="<?php echo $jadwal['id']; ?>">
                                                        <input type="hidden" name="penasehat_id" value="<?php echo $p['id']; ?>">
                                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">[Hapus]</button>
                                                    </form>
                                                </div>
                                        <?php endforeach;
                                        endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <button class="atur-penasehat-btn bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded text-sm"
                                        data-jadwal-id="<?php echo $jadwal['id']; ?>"
                                        data-jam-mulai="<?php echo date("H:i", strtotime($jadwal['jam_mulai'])); ?>"
                                        data-jam-selesai="<?php echo date("H:i", strtotime($jadwal['jam_selesai'])); ?>"
                                        data-waktu-kirim-wa="<?php echo $waktu_kirim_wa; ?>">+ Atur Penasehat</button>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Atur Penasehat -->
<div id="aturPenasehatModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="<?php echo $redirect_url_base; ?>">
                <input type="hidden" name="action" value="tambah_penasehat_jadwal">
                <input type="hidden" name="jadwal_id" id="modal_jadwal_id_p">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tugaskan Penasehat</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Pilih Penasehat*</label>
                            <select name="penasehat_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="">-- Pilih Penasehat --</option>
                                <?php foreach ($penasehat_options as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- <p class="text-sm text-gray-600">Jam di bawah ini hanya untuk placeholder di pesan pengingat WA.</p>
                        <div>
                            <label class="block text-sm font-medium">Jam Mulai Nasehat*</label>
                            <input type="time" name="jam_mulai_pengingat" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                        </div> -->

                        <div class="mt-4">
                            <p class="text-sm text-gray-600">Anda akan menjadwalkan pengingat WA untuk jadwal pada:</p>
                            <p id="modal_info_waktu" class="font-semibold text-gray-800 bg-gray-100 p-2 rounded-md text-center mt-1"></p>
                            <div class="mt-2 pt-2 border-t">
                                <p class="text-sm text-gray-600">Pengingat WA akan dikirim sekitar pukul:</p>
                                <p id="modal_info_waktu_kirim" class="font-bold text-lg text-cyan-600 text-center bg-cyan-50 p-2 rounded-md mt-1"></p>
                            </div>
                            <!-- Input tersembunyi untuk menyimpan nilai jam -->
                            <input type="hidden" name="jam_mulai_pengingat" id="modal_jam_mulai">
                            <input type="hidden" name="jam_selesai_pengingat" id="modal_jam_selesai">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan & Jadwalkan WA</button>
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

        const aturPenasehatModal = document.getElementById('aturPenasehatModal');
        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        if (aturPenasehatModal) {
            aturPenasehatModal.addEventListener('click', e => {
                if (e.target === aturPenasehatModal || e.target.closest('.modal-close-btn')) closeModal(aturPenasehatModal);
            });
        }

        document.querySelector('body').addEventListener('click', function(event) {
            const target = event.target.closest('.atur-penasehat-btn');
            if (target) {
                // document.getElementById('modal_jadwal_id_p').value = target.dataset.jadwalId;
                const jamMulai = target.dataset.jamMulai;
                const jamSelesai = target.dataset.jamSelesai;
                const waktuKirimWa = target.dataset.waktuKirimWa;

                // Isi nilai input tersembunyi (kode Anda yang sudah ada)
                document.getElementById('modal_jadwal_id_p').value = target.dataset.jadwalId;
                document.getElementById('modal_jam_mulai').value = jamMulai;
                document.getElementById('modal_jam_selesai').value = jamSelesai;
                document.getElementById('modal_info_waktu_kirim').textContent = waktuKirimWa;

                // ▼▼▼ TAMBAHKAN SATU BARIS INI ▼▼▼
                // Tampilkan informasi waktu kepada pengguna agar mereka tahu jadwal mana yang sedang diatur
                document.getElementById('modal_info_waktu').textContent = jamMulai.slice(0, 5) + ' - ' + jamSelesai.slice(0, 5);
                openModal(aturPenasehatModal);
            }
        });

        // Notifikasi otomatis hilang
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