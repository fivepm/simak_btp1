<?php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
$jadwal_id = isset($_GET['jadwal_id']) ? (int)$_GET['jadwal_id'] : 0;

if ($jadwal_id === 0) {
    echo '<div class="bg-red-100 border-red-400 text-red-700 p-4 rounded-lg">ID Jadwal tidak valid.</div>';
    return;
}

$success_message = '';
$error_message = '';
$redirect_url = '';

// Ambil data jadwal sekali di awal untuk digunakan di backend dan frontend
$jadwal = $conn->query("SELECT * FROM jadwal_presensi WHERE id = $jadwal_id")->fetch_assoc();

// PERBAIKAN 2: Buat URL kembali yang benar dengan filter yang sudah ada
$back_url = '?page=presensi/jadwal&periode_id=' . ($jadwal['periode_id'] ?? '') . '&kelompok=' . ($jadwal['kelompok'] ?? '') . '&kelas=' . ($jadwal['kelas'] ?? '');

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'simpan_jurnal') {
        $pengajar = $_POST['pengajar'] ?? '';
        $materi1 = $_POST['materi1'] ?? '';
        $materi2 = $_POST['materi2'] ?? '';
        $materi3 = $_POST['materi3'] ?? '';

        // Siapkan data dari jadwal untuk placeholder
        $tanggal_jadwal = date("d M Y", strtotime($jadwal['tanggal']));
        $kelas_jadwal = $jadwal['kelas'];
        $kelompok_jadwal = $jadwal['kelompok'];

        if (empty($pengajar)) {
            $error_message = 'Nama Pengajar wajib diisi.';
        } else {
            $sql = "UPDATE jadwal_presensi SET pengajar=?, materi1=?, materi2=?, materi3=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $pengajar, $materi1, $materi2, $materi3, $jadwal_id);
            if ($stmt->execute()) {
                // === KIRIM NOTIFIKASI JURNAL KE GRUP WA ===
                $target_group_id = getGroupId($conn, $jadwal['kelompok'], $jadwal['kelas']);
                // =======================================================
                // === DI SINILAH PROSES PENGIRIMAN NOTIFIKASI TERJADI ===
                // =======================================================
                $data_untuk_pesan = [
                    '[nama]' => $pengajar,
                    '[tanggal]' => $tanggal_jadwal,
                    '[kelas]' => ucfirst($kelas_jadwal),
                    '[kelompok]' => ucfirst($kelompok_jadwal),
                    '[materi1]' => $materi1,
                    '[materi2]' => $materi2,
                    '[materi3]' => $materi3
                ];
                $pesan_final = getFormattedMessage($conn, 'jurnal_harian', $kelas_jadwal, $kelompok_jadwal, $data_untuk_pesan);
                // KIRIM PESAN DISINI
                kirimPesanFonnte($target_group_id, $pesan_final, 10);
                $redirect_url = '?page=presensi/input_presensi&jadwal_id=' . $jadwal_id . '&status=jurnal_success';
            } else {
                $error_message = 'Gagal menyimpan jurnal.';
            }
        }
    }
    if ($action === 'simpan_kehadiran') {
        $kehadiran_data = $_POST['kehadiran'] ?? [];
        $keterangan_data = $_POST['keterangan'] ?? [];
        // Ambil juga data nomor HP orang tua dari input hidden
        $nomor_hp_ortu_data = $_POST['nomor_hp_ortu'] ?? [];
        $kirim_wa_data = $_POST['kirim_wa'] ?? [];
        $nama_peserta_data = $_POST['nama_peserta'] ?? []; // Ambil nama peserta

        // Siapkan data dari jadwal untuk placeholder
        $tanggal_jadwal = date("d M Y", strtotime($jadwal['tanggal']));
        $kelas_jadwal = $jadwal['kelas'];
        $kelompok_jadwal = $jadwal['kelompok'];

        if (empty($kehadiran_data)) {
            $error_message = "Tidak ada data kehadiran yang dikirim.";
        } else {
            $conn->begin_transaction();
            try {
                $sql = "UPDATE rekap_presensi SET status_kehadiran = ?, keterangan = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);

                foreach ($kehadiran_data as $rekap_id => $status) {
                    $keterangan = $keterangan_data[$rekap_id] ?? '';
                    $nomor_hp_ortu = $nomor_hp_ortu_data[$rekap_id] ?? '';
                    $kirim_wa = $kirim_wa_data[$rekap_id] ?? '';
                    $nama_peserta = $nama_peserta_data[$rekap_id] ?? 'Peserta';

                    if (($status === 'Izin' || $status === 'Sakit') && empty($keterangan)) {
                        throw new Exception("Keterangan wajib diisi untuk status Izin/Sakit.");
                    }

                    $stmt->bind_param("ssi", $status, $keterangan, $rekap_id);
                    $stmt->execute();

                    // =======================================================
                    // === DI SINILAH PROSES PENGIRIMAN NOTIFIKASI TERJADI ===
                    // =======================================================
                    if ($status === 'Alpa' && !empty($nomor_hp_ortu) && $kirim_wa === 'no') {
                        // 1. Siapkan data pengganti placeholder
                        $data_untuk_pesan = [
                            '[nama]' => $nama_peserta,
                            '[tanggal]' => $tanggal_jadwal,
                            '[kelas]' => ucfirst($kelas_jadwal),
                            '[kelompok]' => ucfirst($kelompok_jadwal)
                        ];

                        // 2. Panggil fungsi untuk mendapatkan pesan final dari template
                        $pesan_final = getFormattedMessage($conn, 'notifikasi_alpa', $kelas_jadwal, $kelompok_jadwal, $data_untuk_pesan);

                        // 3. Panggil fungsi untuk mengirim pesan
                        // Hapus komentar di bawah ini untuk mengaktifkan pengiriman pesan
                        $berhasil = kirimPesanFonnte($nomor_hp_ortu, $pesan_final, 10);
                        if ($berhasil) {
                            $sqlUpdateWa = "UPDATE rekap_presensi SET kirim_wa = 'yes' WHERE id = ?";
                            $stmtUpdateWa = $conn->prepare($sqlUpdateWa);
                            $stmtUpdateWa->bind_param("i", $rekap_id);
                            $stmtUpdateWa->execute();
                        }
                    }
                }
                $conn->commit();
                $redirect_url = '?page=presensi/input_presensi&jadwal_id=' . $jadwal_id . '&status=kehadiran_success';
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Gagal menyimpan: " . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'jurnal_success') $success_message = 'Jurnal harian berhasil disimpan!';
    if ($_GET['status'] === 'kehadiran_success') $success_message = 'Data kehadiran berhasil disimpan!';
}

// === AMBIL DATA DARI DATABASE ===
$jadwal = $conn->query("SELECT * FROM jadwal_presensi WHERE id = $jadwal_id")->fetch_assoc();
$peserta_presensi = [];
// PERUBAHAN 1: Tambahkan p.nomor_hp_orang_tua ke query
$sql_presensi = "SELECT rp.id, rp.status_kehadiran, rp.keterangan, p.nama_lengkap, p.nomor_hp_orang_tua, rp.kirim_wa 
                 FROM rekap_presensi rp 
                 JOIN peserta p ON rp.peserta_id = p.id 
                 WHERE rp.jadwal_id = ? 
                 ORDER BY p.nama_lengkap ASC";
$stmt_presensi = $conn->prepare($sql_presensi);
$stmt_presensi->bind_param("i", $jadwal_id);
$stmt_presensi->execute();
$result_presensi = $stmt_presensi->get_result();
if ($result_presensi) {
    while ($row = $result_presensi->fetch_assoc()) {
        $peserta_presensi[] = $row;
    }
}
?>
<div class="container mx-auto">
    <div class="mb-6"><a href="<?php echo $back_url; ?>" class="text-indigo-600 hover:underline">&larr; Kembali ke Daftar Jadwal</a></div>
    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- KARTU 1: JURNAL HARIAN -->
        <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-medium text-gray-800 mb-4">Jurnal Harian</h3>
            <p class="text-sm text-gray-500 mb-4">Untuk jadwal: <strong class="capitalize"><?php echo htmlspecialchars($jadwal['kelas'] . ' - ' . $jadwal['kelompok']); ?></strong> pada <strong><?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?></strong></p>
            <form method="POST" action="?page=presensi/input_presensi&jadwal_id=<?php echo $jadwal_id; ?>">
                <input type="hidden" name="action" value="simpan_jurnal">
                <div class="space-y-4">
                    <div><label class="block text-sm font-medium">Nama Pengajar*</label><input type="text" name="pengajar" value="<?php echo htmlspecialchars($jadwal['pengajar'] ?? ''); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                    <div><label class="block text-sm font-medium">Materi 1</label><textarea name="materi1" rows="2" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($jadwal['materi1'] ?? ''); ?></textarea></div>
                    <div><label class="block text-sm font-medium">Materi 2</label><textarea name="materi2" rows="2" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($jadwal['materi2'] ?? ''); ?></textarea></div>
                    <div><label class="block text-sm font-medium">Materi 3</label><textarea name="materi3" rows="2" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($jadwal['materi3'] ?? ''); ?></textarea></div>
                    <div class="text-right"><button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Simpan Jurnal</button></div>
                </div>
            </form>
        </div>

        <!-- KARTU 2: INPUT KEHADIRAN -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md min-w-0">
            <h3 class="text-xl font-medium text-gray-800 mb-4">Input Kehadiran Peserta</h3>
            <form method="POST" action="?page=presensi/input_presensi&jadwal_id=<?php echo $jadwal_id; ?>">
                <input type="hidden" name="action" value="simpan_kehadiran">
                <div class="overflow-x-auto overflow-y-auto max-h-[60vh]">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="py-3 text-left text-xs text-center font-medium text-gray-500">Nama</th>
                                <th class="py-3 text-left text-xs text-center font-medium text-gray-500">Status</th>
                                <th class="py-3 text-left text-xs text-center font-medium text-gray-500">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="presensiTableBody">
                            <?php foreach ($peserta_presensi as $peserta): $rekap_id = $peserta['id']; ?>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        <?php echo htmlspecialchars($peserta['nama_lengkap']); ?>
                                        <!-- PERUBAHAN 2: Tambahkan input hidden untuk nomor HP Ortu -->
                                        <input type="hidden" name="nomor_hp_ortu[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['nomor_hp_orang_tua'] ?? ''); ?>">
                                        <!-- Input hidden BARU untuk nama peserta -->
                                        <input type="hidden" name="nama_peserta[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['nama_lengkap']); ?>">
                                        <input type="hidden" name="kirim_wa[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['kirim_wa'] ?? ''); ?>">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-3 space-y-2 sm:space-y-0 text-sm"><?php foreach (['Hadir', 'Izin', 'Sakit', 'Alpa'] as $status): ?><label class="flex items-center"><input type="radio" name="kehadiran[<?php echo $rekap_id; ?>]" value="<?php echo $status; ?>" class="h-4 w-4 status-radio" data-keterangan-id="keterangan-<?php echo $rekap_id; ?>" <?php echo ($peserta['status_kehadiran'] === $status) ? 'checked' : ''; ?>><span class="ml-1"><?php echo $status; ?></span></label><?php endforeach; ?></div>
                                    </td>
                                    <td class="px-6 py-4 min-w-52"><input type="text" name="keterangan[<?php echo $rekap_id; ?>]" id="keterangan-<?php echo $rekap_id; ?>" value="<?php echo htmlspecialchars($peserta['keterangan'] ?? ''); ?>" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 text-right"><button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg">Simpan Kehadiran</button></div>
            </form>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($redirect_url)): ?>
            window.location.href = '<?php echo $redirect_url; ?>';
        <?php endif; ?>
        const presensiTableBody = document.getElementById('presensiTableBody');

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

        function updateKeterangan(radio) {
            const keteranganInput = document.getElementById(radio.dataset.keteranganId);
            if (!keteranganInput) return;
            const status = radio.value;
            if (status === 'Hadir' || status === 'Alpa') {
                keteranganInput.value = status;
                keteranganInput.readOnly = true;
                keteranganInput.required = false;
                keteranganInput.classList.add('bg-gray-100');
            } else {
                if (keteranganInput.value === 'Hadir' || keteranganInput.value === 'Alpa') {
                    keteranganInput.value = '';
                }
                keteranganInput.readOnly = false;
                keteranganInput.required = true;
                keteranganInput.classList.remove('bg-gray-100');
                keteranganInput.placeholder = 'Keterangan wajib diisi';
            }
        }
        if (presensiTableBody) {
            presensiTableBody.querySelectorAll('.status-radio:checked').forEach(updateKeterangan);
            presensiTableBody.addEventListener('change', e => {
                if (e.target.classList.contains('status-radio')) updateKeterangan(e.target);
            });
        }
    });
</script>