<?php

$id_musyawarah = $_GET['id'] ?? null;

// Keamanan: Pastikan ID Musyawarah valid
if (!$id_musyawarah || !filter_var($id_musyawarah, FILTER_VALIDATE_INT)) {
    header('Location: daftar_musyawarah.php?status=error&msg=' . urlencode('ID Musyawarah tidak valid.'));
    exit();
}

// 1. Ambil Detail Musyawarah SAAT INI
$stmt_musyawarah = $conn->prepare("SELECT * FROM musyawarah WHERE id = ?");
$stmt_musyawarah->bind_param("i", $id_musyawarah);
$stmt_musyawarah->execute();
$musyawarah = $stmt_musyawarah->get_result()->fetch_assoc();
$stmt_musyawarah->close();

if (!$musyawarah) {
    header('Location: daftar_musyawarah.php?status=error&msg=' . urlencode('Musyawarah tidak ditemukan.'));
    exit();
}

// 2. Cari Musyawarah SEBELUMNYA berdasarkan tanggal
$stmt_prev = $conn->prepare("SELECT id, nama_musyawarah, tanggal FROM musyawarah WHERE tanggal < ? ORDER BY tanggal DESC LIMIT 1");
$stmt_prev->bind_param("s", $musyawarah['tanggal']);
$stmt_prev->execute();
$musyawarah_sebelumnya = $stmt_prev->get_result()->fetch_assoc();
$stmt_prev->close();

// 3. Jika musyawarah sebelumnya ditemukan, ambil poin-poinnya
$poin_sebelumnya = null;
if ($musyawarah_sebelumnya) {
    $stmt_poin_prev = $conn->prepare("SELECT * FROM notulensi_poin WHERE id_musyawarah = ? ORDER BY id ASC");
    $stmt_poin_prev->bind_param("i", $musyawarah_sebelumnya['id']);
    $stmt_poin_prev->execute();
    $poin_sebelumnya = $stmt_poin_prev->get_result();
    $stmt_poin_prev->close();
}

// 4. Ambil Daftar Hadir musyawarah SAAT INI (diurutkan sesuai urutan custom)
$stmt_hadir = $conn->prepare("SELECT nama_peserta, jabatan, status FROM kehadiran_musyawarah WHERE id_musyawarah = ? ORDER BY urutan ASC");
$stmt_hadir->bind_param("i", $id_musyawarah);
$stmt_hadir->execute();
$result_hadir = $stmt_hadir->get_result();
$stmt_hadir->close();

// 5. Ambil Poin Notulensi musyawarah SAAT INI
$stmt_poin = $conn->prepare("SELECT poin_pembahasan FROM notulensi_poin WHERE id_musyawarah = ? ORDER BY id ASC");
$stmt_poin->bind_param("i", $id_musyawarah);
$stmt_poin->execute();
$result_poin = $stmt_poin->get_result();
$stmt_poin->close();

?>
<!-- Di sini Anda bisa menyertakan header/layout utama jika ada -->

