<?php
// Cek Login & Role
if ($_SESSION['user_role'] !== 'superadmin') {
    echo "<script>alert('Akses Ditolak'); window.history.back();</script>";
    exit;
}

$action = $_GET['action'] ?? 'list';
$edit_data = null;
$selected_contributors = [];
$swal_notification = ""; // Variabel untuk menampung script SweetAlert

// --- LOGIKA SIMPAN (INSERT & UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_laporan'])) {
    $id_laporan = $_POST['id_laporan'] ?? '';
    $tgl = $_POST['tanggal_laporan'];
    $p_awal = $_POST['periode_awal'];
    $p_akhir = $_POST['periode_akhir'];
    $summary = $_POST['summary'];

    $fitur = $_POST['fitur_selesai'];
    $progress = $_POST['pekerjaan_berjalan'];
    $kendala = $_POST['kendala_teknis'];
    $teknis = $_POST['catatan_teknis'];
    $usulan = $_POST['usulan_fitur'];
    $uid = $_SESSION['user_id'];

    if (!empty($id_laporan)) {
        // === UPDATE ===
        $stmt = $conn->prepare("UPDATE laporan_developer SET tanggal_laporan=?, periode_awal=?, periode_akhir=?, summary=?, fitur_selesai=?, pekerjaan_berjalan=?, kendala_teknis=?, catatan_teknis=?, usulan_fitur=? WHERE id=?");
        $stmt->bind_param("sssssssssi", $tgl, $p_awal, $p_akhir, $summary, $fitur, $progress, $kendala, $teknis, $usulan, $id_laporan);

        if ($stmt->execute()) {
            // === CCTV ===
            if (function_exists('writeLog')) {
                $tgl_log = formatTanggalIndonesia($tgl);
                $deskripsi_log = "Mengubah Laporan Developer ID $id_laporan (Tgl: $tgl_log)";
                writeLog('UPDATE', $deskripsi_log);
            }
            // Update Kontributor
            $conn->query("DELETE FROM laporan_contributors WHERE laporan_id = " . (int)$id_laporan);
            if (isset($_POST['tim_developer']) && is_array($_POST['tim_developer'])) {
                $stmt_contrib = $conn->prepare("INSERT INTO laporan_contributors (laporan_id, user_id) VALUES (?, ?)");
                foreach ($_POST['tim_developer'] as $dev_id) {
                    $dev_id_int = (int)$dev_id;
                    $stmt_contrib->bind_param("ii", $id_laporan, $dev_id_int);
                    $stmt_contrib->execute();
                }
                $stmt_contrib->close();
            }

            // Set Notifikasi Sukses Edit
            $swal_notification = "
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Laporan berhasil diperbarui.',
                    icon: 'success',
                    confirmButtonColor: '#4F46E5'
                }).then(() => {
                    window.location = '?page=development/laporan_dev';
                });
            ";
        } else {
            // Set Notifikasi Gagal
            $err_msg = addslashes($stmt->error);
            $swal_notification = "
                Swal.fire({
                    title: 'Gagal!',
                    text: 'Terjadi kesalahan: $err_msg',
                    icon: 'error'
                });
            ";
        }
    } else {
        // === LOGIKA INSERT BARU ===
        if (empty($uid)) {
            $swal_notification = "Swal.fire('Error Session', 'User ID tidak ditemukan. Silakan login ulang.', 'error');";
        } else {
            $stmt = $conn->prepare("INSERT INTO laporan_developer (tanggal_laporan, periode_awal, periode_akhir, summary, fitur_selesai, pekerjaan_berjalan, kendala_teknis, catatan_teknis, usulan_fitur, dibuat_oleh) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssi", $tgl, $p_awal, $p_akhir, $summary, $fitur, $progress, $kendala, $teknis, $usulan, $uid);

            if ($stmt->execute()) {
                // 1. Coba ambil ID dengan cara standar
                $laporan_id = $conn->insert_id;

                // 2. WORKAROUND: Jika insert_id bernilai 0, ambil manual ID terbaru
                if ($laporan_id == 0) {
                    $q_latest = $conn->query("SELECT id FROM laporan_developer ORDER BY id DESC LIMIT 1");
                    if ($row_latest = $q_latest->fetch_assoc()) {
                        $laporan_id = $row_latest['id'];
                    }
                }

                // Pastikan ID valid (>0) sebelum insert anak
                if ($laporan_id > 0) {
                    if (isset($_POST['tim_developer']) && is_array($_POST['tim_developer'])) {
                        $stmt_contrib = $conn->prepare("INSERT INTO laporan_contributors (laporan_id, user_id) VALUES (?, ?)");
                        foreach ($_POST['tim_developer'] as $dev_id) {
                            $dev_id_int = (int)$dev_id;
                            $stmt_contrib->bind_param("ii", $laporan_id, $dev_id_int);
                            try {
                                $stmt_contrib->execute();
                            } catch (Exception $e) {
                                continue;
                            } // Abaikan error duplikat
                        }
                        $stmt_contrib->close();
                    }

                    // Log Aktivitas
                    if (function_exists('writeLog')) {
                        $tgl_log = formatTanggalIndonesia($tgl);
                        writeLog('INSERT', "Membuat Laporan Developer Baru (Tgl: $tgl_log)");
                    }

                    $swal_notification = "
                        Swal.fire({
                            title: 'Selesai!',
                            text: 'Laporan baru berhasil disimpan.',
                            icon: 'success',
                            confirmButtonColor: '#4F46E5'
                        }).then(() => {
                            window.location = '?page=development/laporan_dev';
                        });
                    ";
                } else {
                    $swal_notification = "Swal.fire('Gagal Sistem!', 'Data tersimpan tapi ID tidak valid (0) dan gagal mengambil ID terbaru.', 'error');";
                }
            } else {
                $err_msg = addslashes($stmt->error);
                $swal_notification = "Swal.fire('Gagal Simpan!', '$err_msg', 'error');";
            }
        }
    }
}

