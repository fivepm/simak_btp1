<?php
// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    // Redirect ke login jika akses langsung
    header("Location: ../../index");
    exit;
}

// --- INITIALIZATION ---
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'guru';
$userTingkat = $_SESSION['user_tingkat'] ?? '';

$tableName = ($userRole === 'guru') ? 'guru' : 'users';
$target_dir = "../../uploads/profiles/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

// --- HANDLE POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    // Update Info Pribadi
    if ($action == 'update_info_pribadi') {
        $nama_panggilan = trim($_POST['nama_panggilan']);
        $nomor_wa = trim($_POST['nomor_wa']);

        if (!empty($nomor_wa) && !preg_match('/^62\d{9,15}$/', $nomor_wa)) {
            $err_msg = "Format Nomor WA salah. Gunakan awalan 62.";
            $swal_notification = "
                Swal.fire({
                    title: 'Gagal!',
                    text: 'Terjadi kesalahan: $err_msg',
                    icon: 'error'
                });
                ";
        } else {
            $stmt = $conn->prepare("UPDATE $tableName SET nama_panggilan = ?, nomor_wa = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nama_panggilan, $nomor_wa, $userId);

            if ($stmt->execute()) {
                $_SESSION['user_nama_panggilan'] = $nama_panggilan;

                // Inject JavaScript untuk update tampilan Header
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var headerName = document.getElementById('header-user-name');
                        if(headerName) {
                            headerName.innerText = '" . addslashes($nama_panggilan) . "';
                        }
                    });
                </script>";

                writeLog('UPDATE', "Pengguna memperbarui data profil.");
                $swal_notification = "
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Data Profile berhasil diperbarui.',
                    icon: 'success',
                    timer: 2000,
                    confirmButtonColor: '#4F46E5',
                    showConfirmButton: false
                }).then(() => {
                    window.location = '';
                });
                ";
            } else {
                $err_msg = "Gagal memperbarui data: " . $conn->error;
                $swal_notification = "
                Swal.fire({
                    title: 'Gagal!',
                    text: 'Terjadi kesalahan: $err_msg',
                    icon: 'error'
                });
                ";
            }
            $stmt->close();
        }
    }
}

// --- GET DATA ---
$stmt = $conn->prepare("SELECT * FROM $tableName WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo '<div class="p-4 bg-red-100 text-red-700 rounded">Data user tidak ditemukan.</div>';
    return; // Hentikan eksekusi file ini saja, jangan die() agar index tetap jalan
}
?>

<!-- STYLE KHUSUS HALAMAN INI -->
<!-- Load CSS Cropper di sini karena head ada di index.php -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" rel="stylesheet">

<!-- LOADING OVERLAY KHUSUS PROFIL -->
<div id="profilLoadingOverlay" class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-800 bg-opacity-75 hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl text-center">
        <svg class="animate-spin h-10 w-10 text-indigo-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-gray-800" id="profilLoadingText">Memproses...</h3>
    </div>
</div>

