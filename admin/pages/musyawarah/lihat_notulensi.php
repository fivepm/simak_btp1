<?php
$id_musyawarah = $_GET['id'] ?? null;

// ===================================================================
//  PENGAMBILAN DATA UNTUK TAMPILAN
// ===================================================================

// Ambil detail musyawarah untuk judul halaman
$stmt_musyawarah = $conn->prepare("SELECT * FROM musyawarah WHERE id = ?");
$stmt_musyawarah->bind_param("i", $id_musyawarah);
$stmt_musyawarah->execute();
$musyawarah = $stmt_musyawarah->get_result()->fetch_assoc();
$stmt_musyawarah->close();

// Ambil semua poin notulensi yang sudah ada untuk musyawarah ini
$stmt_poin = $conn->prepare("SELECT * FROM notulensi_poin WHERE id_musyawarah = ? ORDER BY id ASC");
$stmt_poin->bind_param("i", $id_musyawarah);
$stmt_poin->execute();
$result_poin = $stmt_poin->get_result();
$stmt_poin->close();

?>

<!-- Di sini Anda bisa menyertakan header atau layout utama admin -->
<div class="p-6">
    <!-- Header Halaman -->
    <div class="mb-6">
        <a href="?page=musyawarah/daftar_notulensi" class="text-cyan-600 hover:text-cyan-800 transition-colors">
            &larr; Kembali ke Daftar Notulensi
        </a>
        <h1 class="text-3xl font-bold text-gray-800 mt-2">Hasil Musyawarah</h1>
        <p class="text-gray-600">
            <?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?> -
            <span class="font-medium"><?php echo date('d F Y', strtotime($musyawarah['tanggal'])); ?></span>
        </p>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <!-- Detail Musyawarah -->
        <div class="mb-6 pb-4 border-b">
            <h2 class="text-2xl font-semibold text-gray-700 mb-2"><?= htmlspecialchars($musyawarah['nama_musyawarah']) ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 text-gray-600 gap-2">
                <p><i class="fas fa-calendar-alt mr-2 text-cyan-500"></i><?= date('l, d F Y', strtotime($musyawarah['tanggal'])) ?></p>
                <p><i class="fas fa-clock mr-2 text-cyan-500"></i>Pukul <?= date('H:i', strtotime($musyawarah['waktu_mulai'])) ?> WIB</p>
                <p><i class="fas fa-user-tie mr-2 text-cyan-500"></i>Pimpinan: <?= htmlspecialchars($musyawarah['pimpinan_rapat']) ?></p>
                <p><i class="fas fa-map-marker-alt mr-2 text-cyan-500"></i>Tempat: <?= htmlspecialchars($musyawarah['tempat']) ?></p>
            </div>
        </div>

        <!-- Daftar Poin yang Sudah Ada -->
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-4">Daftar Poin Notulensi</h2>
            <div class="space-y-3">
                <?php if ($result_poin->num_rows > 0): ?>
                    <?php $nomor = 1; ?>
                    <?php while ($poin = $result_poin->fetch_assoc()): ?>
                        <div class="flex items-start justify-between p-4 bg-gray-50 rounded-lg border">
                            <div class="flex items-start">
                                <span class="font-bold text-gray-500 mr-3"><?php echo $nomor++; ?>.</span>
                                <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($poin['poin_pembahasan'])); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-center py-4 text-gray-500">Belum ada poin notulensi yang ditambahkan untuk musyawarah ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>