// === LOGIKA HAPUS DENGAN CONFIRMATION HANDLED BY JS, TAPI SUCCESS BY PHP ===
if (isset($_GET['hapus_id'])) {
    $id_hapus = (int)$_GET['hapus_id'];
    // Ambil info tanggal dulu untuk keperluan log
    $q_cek = $conn->query("SELECT tanggal_laporan FROM laporan_developer WHERE id = $id_hapus");
    $tgl_log = ($row = $q_cek->fetch_assoc()) ? formatTanggalIndonesia($row['tanggal_laporan']) : 'Tidak Diketahui';

    if ($conn->query("DELETE FROM laporan_developer WHERE id = $id_hapus")) {
        // --- LOG AKTIVITAS (DELETE) ---
        if (function_exists('writeLog')) {
            writeLog('DELETE', "Menghapus Laporan Developer (Tgl Laporan: $tgl_log)");
        }
        $swal_notification = "
            Swal.fire({
                title: 'Terhapus!',
                text: 'Data laporan berhasil dihapus.',
                icon: 'success',
                confirmButtonColor: '#4F46E5'
            }).then(() => {
                window.location = '?page=development/laporan_dev';
            });
        ";
    }
}

// PERSIAPAN EDIT & LIST USER
if ($action === 'edit' && isset($_GET['id'])) {
    $id_edit = (int)$_GET['id'];
    $q_edit = $conn->query("SELECT * FROM laporan_developer WHERE id = $id_edit");

    if ($q_edit && $q_edit->num_rows > 0) {
        $edit_data = $q_edit->fetch_assoc();
        $q_contrib = $conn->query("SELECT user_id FROM laporan_contributors WHERE laporan_id = $id_edit");
        while ($row = $q_contrib->fetch_assoc()) {
            $selected_contributors[] = $row['user_id'];
        }
    } else {
        // Ganti alert biasa dengan redirect langsung (atau sweetalert warning)
        echo "<script>window.location='?page=development/laporan_dev';</script>";
    }
} elseif ($action === 'tambah') {
    $selected_contributors[] = $_SESSION['user_id'];
}

$list_developer = [];
$q_dev = $conn->query("SELECT id, nama FROM users WHERE role = 'superadmin' ORDER BY nama ASC");
while ($row = $q_dev->fetch_assoc()) {
    $list_developer[] = $row;
}
?>