<div class="container mx-auto p-4 md:p-6 max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Profil Saya</h1>
        <!-- Tombol kembali bisa disesuaikan linknya -->
        <a href="/users/ketuapjp/?page=dashboard" class="text-gray-500 hover:text-gray-700 flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- FOTO PROFIL -->
        <div class="md:col-span-1">
            <div class="bg-white p-6 rounded-xl shadow-md text-center border-t-4 border-indigo-500">
                <div class="relative inline-block group">
                    <img id="profile-pic-preview"
                        src="<?php echo $target_dir . htmlspecialchars($user['foto_profil'] ?? 'default.png'); ?>"
                        alt="Foto Profil"
                        class="w-40 h-40 rounded-full object-cover border-4 border-gray-100 shadow-sm mx-auto"
                        onerror="this.onerror=null; this.src='../../../assets/images/default.png';">
                    <label for="foto_profil_input" class="absolute bottom-2 right-2 bg-indigo-600 text-white p-2 rounded-full cursor-pointer shadow-lg hover:bg-indigo-700 transition transform hover:scale-110">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                    <input type="file" name="foto_profil_input" id="foto_profil_input" class="hidden" accept="image/png, image/jpeg, image/webp">
                </div>
                <h2 class="mt-4 text-xl font-bold text-gray-800"><?php echo htmlspecialchars($user['nama']); ?></h2>
                <p class="mb-4 text-indigo-600 font-medium text-sm">@<?php echo htmlspecialchars($user['nama_panggilan'] ?? ''); ?></p>
                <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold uppercase tracking-wide">
                    <?php echo htmlspecialchars($user['kelompok']); ?>
                </span>

                <div class="mt-2 flex flex-wrap justify-center gap-2">
                    <?php
                    if ($userRole === 'guru') {
                        // Pastikan $conn tersedia (dari include index.php)
                        if (isset($conn)) {
                            $stmt_kelas = $conn->prepare("SELECT nama_kelas FROM pengampu WHERE id_guru = ? ORDER BY nama_kelas ASC");
                            $stmt_kelas->bind_param("i", $userId);
                            $stmt_kelas->execute();
                            $result_kelas = $stmt_kelas->get_result();
                            while ($kls = $result_kelas->fetch_assoc()) {
                                echo '<span class="px-3 py-1 bg-purple-50 text-purple-700 rounded-full text-xs font-semibold uppercase tracking-wide">' . htmlspecialchars($kls['nama_kelas']) . '</span>';
                            }
                            $stmt_kelas->close();
                        }
                    }
                    if ($userRole !== 'guru'): ?>
                        <span class="px-3 py-1 bg-orange-50 text-orange-700 rounded-full text-xs font-semibold uppercase tracking-wide">
                            <?php if ($userRole === 'superadmin'): ?>
                                <?php echo htmlspecialchars('DEVELOPER'); ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars(ucwords($userRole) . " " . ucwords($userTingkat)); ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- INFO PROFIL -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="font-bold text-gray-800"><i class="fa-regular fa-id-card mr-2 text-indigo-500"></i>Informasi Pribadi</h3>
                    <button id="btnEditProfil" class="text-indigo-600 hover:text-indigo-800 text-sm font-semibold flex items-center gap-1 transition">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Data
                    </button>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-4">
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Nama Lengkap</p>
                        <p class="text-gray-800 font-medium text-lg border-b border-gray-100 pb-1"><?php echo htmlspecialchars($user['nama']); ?></p>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Nama Panggilan</p>
                        <p class="text-gray-800 font-medium text-lg border-b border-gray-100 pb-1"><?php echo htmlspecialchars($user['nama_panggilan'] ?? ''); ?></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Nomor WhatsApp</p>
                        <div class="flex items-center justify-between border-b border-gray-100 pb-1">
                            <p class="text-gray-800 font-medium text-lg font-mono"><?php echo htmlspecialchars($user['nomor_wa'] ?? '-'); ?></p>
                            <?php if ($user['nomor_wa']): ?>
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Terhubung</span>
                            <?php else: ?>
                                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded">Belum diatur</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="font-bold text-gray-800"><i class="fa-solid fa-shield-halved mr-2 text-indigo-500"></i>Keamanan Akun</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-800 font-semibold">PIN Keamanan</p>
                            <p class="text-sm text-gray-500">Digunakan untuk login ke sistem (6 Digit).</p>
                        </div>
                        <button id="btnGantiPin" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition transform hover:-translate-y-0.5 flex items-center gap-2">
                            <i class="fa-solid fa-key"></i> Ubah PIN
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT DATA DIRI -->
<div id="modalEditProfil" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-indigo-600 p-4 flex justify-between items-center">
            <h3 class="text-white font-bold text-lg">Edit Data Diri</h3>
            <button type="button" class="text-indigo-200 hover:text-white modal-close-btn text-xl">&times;</button>
        </div>
        <form method="POST" action="" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update_info_pribadi">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nama Lengkap (Tetap)</label>
                <input type="text" value="<?php echo htmlspecialchars($user['nama']); ?>" class="w-full bg-gray-100 border border-gray-300 rounded p-2 text-gray-600 cursor-not-allowed" disabled>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Panggilan</label>
                <input type="text" name="nama_panggilan" value="<?php echo htmlspecialchars($user['nama_panggilan'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nomor WhatsApp</label>
                <input type="text" name="nomor_wa" value="<?php echo htmlspecialchars($user['nomor_wa'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-500 outline-none" inputmode="numeric">
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-semibold modal-close-btn">Batal</button>
                <button type="submit" class="flex-1 bg-indigo-600 text-white py-2.5 rounded-lg font-semibold hover:bg-indigo-700 shadow-md">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL GANTI PIN -->
