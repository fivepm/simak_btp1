<?php
// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    echo "<p>Anda harus login untuk mengakses halaman ini.</p>";
    return;
}
// --- END SECURITY CHECK ---


// --- INITIALIZATION ---
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'user';
$userNama = $_SESSION['user_nama'];
$userUsername = $_SESSION['username'];
$pesan_sukses = '';
$pesan_error = '';
$tableName = ($userRole == 'guru') ? 'guru' : 'users';
$target_dir = "../uploads/profiles/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}
// --- END INITIALIZATION ---


// --- POST REQUEST HANDLING ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $action = $_POST['action'] ?? '';

    // --- ACTION: Update Profile Information (Tidak Berubah) ---
    if ($action == 'update_profil') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '') ?: $userNama;
        $nomor_wa = trim($_POST['nomor_wa']);
        $username = trim($_POST['username'] ?? '') ?: $userUsername;

        if (!empty($nomor_wa) && (!preg_match('/^62\d{9,15}$/', $nomor_wa))) {
            $pesan_error = "Format Nomor WhatsApp salah. Gunakan format 62... (contoh: 628123456789).";
        } else {
            $sql = "UPDATE $tableName SET nama = ?, username = ?, nomor_wa = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $nama_lengkap, $username, $nomor_wa, $userId);

            if (mysqli_stmt_execute($stmt)) {
                $pesan_sukses = "Informasi profil berhasil diperbarui.";
            } else {
                $error_msg = mysqli_error($conn);
                if (strpos($error_msg, 'Unknown column') !== false) {
                    $pesan_error = "Struktur database belum sesuai. Pastikan migrasi Phinx sudah dijalankan.";
                } else {
                    $pesan_error = "Gagal memperbarui profil: " . $error_msg;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    /*
    // --- ACTION: Change Password (Tidak Berubah) ---
    elseif ($action == 'change_password') {
        $pass_lama = $_POST['pass_lama'];
        $pass_baru = $_POST['pass_baru'];
        $konf_pass_baru = $_POST['konf_pass_baru'];

        if (empty($pass_lama) || empty($pass_baru) || empty($konf_pass_baru)) {
            $pesan_error = "Semua field password harus diisi.";
        } elseif ($pass_baru !== $konf_pass_baru) {
            $pesan_error = "Konfirmasi password baru tidak cocok.";
        } elseif (strlen($pass_baru) < 8) {
            $pesan_error = "Password baru minimal harus 8 karakter.";
        } else {
            $sql_pass = "SELECT password FROM $tableName WHERE id = ?";
            $stmt_pass = mysqli_prepare($conn, $sql_pass);
            mysqli_stmt_bind_param($stmt_pass, "i", $userId);
            mysqli_stmt_execute($stmt_pass);
            $result_pass = mysqli_stmt_get_result($stmt_pass);
            $user_data = mysqli_fetch_assoc($result_pass);
            mysqli_stmt_close($stmt_pass);

            if ($user_data && password_verify($pass_lama, $user_data['password'])) {
                $hash_pass_baru = password_hash($pass_baru, PASSWORD_DEFAULT);

                $sql_upd = "UPDATE $tableName SET password = ? WHERE id = ?";
                $stmt_upd = mysqli_prepare($conn, $sql_upd);
                mysqli_stmt_bind_param($stmt_upd, "si", $hash_pass_baru, $userId);

                if (mysqli_stmt_execute($stmt_upd)) {
                    $pesan_sukses = "Password berhasil diubah.";
                } else {
                    $pesan_error = "Gagal mengubah password: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt_upd);
            } else {
                $pesan_error = "Password lama yang Anda masukkan salah.";
            }
        }
    }
    */
}
// --- END POST REQUEST HANDLING ---


// --- GET DATA FOR DISPLAY ---
$sql_get = "SELECT * FROM $tableName WHERE id = ?";
$stmt_get = mysqli_prepare($conn, $sql_get);
mysqli_stmt_bind_param($stmt_get, "i", $userId);
mysqli_stmt_execute($stmt_get);
$result_get = mysqli_stmt_get_result($stmt_get);
$user = mysqli_fetch_assoc($result_get);
mysqli_stmt_close($stmt_get);

if (!$user) {
    echo "<p>User tidak ditemukan di tabel $tableName.</p>";
    return;
}
// --- END GET DATA ---
?>

<!-- ======================= -->
<!--   LIBRARY CROPPER.JS    -->
<!-- ======================= -->
<!-- Tambahkan 2 baris ini di atas <div> container -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>


<div class="container mx-auto p-4 md:p-6">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6">Profil Saya</h1>

    <!-- Alert Placeholder -->
    <div id="alert-placeholder" class="mb-4">
        <?php if ($pesan_sukses): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                <p class="font-bold">Sukses</p>
                <p><?php echo htmlspecialchars($pesan_sukses); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($pesan_error); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- === COLUMN 1: Profile Picture Card === -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">

                <img id="profile-pic-preview"
                    src="<?php echo $target_dir . htmlspecialchars($user['foto_profil'] ?? 'default.png'); ?>"
                    alt="Foto Profil"
                    class="w-32 h-32 md:w-40 md:h-40 rounded-full mx-auto mb-4 object-cover border-4 border-gray-200"
                    onerror="this.onerror=null; this.src='<?php echo $target_dir; ?>default.png';">

                <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($user['nama']); ?></h2>
                <p class="text-gray-600">@<?php echo htmlspecialchars($user['username']); ?></p>
                <span class="inline-block bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full mt-2 capitalize">
                    <?php if ($user['role'] == 'superadmin'): ?>
                        <?php echo "Super Admin"; ?>
                    <?php else : ?>
                        <?php echo htmlspecialchars($user['role'] ?? $userRole); ?>
                    <?php endif; ?>
                </span>

                <div class="mt-6">
                    <label for="foto_profil_input" class="cursor-pointer inline-block bg-gray-200 hover:bg-gray-400 px-3 py-1 rounded-full text-sm font-medium text-gray-700 mb-1">
                        Ubah Foto Profil
                    </label>

                    <!-- Input file ini sekarang akan memicu modal crop. Kita sembunyikan. -->
                    <input type="file" name="foto_profil_input" id="foto_profil_input" class="hidden" accept="image/png, image/jpeg, image/webp">

                    <p class="text-xs text-gray-500">Max 5MB. Pilih file untuk memotong.</p>
                </div>

            </div>
        </div>

        <!-- === COLUMN 2: Forms (Tidak Berubah) === -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Form Card 1: Informasi Akun -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Informasi Akun</h3>

                <form action="?page=profile/index" method="POST" id="form-update-profil">
                    <input type="hidden" name="action" value="update_profil">
                    <div class="mb-4">
                        <label for="nama_lengkap" class="block text-sm font-medium text-gray-700">Nama Lengkap<?php echo ($user['tingkat'] == 'kelompok') ? '<span class="text-sm font-medium text-yellow-500">*)</span>' : ''; ?></label>
                        <input type="text" name="nama_lengkap" id="nama_lengkap" value="<?php echo htmlspecialchars($user['nama']); ?>" class="mt-1 px-2 block w-full rounded-md border-gray-300 shadow-sm bg-white sm:text-sm" <?php echo ($user['tingkat'] == 'kelompok') ? 'readonly disabled' : ''; ?>>
                    </div>
                    <div class="mb-4">
                        <label for="username" class="block text-sm font-medium text-gray-700">Username<?php echo ($user['tingkat'] == 'kelompok') ? '<span class="text-sm font-medium text-yellow-500">*)</span>' : ''; ?></label>
                        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="mt-1 px-2 block w-full rounded-md border-gray-300 shadow-sm bg-white sm:text-sm" <?php echo ($user['tingkat'] == 'kelompok') ? 'readonly disabled' : ''; ?>>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Role<span class="text-sm font-medium text-red-500">**)</span></label>
                        <input type="text" value="<?php if ($user['role'] == 'superadmin'): ?><?php echo "Super Admin"; ?><?php else : ?><?php echo htmlspecialchars($user['role']); ?><?php endif; ?>" class="mt-1 px-2 block w-full rounded-md border-gray-300 shadow-sm bg-white sm:text-sm" readonly disabled>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Tingkat<span class="text-sm font-medium text-red-500">**)</span></label>
                        <input type="text" value="<?php echo htmlspecialchars(ucwords($user['tingkat']) ?? ''); ?>" class="mt-1 px-2 block w-full rounded-md border-gray-300 shadow-sm bg-white sm:text-sm" readonly disabled>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Kelompok<span class="text-sm font-medium text-red-500">**)</span></label>
                        <input type="text" value="<?php echo htmlspecialchars(ucwords($user['kelompok']) ?? ''); ?>" class="mt-1 px-2 block w-full rounded-md border-gray-300 shadow-sm bg-white sm:text-sm" readonly disabled>
                    </div>
                    <div class="mb-4">
                        <label for="nomor_wa" class="block text-sm font-medium text-gray-700">Nomor WhatsApp</label>
                        <input type="text" name="nomor_wa" id="nomor_wa" value="<?php echo htmlspecialchars($user['nomor_wa'] ?? ''); ?>" class="mt-1 px-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="628123456789">
                        <p class="text-xs text-gray-500 mt-1">Gunakan format 62... (Contoh: 628123456789). Penting untuk notifikasi.</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-yellow-500">*) : Hubungi Admin Desa untuk mengubah.</label>
                        <label class="block text-sm font-medium text-red-500">**) : Tidak dapat diubah.</label>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition duration-200 font-medium">Simpan Perubahan</button>
                    </div>
                </form>
            </div>


            <!-- Form Card 2: Ubah Password -->
            <!-- <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Ubah Password</h3>
                <form action="index.php?page=profile" method="POST" id="form-ubah-password">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-4">
                        <label for="pass_lama" class="block text-sm font-medium text-gray-700">Password Saat Ini</label>
                        <input type="password" name="pass_lama" id="pass_lama" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                    </div>
                    <div class="mb-4">
                        <label for="pass_baru" class="block text-sm font-medium text-gray-700">Password Baru</label>
                        <input type="password" name="pass_baru" id="pass_baru" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required minlength="8">
                    </div>
                    <div class="mb-4">
                        <label for="konf_pass_baru" class="block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                        <input type="password" name="konf_pass_baru" id="konf_pass_baru" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required minlength="8">
                    </div>
                    <div class="text-right">
                        <button type="submit" class="bg-red-600 text-white py-2 px-6 rounded-lg hover:bg-red-700 transition duration-200 font-medium">Ubah Password</button>
                    </div>
                </form>
            </div> -->
        </div>

    </div>