<!-- ================================================================= -->
<!-- DEPENDENCIES: TINYMCE & SWEETALERT2 -->
<!-- ================================================================= -->
<script src="https://cdn.tiny.cloud/1/in3kyc2hqas3mfw5thu5t8i7iuwfb8bce5n0orm0umgcqkoo/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    tinymce.init({
        selector: '.rich-editor', // Target class textareas
        height: 200,
        menubar: false,
        statusbar: false,
        plugins: 'lists link',
        toolbar: 'bold italic underline | bullist numlist | outdent indent',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
        // Opsi ini agar editor menyesuaikan lebar container
        width: '100%'
    });
</script>

<!-- === OVERLAY LOADER === -->
<div id="downloadLoader" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-75 hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl text-center">
        <svg class="animate-spin h-10 w-10 text-indigo-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-gray-800">Sedang Mengunduh Laporan...</h3>
        <p class="text-sm text-gray-500">Mohon tunggu, file PDF sedang disiapkan.</p>
    </div>
</div>

<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">📢 Laporan Progres Developer</h1>

    <?php if ($action === 'tambah' || $action === 'edit'): ?>
        <!-- FORM INPUT -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 text-gray-700 border-b pb-2">
                <?= ($action === 'edit') ? '✏️ Edit Laporan' : '📝 Buat Laporan Baru' ?>
            </h2>

            <form method="POST">
                <input type="hidden" name="id_laporan" value="<?= $edit_data['id'] ?? '' ?>">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block font-bold mb-1">Tanggal Laporan</label>
                        <input type="date" name="tanggal_laporan" value="<?= $edit_data['tanggal_laporan'] ?? date('Y-m-d') ?>" class="w-full border p-2 rounded" required>
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Periode Awal</label>
                        <input type="date" name="periode_awal" value="<?= $edit_data['periode_awal'] ?? '' ?>" class="w-full border p-2 rounded" required>
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Periode Akhir</label>
                        <input type="date" name="periode_akhir" value="<?= $edit_data['periode_akhir'] ?? '' ?>" class="w-full border p-2 rounded" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-1">Ringkasan Eksekutif (Summary)</label>
                    <input type="text" name="summary" value="<?= htmlspecialchars($edit_data['summary'] ?? '') ?>" class="w-full border p-2 rounded" placeholder="Contoh: Fokus minggu ini penyelesaian modul Musyawarah." required>
                </div>

                <div class="mb-6 p-4 bg-gray-50 border rounded-lg">
                    <label class="block font-bold mb-2 text-indigo-700">👥 Tim Developer (Kontributor)</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <?php foreach ($list_developer as $dev): ?>
                            <label class="inline-flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="tim_developer[]" value="<?= $dev['id'] ?>" class="form-checkbox h-5 w-5 text-indigo-600 rounded"
                                    <?= (in_array($dev['id'], $selected_contributors)) ? 'checked' : '' ?>>
                                <span class="text-gray-700"><?= htmlspecialchars($dev['nama']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block font-bold mb-1 text-green-600">✅ Fitur Selesai (Completed)</label>
                        <textarea name="fitur_selesai" class="rich-editor"><?= $edit_data['fitur_selesai'] ?? '' ?></textarea>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-blue-600">🚧 Sedang Berjalan (In Progress)</label>
                        <textarea name="pekerjaan_berjalan" class="rich-editor"><?= $edit_data['pekerjaan_berjalan'] ?? '' ?></textarea>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-red-600">⚠️ Kendala / Isu (Blockers)</label>
                        <textarea name="kendala_teknis" class="rich-editor"><?= $edit_data['kendala_teknis'] ?? '' ?></textarea>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-600">🔧 Catatan Teknis (Technical Notes)</label>
                        <textarea name="catatan_teknis" class="rich-editor"><?= $edit_data['catatan_teknis'] ?? '' ?></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block font-bold mb-1 text-purple-600">💡 Usulan Fitur Kedepan</label>
                        <textarea name="usulan_fitur" class="rich-editor"><?= $edit_data['usulan_fitur'] ?? '' ?></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <a href="?page=development/laporan_dev" class="bg-gray-500 text-white px-4 py-2 rounded">Batal</a>
                    <button type="submit" name="simpan_laporan" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                        <?= ($action === 'edit') ? 'Simpan Perubahan' : 'Simpan Laporan' ?>
                    </button>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- LIST LAPORAN -->
        <div class="mb-4 flex justify-between items-center">
            <p class="text-gray-600">Riwayat laporan yang telah dibuat untuk pertanggungjawaban developer.</p>
            <a href="?page=development/laporan_dev&action=tambah" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 shadow flex items-center gap-2">
                <i class="fas fa-plus"></i> Buat Laporan Baru
            </a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="p-3 text-left font-semibold text-gray-700">Tanggal</th>
                        <th class="p-3 text-left font-semibold text-gray-700">Periode</th>
                        <th class="p-3 text-left font-semibold text-gray-700">Ringkasan</th>
                        <th class="p-3 text-left font-semibold text-gray-700">Kontributor</th>
                        <th class="p-3 text-center font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT l.*, GROUP_CONCAT(u.nama SEPARATOR ', ') as tim_names
                            FROM laporan_developer l
                            LEFT JOIN laporan_contributors lc ON l.id = lc.laporan_id
                            LEFT JOIN users u ON lc.user_id = u.id
                            GROUP BY l.id ORDER BY l.tanggal_laporan DESC";
                    $q = $conn->query($sql);
                    if ($q && $q->num_rows > 0):
                        while ($row = $q->fetch_assoc()):
                    ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="p-3"><?= date('d M Y', strtotime($row['tanggal_laporan'])) ?></td>
                                <td class="p-3 text-sm text-gray-500"><?= date('d/m', strtotime($row['periode_awal'])) ?> - <?= date('d/m', strtotime($row['periode_akhir'])) ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['summary']) ?></td>
                                <td class="p-3 text-sm text-indigo-600 font-medium"><?= $row['tim_names'] ? htmlspecialchars($row['tim_names']) : '-' ?></td>
                                <td class="p-3 text-center">
                                    <a href="?page=development/laporan_dev&action=edit&id=<?= $row['id'] ?>" class="text-yellow-600 hover:text-yellow-800 mr-3" title="Edit"><i class="fas fa-pencil-alt fa-lg"></i></a>

                                    <!-- Tombol Download -->
                                    <a href="javascript:void(0)" onclick="downloadLaporan(<?= $row['id'] ?>)" class="text-blue-600 hover:text-blue-800 mr-3" title="Download PDF">
                                        <i class="fas fa-file-download fa-lg"></i>
                                    </a>

                                    <!-- Tombol Hapus dengan SweetAlert Confirmation -->
                                    <a href="javascript:void(0)" onclick="konfirmasiHapus(<?= $row['id'] ?>)" class="text-red-600 hover:text-red-800"><i class="fas fa-trash fa-lg"></i></a>
                                </td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500 italic">Belum ada laporan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- SCRIPT PENDUKUNG (Download & Delete) -->