<div id="modalGantiPin" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="bg-yellow-500 p-4 flex justify-between items-center">
            <h3 class="text-white font-bold text-lg">Ubah PIN Keamanan</h3>
            <button type="button" class="text-yellow-100 hover:text-white modal-close-btn text-xl">&times;</button>
        </div>
        <form id="formGantiPin" class="p-6 space-y-5">
            <div id="pinAlert" class="hidden p-3 rounded text-sm font-medium"></div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PIN Lama</label>
                <input type="password" name="pin_lama" maxlength="6" inputmode="numeric" class="w-full bg-gray-50 border-2 border-gray-200 rounded-lg p-2 text-center font-bold tracking-[0.5em] focus:border-yellow-500 outline-none" placeholder="••••••" required>
            </div>
            <div class="border-t border-gray-100 pt-2"></div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PIN Baru (6 Digit)</label>
                <input type="password" name="pin_baru" maxlength="6" inputmode="numeric" class="w-full bg-gray-50 border-2 border-gray-200 rounded-lg p-2 text-center font-bold tracking-[0.5em] focus:border-yellow-500 outline-none" placeholder="••••••" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi PIN Baru</label>
                <input type="password" name="pin_konfirmasi" maxlength="6" inputmode="numeric" class="w-full bg-gray-50 border-2 border-gray-200 rounded-lg p-2 text-center font-bold tracking-[0.5em] focus:border-yellow-500 outline-none" placeholder="••••••" required>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-semibold modal-close-btn">Batal</button>
                <button type="submit" id="btnSimpanPin" class="flex-1 bg-yellow-500 text-white py-2.5 rounded-lg font-semibold hover:bg-yellow-600 shadow-md">Simpan PIN</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CROPPER -->
<div id="crop-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-80 hidden backdrop-blur-sm">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-4">
        <h2 class="text-lg font-bold mb-3 text-gray-800">Sesuaikan Foto</h2>
        <div class="w-full h-80 bg-gray-100 rounded overflow-hidden relative flex items-center justify-center border border-gray-300">
            <img id="image-to-crop" src="" alt="Preview" class="max-w-full max-h-full block">
        </div>
        <div class="flex justify-end space-x-3 mt-4">
            <button id="cancel-crop" type="button" class="bg-gray-200 text-gray-800 py-2 px-4 rounded hover:bg-gray-300 font-medium">Batal</button>
            <button id="crop-and-save" type="button" class="bg-indigo-600 text-white py-2 px-6 rounded hover:bg-indigo-700 font-medium shadow transition">Simpan</button>
        </div>
    </div>
</div>

