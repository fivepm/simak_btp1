<?php
session_start();
require_once '../../config/config.php';

// Cek sesi
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guru') {
    header("Location: ../../index.php");
    exit;
}

$id_guru = $_SESSION['user_id'];
$nama_guru = $_SESSION['user_nama'];
$kelompok = $_SESSION['user_kelompok'];

// Ambil daftar kelas yang diampu
$stmt = $conn->prepare("SELECT nama_kelas FROM pengampu WHERE id_guru = ? ORDER BY nama_kelas ASC");
$stmt->bind_param("i", $id_guru);
$stmt->execute();
$result = $stmt->get_result();
$daftar_kelas = [];
while ($row = $result->fetch_assoc()) {
    $daftar_kelas[] = $row['nama_kelas'];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Kelas - SIMAK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen font-sans">

    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md text-center">
        <img src="../../assets/images/logo_kbm.png" alt="Logo" class="h-16 w-16 mx-auto mb-4">

        <h2 class="text-2xl font-bold text-gray-800">Halo, <?php echo htmlspecialchars($nama_guru); ?>!</h2>
        <p class="text-gray-600 mb-6">Anda terdaftar di beberapa kelas. Silakan pilih kelas untuk masuk.</p>

        <div class="space-y-3">
            <?php foreach ($daftar_kelas as $kelas): ?>
                <form action="set_kelas_aktif.php" method="POST">
                    <input type="hidden" name="kelas_tujuan" value="<?php echo htmlspecialchars($kelas); ?>">
                    <button type="submit" class="w-full bg-white hover:bg-indigo-50 border-2 border-indigo-100 hover:border-indigo-500 text-indigo-700 font-semibold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-between group">
                        <span class="capitalize">Kelas <?php echo htmlspecialchars($kelas); ?></span>
                        <i class="fa-solid fa-arrow-right text-indigo-300 group-hover:text-indigo-600"></i>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 pt-4 border-t border-gray-100">
            <a href="#" onclick="event.preventDefault(); handleLogout();" class="text-sm text-red-500 hover:text-red-700 font-medium">
                <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout / Ganti Akun
            </a>
        </div>
    </div>

    <div id="logout-overlay" class="fixed inset-0 z-[999] flex flex-col items-center justify-center bg-gray-900 bg-opacity-75 transition-opacity duration-300 ease-in-out opacity-0 hidden">
        <div class="w-16 h-16 border-4 border-t-4 border-t-cyan-500 border-gray-600 rounded-full animate-spin"></div>
        <p class="mt-4 text-white text-lg font-semibold">Logging out...</p>
    </div>

    <script>
        function handleLogout() {
            const overlay = document.getElementById('logout-overlay');
            if (!overlay) {
                console.error("Elemen logout-overlay tidak ditemukan!");
                // Fallback jika overlay tidak ada
                window.location.href = '../../auth/logout'; // Langsung logout paksa
                return;
            }

            // 1. Tampilkan Overlay dengan fade-in
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
            }, 10); // delay kecil untuk trigger transisi CSS

            // 2. Panggil file logout.php di server setelah animasi terlihat
            setTimeout(() => {
                fetch('../../auth/logout.php', { // Pastikan path ke logout.php benar
                        method: 'POST', // Gunakan POST agar tidak di-cache
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest' // Tanda ini request AJAX
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // 3. Sukses, tunggu sebentar lalu redirect ke login
                            setTimeout(() => {
                                // Ganti 'login.php' dengan halaman login Anda
                                window.location.href = '../../';
                            }, 500); // Beri waktu 0.5 detik agar user melihat animasi
                        } else {
                            // Gagal logout (jarang terjadi)
                            alert('Logout gagal. Mencoba redirect paksa...');
                            window.location.href = '../../';
                        }
                    })
                    .catch(error => {
                        console.error('Error saat logout:', error);
                        // Jika fetch gagal (misal server down), redirect paksa
                        alert('Error koneksi saat logout. Redirecting...');
                        window.location.href = '../../';
                    });
            }, 500); // Mulai proses logout setelah 0.5 detik animasi
        }
    </script>

</body>

</html>