<script>
    // Fungsi Download dengan Loader
    function downloadLaporan(id) {
        const loader = document.getElementById('downloadLoader');
        loader.classList.remove('hidden');

        fetch('pages/developer/cetak_laporan.php?id=' + id)
            .then(response => {
                if (!response.ok) throw new Error('Gagal mengambil data');
                const contentDisposition = response.headers.get('content-disposition');
                let filename = 'Laporan_Dev.pdf';
                if (contentDisposition) {
                    const match = contentDisposition.match(/filename="?([^"]+)"?/);
                    if (match && match[1]) filename = match[1];
                }
                return response.blob().then(blob => ({
                    blob,
                    filename
                }));
            })
            .then(({
                blob,
                filename
            }) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                loader.classList.add('hidden');
            })
            .catch(error => {
                console.error(error);
                Swal.fire('Error', 'Gagal mendownload laporan.', 'error');
                loader.classList.add('hidden');
            });
    }

    // Fungsi Konfirmasi Hapus dengan SweetAlert
    function konfirmasiHapus(id) {
        Swal.fire({
            title: 'Anda yakin?',
            text: "Laporan yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "?page=development/laporan_dev&hapus_id=" + id;
            }
        });
    }
</script>

<!-- EKSEKUSI NOTIFIKASI DARI PHP -->
<?php if (!empty($swal_notification)): ?>
    <script>
        <?= $swal_notification ?>
    </script>
<?php endif; ?>