</div>

<!-- ============================ -->
<!--   MODAL HTML UNTUK CROPPER   -->
<!-- ============================ -->
<div id="crop-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
        <h2 class="text-xl font-bold mb-4">Potong Gambar (1x1)</h2>

        <!-- Container untuk gambar yang akan di-crop -->
        <div class="w-full h-64 md:h-96 mb-4 bg-gray-200">
            <img id="image-to-crop" src="" alt="Preview" class="max-w-full max-h-full">
        </div>

        <div class="flex justify-end space-x-4">
            <button id="cancel-crop" type="button" class="bg-gray-300 text-gray-800 py-2 px-4 rounded-lg hover:bg-gray-400">Batal</button>
            <button id="crop-and-save" type="button" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                Potong & Simpan
            </button>
        </div>
    </div>
</div>


<!-- ======================= -->
<!--   INLINE JAVASCRIPT     -->
<!-- ======================= -->
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // --- Variabel untuk Cropper ---
        const modal = document.getElementById('crop-modal');
        const fileInput = document.getElementById('foto_profil_input');
        const imageToCrop = document.getElementById('image-to-crop');
        const cropAndSaveBtn = document.getElementById('crop-and-save');
        const cancelCropBtn = document.getElementById('cancel-crop');
        let cropper;

        // --- Cek Notifikasi Statis (dari PHP) ---
        // Kita targetkan 'alert-placeholder' yang kita siapkan di atas
        const alertPlaceholder = document.getElementById('alert-placeholder');

        // GANTI KONDISI DI BAWAH INI
        if (alertPlaceholder && alertPlaceholder.innerHTML.trim() !== '') {
            // MENJADI:
            // if (alertPlaceholder && alertPlaceholder.children.length > 0) {
            // Jika ada alert dari PHP (punya elemen anak), hilangkan setelah 3 detik
            setTimeout(() => {
                if (alertPlaceholder) {
                    // alertPlaceholder.innerHTML = '';
                    alertPlaceholder.style.transition = 'opacity 0.5s ease';
                    alertPlaceholder.style.opacity = '0';
                    setTimeout(() => {
                        alertPlaceholder.style.display = 'none';
                    }, 500);
                }
            }, 3000); // 3000 milidetik = 3 detik
        }

        // --- Inisialisasi Cropper saat file dipilih ---
        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Tampilkan gambar di modal
                    imageToCrop.src = event.target.result;
                    modal.classList.remove('hidden'); // Tampilkan modal

                    // Hancurkan instance cropper lama jika ada
                    if (cropper) {
                        cropper.destroy();
                    }

                    // Inisialisasi Cropper.js
                    cropper = new Cropper(imageToCrop, {
                        aspectRatio: 1 / 1, // Rasio 1x1 (persegi)
                        viewMode: 1, // Mode tampilan
                        dragMode: 'move',
                        background: false,
                        responsive: true,
                        autoCropArea: 0.8,
                    });
                };
                reader.readAsDataURL(files[0]);
            }
        });

        // --- Tombol Batal di Modal ---
        cancelCropBtn.addEventListener('click', function() {
            modal.classList.add('hidden'); // Sembunyikan modal
            fileInput.value = ''; // Reset input file
            if (cropper) {
                cropper.destroy();
            }
        });

        // --- Tombol Potong & Simpan di Modal ---
        cropAndSaveBtn.addEventListener('click', function() {
            if (!cropper) return;

            // Tampilkan loading di tombol
            cropAndSaveBtn.disabled = true;
            cropAndSaveBtn.innerHTML = 'Menyimpan...';

            // Dapatkan canvas yang di-crop
            const canvas = cropper.getCroppedCanvas({
                width: 500, // Tentukan ukuran output
                height: 500,
                imageSmoothingQuality: 'high',
            });

            // Konversi canvas ke Blob (file)
            canvas.toBlob(function(blob) {
                if (!blob) {
                    showAlert('Gagal memproses gambar. Coba lagi.', 'error');
                    resetCropButton();
                    return;
                }

                // Buat FormData untuk dikirim via AJAX
                const formData = new FormData();
                formData.append('foto_profil', blob, 'profile_cropped.png'); // Nama file penting
                formData.append('action', 'update_foto'); // Aksi PHP
                formData.append('is_ajax', '1'); // Penanda AJAX

                // Kirim data menggunakan fetch
                fetch('pages/profile/ajax_update_foto.php', {
                        method: 'POST',
                        body: formData,
                        // Jangan set Content-Type, biarkan browser
                    })
                    .then(response => {
                        // Cek jika response bukan JSON
                        const contentType = response.headers.get("content-type");
                        if (contentType && contentType.indexOf("application/json") !== -1) {
                            return response.json();
                        } else {
                            // Jika PHP error, response mungkin HTML
                            return response.text().then(text => {
                                throw new Error("Respon server tidak valid. Coba lihat log. \n" + text.substring(0, 200))
                            });
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            showAlert('Foto profil berhasil diperbarui!', 'success');

                            // Update gambar di halaman (profile card)
                            const previewImg = document.getElementById('profile-pic-preview');
                            if (previewImg) {
                                previewImg.src = data.newImageUrl;
                            }

                            // Update gambar di header (jika ada)
                            // Anda mungkin perlu menyesuaikan ID 'header-profile-pic'
                            const headerImg = document.getElementById('header-profile-pic');
                            if (headerImg) {
                                headerImg.src = data.newImageUrl;
                            }

                            // Sembunyikan modal dan reset
                            modal.classList.add('hidden');
                            fileInput.value = '';
                            if (cropper) {
                                cropper.destroy();
                            }

                        } else {
                            showAlert(data.message || 'Gagal mengupload gambar.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch Error:', error);
                        showAlert('Terjadi kesalahan saat mengupload: ' + error.message, 'error');
                    })
                    .finally(() => {
                        // Kembalikan tombol ke state normal
                        resetCropButton();
                    });

            }, 'image/png'); // Tipe file output
        });

        function resetCropButton() {
            cropAndSaveBtn.disabled = false;
            cropAndSaveBtn.innerHTML = 'Potong & Simpan';
        }


        // --- (Kode JS Lainnya di Bawah Sini) ---

        // --- Password Confirmation Check ---
        const passForm = document.getElementById('form-ubah-password');
        if (passForm) {
            passForm.addEventListener('submit', function(e) {
                const passBaru = document.getElementById('pass_baru').value;
                const konfPassBaru = document.getElementById('konf_pass_baru').value;

                if (passBaru !== konfPassBaru) {
                    e.preventDefault();
                    showAlert('Konfirmasi password baru tidak cocok. Silakan periksa kembali.', 'error');
                }
            });
        }

        // --- Dynamic Alert Function ---
        function showAlert(message, type = 'error') {
            const alertPlaceholder = document.getElementById('alert-placeholder');
            if (!alertPlaceholder) return;

            let bgColor, borderColor, textColor, title;
            if (type === 'success') {
                bgColor = 'bg-green-100';
                borderColor = 'border-green-500';
                textColor = 'text-green-700';
                title = 'Sukses';
            } else {
                bgColor = 'bg-red-100';
                borderColor = 'border-red-500';
                textColor = 'text-red-700';
                title = 'Error';
            }

            const alertHTML = `
            <div class="${bgColor} border-l-4 ${borderColor} ${textColor} p-4" role="alert">
                <p class="font-bold">${title}</p>
                <p>${message}</p>
            </div>
        `;

            alertPlaceholder.innerHTML = alertHTML;
            window.scrollTo(0, 0);

            setTimeout(() => {
                if (alertPlaceholder) {
                    // alertPlaceholder.innerHTML = '';
                    alertPlaceholder.style.transition = 'opacity 0.5s ease';
                    alertPlaceholder.style.opacity = '0';
                    setTimeout(() => {
                        alertPlaceholder.style.display = 'none';
                    }, 500);
                }
            }, 3000);
        }
    });
</script>