<div class="container mx-auto p-4 sm:p-6 lg:p-8">

    <!-- Header Halaman -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <a href="?page=musyawarah/daftar_musyawarah" class="text-cyan-600 hover:text-cyan-800 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Daftar
            </a>
            <a href="pages/musyawarah/cetak_notulensi?id=<?php echo $id_musyawarah; ?>" target="_blank" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
                <i class="fas fa-print mr-2"></i> Cetak
            </a>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mt-4"><?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?></h1>
    </div>

    <!-- Grid untuk menata konten -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Kolom Kiri: Detail & Daftar Hadir -->
        <div class="lg:col-span-1 flex flex-col gap-6">

            <!-- Card Detail Musyawarah -->
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-3">Detail Acara</h2>
                <div class="space-y-3 text-gray-700">
                    <p><strong><i class="fas fa-calendar-alt w-5 mr-2 text-cyan-500"></i>Tanggal:</strong> <?php echo date('d F Y', strtotime($musyawarah['tanggal'])); ?></p>
                    <p><strong><i class="fas fa-clock w-5 mr-2 text-cyan-500"></i>Waktu:</strong> <?php echo date('H:i', strtotime($musyawarah['waktu_mulai'])); ?> WIB</p>
                    <p><strong><i class="fas fa-map-marker-alt w-5 mr-2 text-cyan-500"></i>Tempat:</strong> <?php echo htmlspecialchars($musyawarah['tempat']); ?></p>
                    <p><strong><i class="fas fa-check-square w-5 mr-2 text-cyan-500"></i>Status Acara:</strong>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800"><?php echo htmlspecialchars($musyawarah['status']); ?></span>
                    </p>
                </div>
            </div>

            <!-- Card Daftar Hadir -->
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-3">Daftar Hadir Peserta</h2>
                <div class="overflow-auto max-h-96">
                    <ul class="space-y-3">
                        <?php if ($result_hadir->num_rows > 0): $no = 1; ?>
                            <?php while ($peserta = $result_hadir->fetch_assoc()): ?>
                                <li class="flex items-start">
                                    <span class="mr-3 text-gray-500"><?php echo $no++; ?>.</span>
                                    <div>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($peserta['nama_peserta']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($peserta['jabatan']); ?> - <span class="font-medium text-green-600"><?php echo htmlspecialchars($peserta['status']); ?></span></p>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="text-center text-gray-500">Tidak ada data kehadiran.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Tinjauan & Poin Baru -->
        <div class="lg:col-span-2 flex flex-col gap-6">

            <!-- Tinjauan Musyawarah Sebelumnya -->
            <?php if ($poin_sebelumnya && $poin_sebelumnya->num_rows > 0): ?>
                <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-yellow-400">
                    <h2 class="text-xl font-bold text-gray-800 mb-1">Evaluasi Musyawarah Sebelumnya</h2>
                    <p class="text-sm text-gray-500 mb-4">Dari Musyawarah: "<?php echo htmlspecialchars($musyawarah_sebelumnya['nama_musyawarah']); ?>" (<?php echo date('d M Y', strtotime($musyawarah_sebelumnya['tanggal'])); ?>)</p>
                    <div class="space-y-4">
                        <?php $no_prev = 1; ?>
                        <?php while ($poin = $poin_sebelumnya->fetch_assoc()): ?>
                            <div class="border rounded-lg p-3 bg-gray-50">
                                <div class="flex items-start">
                                    <span class="font-bold text-gray-500 mr-3"><?php echo $no_prev++; ?>.</span>
                                    <p class="text-gray-800 flex-1"><?php echo nl2br(htmlspecialchars($poin['poin_pembahasan'])); ?></p>
                                </div>
                                <div class="mt-2 pl-8 border-l-2 ml-2 border-gray-200">
                                    <p class="text-sm"><strong>Status:</strong>
                                        <?php
                                        if ($poin['status_evaluasi'] == 'Terlaksana') {
                                            echo '<span class="font-semibold text-green-600"><i class="fas fa-check-circle mr-1"></i> Terlaksana</span>';
                                        } elseif ($poin['status_evaluasi'] == 'Belum Terlaksana') {
                                            echo '<span class="font-semibold text-red-600"><i class="fas fa-times-circle mr-1"></i> Belum Terlaksana</span>';
                                        } else {
                                            echo '<span class="font-semibold text-gray-500"><i class="far fa-circle mr-1"></i> Belum Dievaluasi</span>';
                                        }
                                        ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1"><strong>Keterangan:</strong> <?php echo htmlspecialchars($poin['keterangan'] ?: '-'); ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Poin Notulensi Musyawarah Saat Ini -->
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-3">Poin Baru yang Diputuskan</h2>
                <div class="space-y-4">
                    <?php if ($result_poin->num_rows > 0): $no = 1; ?>
                        <?php while ($poin = $result_poin->fetch_assoc()): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex items-start">
                                    <span class="font-bold text-gray-500 mr-3"><?php echo $no++; ?>.</span>
                                    <p class="text-gray-800 flex-1"><?php echo nl2br(htmlspecialchars($poin['poin_pembahasan'])); ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center py-6 text-gray-500">Belum ada poin baru yang dicatat pada musyawarah ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Di sini Anda bisa menyertakan footer jika ada -->
<?php
$conn->close();
?>