<!-- SCRIPT LIBRARY CROPPER DILETAKKAN DI SINI -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // === GUNAKAN ID UNIK UNTUK LOADING OVERLAY ===
        const loadingOverlay = document.getElementById('profilLoadingOverlay');
        const loadingText = document.getElementById('profilLoadingText');

        const showLoading = (text = 'Memproses...') => {
            loadingText.innerText = text;
            loadingOverlay.classList.remove('hidden');
        };
        const hideLoading = () => {
            loadingOverlay.classList.add('hidden');
        };

        // === MODALS ===
        const modals = {
            edit: document.getElementById('modalEditProfil'),
            pin: document.getElementById('modalGantiPin'),
            crop: document.getElementById('crop-modal')
        };
        const openModal = (m) => m.classList.remove('hidden');
        const closeModal = (m) => m.classList.add('hidden');

        document.getElementById('btnEditProfil').onclick = () => openModal(modals.edit);
        document.getElementById('btnGantiPin').onclick = () => {
            openModal(modals.pin);
            document.getElementById('formGantiPin').reset();
            document.getElementById('pinAlert').classList.add('hidden');
        };
        document.querySelectorAll('.modal-close-btn').forEach(btn => btn.onclick = function() {
            closeModal(this.closest('.fixed'));
        });

        setTimeout(() => {
            document.querySelectorAll('#php-alert-sukses, #php-alert-error').forEach(el => el.style.display = 'none');
        }, 3000);

        // === GANTI PIN ===
        const formGantiPin = document.getElementById('formGantiPin');
        const pinAlert = document.getElementById('pinAlert');
        const btnSimpanPin = document.getElementById('btnSimpanPin');

        formGantiPin.addEventListener('submit', async (e) => {
            e.preventDefault();
            // --- KUNCI: STOP INDEX.PHP LOADER ---
            e.stopImmediatePropagation();
            // ------------------------------------

            btnSimpanPin.disabled = true;
            btnSimpanPin.innerText = 'Menyimpan...';
            showLoading('Menyimpan PIN...');

            try {
                const response = await fetch('pages/profile/update_pin.php', {
                    method: 'POST',
                    body: new FormData(formGantiPin)
                });

                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error("Respon server error (bukan JSON).");
                }

                const result = await response.json();
                pinAlert.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700');
                if (result.success) {
                    pinAlert.classList.add('bg-green-100', 'text-green-700');
                    pinAlert.innerHTML = '<i class="fa-solid fa-check-circle mr-1"></i> ' + result.message;
                    setTimeout(() => closeModal(modals.pin), 2000);
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'PIN berhasil diperbarui.',
                        icon: 'success',
                        confirmButtonColor: '#4F46E5', // Warna tombol sesuai tema (indigo-600)
                        timer: 2000, // Otomatis tutup dalam 2 detik (opsional)
                        showConfirmButton: false // Hilangkan tombol OK jika pakai timer
                    });
                } else {
                    pinAlert.classList.add('bg-red-100', 'text-red-700');
                    pinAlert.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> ' + result.message;
                    Swal.fire({
                        title: 'Gagal!',
                        text: result.message,
                        icon: 'error',
                        timer: 2000, // Otomatis tutup dalam 2 detik (opsional)
                        showConfirmButton: false // Hilangkan tombol OK jika pakai timer
                    });
                }
            } catch (error) {
                pinAlert.classList.remove('hidden', 'bg-red-100', 'text-red-700');
                pinAlert.innerText = 'Gagal terhubung: ' + error.message;
                Swal.fire({
                    title: 'Gagal!',
                    text: error.message,
                    icon: 'error',
                    timer: 2000, // Otomatis tutup dalam 2 detik (opsional)
                    showConfirmButton: false // Hilangkan tombol OK jika pakai timer
                });
            } finally {
                hideLoading();
                btnSimpanPin.disabled = false;
                btnSimpanPin.innerText = 'Simpan PIN';
            }
        });

        // === CROP FOTO ===
        const fileInput = document.getElementById('foto_profil_input');
        const imageToCrop = document.getElementById('image-to-crop');
        const cropAndSaveBtn = document.getElementById('crop-and-save');
        const cancelCropBtn = document.getElementById('cancel-crop');
        let cropper;

        fileInput.addEventListener('change', function(e) {
            if (e.target.files && e.target.files.length > 0) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    imageToCrop.src = event.target.result;
                    openModal(modals.crop);
                    if (cropper) cropper.destroy();
                    setTimeout(() => {
                        if (typeof Cropper !== 'undefined') {
                            cropper = new Cropper(imageToCrop, {
                                aspectRatio: 1,
                                viewMode: 1,
                                dragMode: 'move',
                                autoCropArea: 0.8
                            });
                        }
                    }, 200);
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        cancelCropBtn.onclick = () => {
            closeModal(modals.crop);
            fileInput.value = '';
            if (cropper) cropper.destroy();
        };

        cropAndSaveBtn.onclick = () => {
            if (!cropper) return;
            showLoading('Mengupload...');
            cropper.getCroppedCanvas({
                width: 500,
                height: 500
            }).toBlob((blob) => {
                const formData = new FormData();
                formData.append('foto_profil', blob, 'profile.png');
                fetch('pages/profile/ajax_update_foto.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('profile-pic-preview').src = data.newImageUrl;
                            const headerImg = document.getElementById('header-profile-pic');
                            if (headerImg) {
                                headerImg.src = data.newImageUrl;
                            }
                            closeModal(modals.crop);

                            Swal.fire({
                                title: 'Berhasil!',
                                text: 'Foto profil berhasil diperbarui.',
                                icon: 'success',
                                confirmButtonColor: '#4F46E5', // Warna tombol sesuai tema (indigo-600)
                                timer: 2000, // Otomatis tutup dalam 2 detik (opsional)
                                showConfirmButton: false // Hilangkan tombol OK jika pakai timer
                            });
                        } else {
                            Swal.fire({
                                title: 'Gagal!',
                                text: data.message || 'Terjadi kesalahan saat mengupload foto.',
                                icon: 'error',
                                confirmButtonColor: '#EF4444' // Warna merah (red-500)
                            });
                        }
                    })
                    .catch(err => alert('Error upload: ' + err.message))
                    .finally(() => {
                        hideLoading();
                        fileInput.value = '';
                    });
            });
        };
    });
</script>