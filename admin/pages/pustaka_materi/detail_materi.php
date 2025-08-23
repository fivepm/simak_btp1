<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$materi_id = isset($_GET['materi_id']) ? (int)$_GET['materi_id'] : 0;
if ($materi_id === 0) {
    echo '<div class="bg-red-100 p-4 rounded-lg">ID Materi tidak valid.</div>';
    return;
}

$success_message = '';
$error_message = '';
$redirect_url = ''; // Inisialisasi sebagai string kosong

// Fungsi untuk mengubah URL Google Drive menjadi URL embed
function get_gdrive_embed_url($url)
{
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return 'https://drive.google.com/file/d/' . $matches[1] . '/preview';
    }
    return $url; // Kembalikan URL asli jika format tidak cocok
}

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $poin_id = $_POST['poin_id'] ?? 0;

    if ($action === 'tambah_poin') {
        $nama_poin = trim($_POST['nama_poin'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if (empty($nama_poin)) {
            $error_message = 'Nama Poin wajib diisi.';
        } else {
            $sql = "INSERT INTO materi_poin (materi_induk_id, parent_id, nama_poin) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $materi_id, $parent_id, $nama_poin);
            if ($stmt->execute()) {
                $redirect_url = '?page=pustaka_materi/detail_materi&materi_id=' . $materi_id . '&status=add_poin_success';
            } else {
                $error_message = 'Gagal menambahkan poin.';
            }
        }
    }

    if ($action === 'upload_file') {
        if (isset($_FILES['materi_file']) && $_FILES['materi_file']['error'] == 0 && !empty($poin_id)) {
            $upload_dir = '../uploads/materi/';

            // PERUBAHAN: Cek jika folder ada, jika tidak, beri notifikasi error
            if (!$upload_dir || !is_dir($upload_dir)) {
                $error_message = "Proses gagal: Direktori upload tidak ditemukan di server. Hubungi administrator.";
            } else {
                $file_name = time() . '_' . preg_replace('/[^A-Za-z0-9\.\-]/', '_', basename($_FILES['materi_file']['name']));
                $target_file = $upload_dir . DIRECTORY_SEPARATOR . $file_name;

                if (move_uploaded_file($_FILES['materi_file']['tmp_name'], $target_file)) {
                    $sql = "INSERT INTO materi_file (poin_id, nama_file_asli, path_file) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iss", $poin_id, $_FILES['materi_file']['name'], $file_name);
                    if ($stmt->execute()) {
                        $redirect_url = '?page=pustaka_materi/detail_materi&materi_id=' . $materi_id . '&status=add_file_success';
                    } else {
                        $error_message = 'Gagal menyimpan data file.';
                    }
                } else {
                    $error_message = 'Gagal memindahkan file yang diunggah. Periksa izin folder.';
                }
            }
        } else {
            $error_message = 'Pilih poin dan file untuk diunggah.';
        }
    }

    if ($action === 'tambah_video') {
        $url_video = trim($_POST['url_video'] ?? '');
        $deskripsi_video = trim($_POST['deskripsi_video'] ?? '');
        if (empty($poin_id) || empty($url_video)) {
            $error_message = 'Poin dan URL Video tidak boleh kosong.';
        } else {
            $sql = "INSERT INTO materi_video (poin_id, url_video, deskripsi_video) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $poin_id, $url_video, $deskripsi_video);
            if ($stmt->execute()) {
                $redirect_url = '?page=pustaka_materi/detail_materi&materi_id=' . $materi_id . '&status=add_video_success';
            } else {
                $error_message = 'Gagal menambahkan video.';
            }
        }
    }

    // --- AKSI BARU: HAPUS POIN (dan semua isinya) ---
    if ($action === 'hapus_poin') {
        $id = $_POST['id'] ?? 0;
        if (!empty($id)) {
            // ON DELETE CASCADE akan menghapus sub-poin, file, dan video terkait secara otomatis
            $stmt = $conn->prepare("DELETE FROM materi_poin WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url .= '?page=pustaka_materi/detail_materi&materi_id=' . $materi_id . '&status=delete_poin_success';
            } else {
                $error_message = 'Gagal menghapus poin.';
            }
        }
    }

    // --- AKSI BARU: HAPUS FILE ---
    if ($action === 'hapus_file') {
        $id = $_POST['id'] ?? 0;
        if (!empty($id)) {
            // 1. Ambil path file dari database sebelum dihapus
            $stmt_path = $conn->prepare("SELECT path_file FROM materi_file WHERE id = ?");
            $stmt_path->bind_param("i", $id);
            $stmt_path->execute();
            $file_to_delete = $stmt_path->get_result()->fetch_assoc();

            // 2. Hapus data dari database
            $stmt = $conn->prepare("DELETE FROM materi_file WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                // 3. Hapus file fisik dari server jika ada
                if ($file_to_delete && file_exists('../uploads/materi/' . $file_to_delete['nama_file_asli'])) {
                    unlink('../uploads/materi/' . $file_to_delete['path_file']);
                }
                $redirect_url .= '?page=pustaka_materi/detail_materi&materi_id=' . $materi_id . '&status=delete_file_success';
            } else {
                $error_message = 'Gagal menghapus file.';
            }
        }
    }

    // --- AKSI BARU: HAPUS VIDEO ---
    if ($action === 'hapus_video') {
        $id = $_POST['id'] ?? 0;
        if (!empty($id)) {
            $stmt = $conn->prepare("DELETE FROM materi_video WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url .= '?page=pustaka_materi/detail_materi&materi_id=' . $materi_id . '&status=delete_video_success';
            } else {
                $error_message = 'Gagal menghapus video.';
            }
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_poin_success') $success_message = 'Poin baru berhasil ditambahkan!';
    if ($_GET['status'] === 'add_file_success') $success_message = 'File berhasil diunggah!';
    if ($_GET['status'] === 'add_video_success') $success_message = 'Video berhasil ditambahkan!';
    if ($_GET['status'] === 'delete_poin_success') $success_message = 'Poin berhasil dihapus!';
    if ($_GET['status'] === 'delete_file_success') $success_message = 'File berhasil dihapus!';
    if ($_GET['status'] === 'delete_video_success') $success_message = 'Video berhasil dihapus!';
}

// === AMBIL DATA DARI DATABASE (LOGIKA BARU YANG LEBIH SEDERHANA) ===
$stmt_materi = $conn->prepare("SELECT judul_materi, deskripsi FROM materi_induk WHERE id = ?");
$stmt_materi->bind_param("i", $materi_id);
$stmt_materi->execute();
$materi_induk = $stmt_materi->get_result()->fetch_assoc();
$stmt_materi->close();

if (!$materi_induk) {
    echo '<div class="bg-red-100 p-4 rounded-lg">Materi tidak ditemukan.</div>';
    return;
}

// Ambil Poin Utama
$poin_utama_list = [];
$stmt_poin_utama = $conn->prepare("SELECT id, nama_poin FROM materi_poin WHERE materi_induk_id = ? AND parent_id IS NULL ORDER BY urutan, id ASC");
$stmt_poin_utama->bind_param("i", $materi_id);
$stmt_poin_utama->execute();
$result_poin_utama = $stmt_poin_utama->get_result();
if ($result_poin_utama) {
    while ($poin = $result_poin_utama->fetch_assoc()) {
        $poin_id = $poin['id'];
        $poin['sub_poin'] = [];
        $poin['files'] = [];
        $poin['videos'] = [];

        $stmt_sub = $conn->prepare("SELECT id, nama_poin FROM materi_poin WHERE parent_id = ? ORDER BY urutan, id ASC");
        $stmt_sub->bind_param("i", $poin_id);
        $stmt_sub->execute();
        $result_sub = $stmt_sub->get_result();
        if ($result_sub) {
            while ($row = $result_sub->fetch_assoc()) {
                $sub_poin_id = $row['id'];
                $row['files'] = [];
                $row['videos'] = [];
                $stmt_files_sub = $conn->prepare("SELECT id, nama_file_asli, path_file FROM materi_file WHERE poin_id = ?");
                $stmt_files_sub->bind_param("i", $sub_poin_id);
                $stmt_files_sub->execute();
                $result_files_sub = $stmt_files_sub->get_result();
                if ($result_files_sub) {
                    while ($file_row = $result_files_sub->fetch_assoc()) {
                        $row['files'][] = $file_row;
                    }
                }

                $stmt_videos_sub = $conn->prepare("SELECT id, url_video, deskripsi_video FROM materi_video WHERE poin_id = ?");
                $stmt_videos_sub->bind_param("i", $sub_poin_id);
                $stmt_videos_sub->execute();
                $result_videos_sub = $stmt_videos_sub->get_result();
                if ($result_videos_sub) {
                    while ($video_row = $result_videos_sub->fetch_assoc()) {
                        $row['videos'][] = $video_row;
                    }
                }

                $poin['sub_poin'][] = $row;
            }
        }
        $stmt_sub->close();

        $stmt_files = $conn->prepare("SELECT id, nama_file_asli, path_file FROM materi_file WHERE poin_id = ?");
        $stmt_files->bind_param("i", $poin_id);
        $stmt_files->execute();
        $result_files = $stmt_files->get_result();
        if ($result_files) {
            while ($row = $result_files->fetch_assoc()) {
                $poin['files'][] = $row;
            }
        }
        $stmt_files->close();

        $stmt_videos = $conn->prepare("SELECT id, url_video, deskripsi_video FROM materi_video WHERE poin_id = ?");
        $stmt_videos->bind_param("i", $poin_id);
        $stmt_videos->execute();
        $result_videos = $stmt_videos->get_result();
        if ($result_videos) {
            while ($row = $result_videos->fetch_assoc()) {
                $poin['videos'][] = $row;
            }
        }
        $stmt_videos->close();

        $poin_utama_list[] = $poin;
    }
}
$stmt_poin_utama->close();
?>
<div class="container mx-auto">
    <div class="mb-6"><a href="?page=pustaka_materi/index" class="text-indigo-600 hover:underline">&larr; Kembali ke Pustaka Materi</a></div>
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($materi_induk['judul_materi']); ?></h1>
        <p class="mt-1 text-gray-600"><?php echo htmlspecialchars($materi_induk['deskripsi']); ?></p>
    </div>
    <!-- PERUBAHAN 1: Tambahkan id pada notifikasi -->
    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Tambah Poin Utama</h3>
                <form method="POST" action="?page=pustaka_materi/detail_materi&materi_id=<?php echo $materi_id; ?>"><input type="hidden" name="action" value="tambah_poin">
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nama Poin*</label><input type="text" name="nama_poin" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div class="text-right"><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Tambah</button></div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Kolom Daftar Poin & Sub-Poin -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Daftar Poin & Materi</h3>
            <div class="space-y-4">
                <?php if (empty($poin_utama_list)): ?>
                    <p class="text-center text-gray-500 py-4">Belum ada poin untuk materi ini.</p>
                    <?php else: foreach ($poin_utama_list as $poin): ?>
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <div class="flex justify-between items-center">
                                <h4 class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($poin['nama_poin']); ?></h4>
                                <!-- Tombol Hapus Poin Utama -->
                                <form method="POST" action="<?php echo $redirect_url; ?>" onsubmit="return confirm('Yakin ingin menghapus poin ini beserta semua isinya?');">
                                    <input type="hidden" name="action" value="hapus_poin">
                                    <input type="hidden" name="id" value="<?php echo $poin['id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs">[Hapus Poin]</button>
                                </form>
                            </div>

                            <div class="mt-4 pt-4 border-t space-y-4">
                                <?php $poin_data = $poin;
                                include __DIR__ . '/_materi_content.php'; ?>
                            </div>

                            <div class="pl-6 mt-4 space-y-3 border-l-2 border-gray-200">
                                <?php foreach ($poin['sub_poin'] as $sub_poin): ?>
                                    <div class="bg-white p-3 rounded shadow-sm">
                                        <div class="flex justify-between items-center">
                                            <p class="font-medium text-gray-700"><?php echo htmlspecialchars($sub_poin['nama_poin']); ?></p>
                                            <!-- Tombol Hapus Sub-Poin -->
                                            <form method="POST" action="<?php echo $redirect_url; ?>" onsubmit="return confirm('Yakin ingin menghapus sub-poin ini beserta semua isinya?');">
                                                <input type="hidden" name="action" value="hapus_poin">
                                                <input type="hidden" name="id" value="<?php echo $sub_poin['id']; ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">[Hapus]</button>
                                            </form>
                                        </div>
                                        <div class="mt-2 pt-2 border-t space-y-2">
                                            <?php $poin_data = $sub_poin;
                                            include __DIR__ . '/_materi_content.php'; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <form method="POST" action="<?php echo $redirect_url; ?>" class="flex items-center gap-2 pt-2"><input type="hidden" name="action" value="tambah_poin"><input type="hidden" name="parent_id" value="<?php echo $poin['id']; ?>"><input type="text" name="nama_poin" placeholder="+ Tambah Sub-Poin" class="flex-grow block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><button type="submit" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-3 rounded-lg text-xs">Simpan</button></form>
                            </div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Upload File -->
<div id="uploadFileModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pustaka_materi/detail_materi&materi_id=<?php echo $materi_id; ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_file">
                <input type="hidden" name="poin_id" id="upload_poin_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Upload File Materi</h3>
                    <div><label class="block text-sm font-medium">Pilih File*</label><input type="file" name="materi_file" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required></div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"><button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">Upload</button><button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Video -->
<div id="addVideoModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pustaka_materi/detail_materi&materi_id=<?php echo $materi_id; ?>">
                <input type="hidden" name="action" value="tambah_video">
                <input type="hidden" name="poin_id" id="video_poin_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tambah Link Video Google Drive</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">URL Video*</label><input type="url" name="url_video" placeholder="https://..." class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Deskripsi Video</label><input type="text" name="deskripsi_video" placeholder="Contoh: Penjelasan Ustadz A" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"><button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan Video</button><button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button></div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($redirect_url)): ?>window.location.href = '<?php echo $redirect_url; ?>';
    <?php endif; ?>

    // PERUBAHAN 2: Tambahkan kode untuk menyembunyikan notifikasi
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
        upload: document.getElementById('uploadFileModal'),
        video: document.getElementById('addVideoModal')
    };
    const openModal = (modal) => modal.classList.remove('hidden');
    const closeModal = (modal) => modal.classList.add('hidden');

    Object.values(modals).forEach(modal => {
        if (modal) modal.addEventListener('click', e => {
            if (e.target === modal || e.target.closest('.modal-close-btn')) closeModal(modal);
        });
    });

    document.querySelector('body').addEventListener('click', function(event) {
        const target = event.target;
        if (target.classList.contains('upload-file-btn')) {
            document.getElementById('upload_poin_id').value = target.dataset.poinId;
            openModal(modals.upload);
        }
        if (target.classList.contains('add-video-btn')) {
            document.getElementById('video_poin_id').value = target.dataset.poinId;
            openModal(modals.video);
        }
    });
    });
</script>