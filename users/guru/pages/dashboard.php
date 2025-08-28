<?php
// Variabel $conn dan data session sudah tersedia dari index.php
$nama_guru = htmlspecialchars($_SESSION['user_nama']);
$guru_id = $_SESSION['user_id'];
$guru_kelompok = $_SESSION['user_kelompok'] ?? '';
$guru_kelas = $_SESSION['user_kelas'] ?? '';

// === AMBIL DATA STATISTIK UNTUK DASHBOARD ===

// 1. Ambil jadwal mengajar yang sedang aktif saat ini
$jadwal_aktif = null;
if (!empty($guru_kelompok) && !empty($guru_kelas)) {
    $sql_jadwal = "SELECT id, kelas, kelompok, jam_mulai, jam_selesai 
                   FROM jadwal_presensi 
                   WHERE kelompok = ? AND kelas = ? AND tanggal = CURDATE() AND CURTIME() BETWEEN jam_mulai AND jam_selesai
                   LIMIT 1";
    $stmt_jadwal = $conn->prepare($sql_jadwal);
    $stmt_jadwal->bind_param("ss", $guru_kelompok, $guru_kelas);
    $stmt_jadwal->execute();
    $result_jadwal = $stmt_jadwal->get_result();
    if ($result_jadwal && $result_jadwal->num_rows > 0) {
        $jadwal_aktif = $result_jadwal->fetch_assoc();
    }
    $stmt_jadwal->close();
}

// 2. Ambil ringkasan data kelas
$ringkasan_kelas = ['total' => 0, 'laki_laki' => 0, 'perempuan' => 0];
if (!empty($guru_kelompok) && !empty($guru_kelas)) {
    $sql_ringkasan = "SELECT 
                        COUNT(id) as total,
                        SUM(CASE WHEN jenis_kelamin = 'Laki-laki' THEN 1 ELSE 0 END) as laki_laki,
                        SUM(CASE WHEN jenis_kelamin = 'Perempuan' THEN 1 ELSE 0 END) as perempuan
                      FROM peserta
                      WHERE kelompok = ? AND kelas = ? AND status = 'Aktif'";
    $stmt_ringkasan = $conn->prepare($sql_ringkasan);
    $stmt_ringkasan->bind_param("ss", $guru_kelompok, $guru_kelas);
    $stmt_ringkasan->execute();
    $result_ringkasan = $stmt_ringkasan->get_result();
    if ($result_ringkasan) {
        $data = $result_ringkasan->fetch_assoc();
        $ringkasan_kelas['total'] = $data['total'] ?? 0;
        $ringkasan_kelas['laki_laki'] = $data['laki_laki'] ?? 0;
        $ringkasan_kelas['perempuan'] = $data['perempuan'] ?? 0;
    }
    $stmt_ringkasan->close();
}

?>
<div class="container mx-auto space-y-8">
    <!-- Header Sambutan -->
    <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-800">
            Selamat Datang, <?php echo $nama_guru; ?>!
        </h1>
        <p class="text-md text-gray-500 mt-2">
            Anda mengajar di Kelompok <span class="font-semibold capitalize text-green-600"><?php echo htmlspecialchars($guru_kelompok); ?></span>
            untuk Kelas <span class="font-semibold capitalize text-green-600"><?php echo htmlspecialchars($guru_kelas); ?></span>.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Jadwal & Ringkasan -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Kartu Jadwal Aktif -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-4 text-center">Jadwal Mengajar Saat Ini</h2>
                <?php if ($jadwal_aktif): ?>
                    <div class="space-y-4">
                        <div class="bg-green-100 text-green-800 p-4 rounded-lg text-left">
                            <p class="font-semibold">Anda memiliki jadwal mengajar yang sedang berlangsung:</p>
                            <ul class="list-disc list-inside mt-2">
                                <li><strong>Kelas:</strong> <span class="capitalize"><?php echo htmlspecialchars($jadwal_aktif['kelas']); ?></span></li>
                                <li><strong>Waktu:</strong> <?php echo date("H:i", strtotime($jadwal_aktif['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal_aktif['jam_selesai'])); ?></li>
                            </ul>
                        </div>
                        <a href="?page=input_presensi&jadwal_id=<?php echo $jadwal_aktif['id']; ?>"
                            class="inline-block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition duration-200 text-lg text-center">
                            Mulai Presensi Sekarang
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-md font-medium text-gray-900">Tidak Ada Jadwal Aktif</h3>
                        <p class="mt-1 text-sm text-gray-500">Saat ini tidak ada jadwal mengajar yang sedang berlangsung.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kartu Ringkasan Kelas -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Ringkasan Kelas Saya</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-700 font-semibold">Total Peserta</p>
                        <p class="text-3xl font-bold text-blue-900"><?php echo $ringkasan_kelas['total']; ?></p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-green-700 font-semibold">Laki-laki</p>
                        <p class="text-3xl font-bold text-green-900"><?php echo $ringkasan_kelas['laki_laki']; ?></p>
                    </div>
                    <div class="bg-pink-50 p-4 rounded-lg">
                        <p class="text-sm text-pink-700 font-semibold">Perempuan</p>
                        <p class="text-3xl font-bold text-pink-900"><?php echo $ringkasan_kelas['perempuan']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Akses Cepat -->
        <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Akses Cepat</h2>
            <div class="space-y-3">
                <a href="?page=jadwal" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-calendar-alt fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Lihat Semua Jadwal</span>
                </a>
                <a href="?page=daftar_peserta" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-users fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Daftar Peserta</span>
                </a>
                <a href="?page=rekap_kehadiran" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-clipboard-list fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Rekap Kehadiran</span>
                </a>
                <a href="?page=rekap_jurnal" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-book-reader fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Rekap Jurnal</span>
                </a>
                <!-- TOMBOL BARU DITAMBAHKAN DI SINI -->
                <a href="?page=catatan_bk" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-user-shield fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Catatan BK</span>
                </a>
                <a href="?page=pustaka_materi/index" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-book-open fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Pustaka Materi</span>
                </a>
            </div>
        </div>
    </div>
</div>