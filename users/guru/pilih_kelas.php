<?php
session_start();
require_once '../../config/config.php';

// Cek sesi
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guru') {
    header("Location: ../../index.php");
    exit;
}

$id_guru = $_SESSION['user_id'];
$nama_guru = $_SESSION['user_nama'] ?? 'Guru';
$kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil daftar kelas yang diampu
$stmt = $conn->prepare("SELECT nama_kelas FROM pengampu WHERE id_guru = ? ORDER BY nama_kelas ASC");
$stmt->bind_param("i", $id_guru);
$stmt->execute();
$result = $stmt->get_result();
$daftar_kelas = [];
while ($row = $result->fetch_assoc()) {
    $daftar_kelas[] = $row['nama_kelas'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Kelas - SIMAK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Welcome Overlay Styles (Mirip index.php) */
        #welcome-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 9999;
            display: flex; justify-content: center; align-items: center;
            opacity: 0; visibility: hidden;
            transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.4s;
        }

        #welcome-overlay.show {
            opacity: 1; visibility: visible;
        }

        .welcome-content {
            text-align: center;
            transform: translateY(20px) scale(0.95);
            transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        #welcome-overlay.show .welcome-content {
            transform: translateY(0) scale(1);
        }

        .welcome-avatar {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            margin: 0 auto 20px;
            color: white; font-size: 36px;
            box-shadow: 0 10px 25px -5px rgba(34, 197, 94, 0.5);
        }

        .welcome-spinner {
            width: 40px; height: 40px;
            border: 4px solid #dcfce7; border-top-color: #22c55e;
            border-radius: 50%; margin: 0 auto;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { 
            100% { transform: rotate(360deg); } 
        }
    </style>
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4 font-sans">
    
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 text-center border border-gray-100">
        <!-- Header Info -->
        <div class="mb-6">
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl shadow-inner">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Selamat Datang, <?= htmlspecialchars($nama_guru) ?>!</h2>
            <p class="text-gray-500 mt-2 text-sm">Anda mengajar lebih dari satu kelas. Silakan pilih kelas yang ingin Anda kelola pada sesi ini.</p>
        </div>

        <!-- List Kelas -->
        <div class="space-y-3">
            <?php if (empty($daftar_kelas)): ?>
                <div class="p-4 bg-red-50 text-red-600 rounded-lg text-sm">
                    Anda belum ditugaskan untuk mengajar di kelas mana pun.
                </div>
            <?php else: ?>
                <?php foreach ($daftar_kelas as $kelas): ?>
                    <button 
                        onclick="pilihKelas('<?= htmlspecialchars($kelas, ENT_QUOTES) ?>')"
                        class="w-full flex items-center justify-between p-4 bg-white border-2 border-gray-200 rounded-xl hover:border-green-500 hover:bg-green-50 transition-all duration-200 text-left group relative overflow-hidden">
                        <span class="font-semibold text-gray-700 group-hover:text-green-700 text-lg relative z-10">
                            <?= htmlspecialchars(ucwords($kelas)) ?>
                        </span>
                        <i class="fa-solid fa-chevron-right text-gray-400 group-hover:text-green-500 transition-colors relative z-10"></i>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Tombol Logout -->
        <div class="mt-8">
            <a href="../../auth/logout.php" class="text-sm text-gray-400 hover:text-red-500 transition-colors">
                <i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Kembali ke Login
            </a>
        </div>
    </div>

    <!-- Welcome Overlay -->
    <div id="welcome-overlay">
        <div class="welcome-content">
            <div class="welcome-avatar">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="welcome-text">
                <h2 class="text-2xl font-bold mb-1">Selamat Datang!</h2>
                <p id="welcome-user-name" class="text-xl font-medium mb-1"></p>
                <span id="welcome-user-role" class="inline-block bg-green-100 text-green-700 text-sm px-3 py-1 rounded-full font-medium shadow-sm"></span>
            </div>
            <div class="mt-6">
                <div class="welcome-spinner"></div>
            </div>
        </div>
    </div>

    <script>
        function pilihKelas(kelasTujuan) {
            // Kita nonaktifkan dulu semua tombol agar tidak diklik dua kali
            const buttons = document.querySelectorAll('button');
            buttons.forEach(btn => btn.disabled = true);

            // Fetch data ke backend
            fetch('set_kelas_aktif.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ kelas_tujuan: kelasTujuan })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Isi nama dan role pada overlay dari balasan JSON backend
                    document.getElementById('welcome-user-name').textContent = data.nama;
                    document.getElementById('welcome-user-role').textContent = data.tampilan_role;

                    // Munculkan overlay cantik dengan efek blur dan pop-up
                    const welcomeOverlay = document.getElementById('welcome-overlay');
                    welcomeOverlay.classList.add('show');

                    // Beri jeda waktu agar animasi bisa dinikmati mata (1.5 detik)
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 1500);
                } else {
                    buttons.forEach(btn => btn.disabled = false);
                    alert(data.message || 'Terjadi kesalahan saat memproses kelas.');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                buttons.forEach(btn => btn.disabled = false);
                alert('Koneksi terputus. Gagal menghubungi server.');
            });
        }
    </script>
</body>
</html>