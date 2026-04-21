<?php
// ============================================================================
// --- BACKEND: PENGOLAHAN DATA & LOGIKA ---
// ============================================================================

// Variabel $conn dan data session sudah tersedia dari index.php
$guru_kelompok = $_SESSION['user_kelompok'] ?? '';
$guru_kelas = $_SESSION['user_kelas'] ?? '';

// Variabel dari session (sudah ada di baris 7 & 8, kita satukan di sini)
$selected_kelompok = $_SESSION['user_kelompok'] ?? '';
$selected_kelas = $_SESSION['user_kelas'] ?? '';

// === AMBIL DAFTAR PERIODE AKTIF UNTUK FILTER ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode, tanggal_mulai, tanggal_selesai 
                FROM periode 
                WHERE status = 'Aktif' 
                ORDER BY tanggal_mulai DESC";

$result_periode = $conn->query($sql_periode);
if ($result_periode && $result_periode->num_rows > 0) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// === LOGIKA BARU: Tentukan Periode Default Berdasarkan Tanggal Hari Ini ===
$default_periode_id = null;
$today = date('Y-m-d');

foreach ($periode_list as $p) {
    if ($today >= $p['tanggal_mulai'] && $today <= $p['tanggal_selesai']) {
        $default_periode_id = $p['id'];
        break; // Ditemukan periode yang aktif hari ini
    }
}

// Jika tidak ada periode yang cocok, ambil periode terbaru
if ($default_periode_id === null && !empty($periode_list)) {
    $default_periode_id = $periode_list[0]['id'];
}

// === MODIFIKASI: Ambil periode dari URL, atau gunakan default ===
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : $default_periode_id;

$selected_periode_nama = '';
if ($selected_periode_id) {
    $stmt_periode_nama = $conn->prepare("SELECT nama_periode FROM periode WHERE id = ?");
    $stmt_periode_nama->bind_param("i", $selected_periode_id);
    $stmt_periode_nama->execute();
    $result_periode_nama = $stmt_periode_nama->get_result();
    if ($result_periode_nama->num_rows > 0) {
        $selected_periode_nama = $result_periode_nama->fetch_assoc()['nama_periode'];
    }
}

// === 1. AMBIL DAFTAR JADWAL ===
$jadwal_list = [];
$rekap_petugas_data = [];

if ($selected_periode_id && !empty($guru_kelompok) && !empty($guru_kelas)) {
    $sql = "SELECT 
                jp.id, jp.tanggal, jp.jam_mulai, jp.jam_selesai, jp.pengajar,
                GROUP_CONCAT(DISTINCT g.nama SEPARATOR ', ') as daftar_guru,
                GROUP_CONCAT(DISTINCT p.nama SEPARATOR ', ') as daftar_penasehat
            FROM jadwal_presensi jp
            LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id
            LEFT JOIN guru g ON jg.guru_id = g.id
            LEFT JOIN jadwal_penasehat jn ON jp.id = jn.jadwal_id
            LEFT JOIN penasehat p ON jn.penasehat_id = p.id
            WHERE jp.kelompok = ? AND jp.kelas = ? AND jp.periode_id = ?
            GROUP BY jp.id
            ORDER BY jp.tanggal DESC, jp.jam_mulai DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $guru_kelompok, $guru_kelas, $selected_periode_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $jadwal_list[] = $row;
        }
    }
    $stmt->close();

    // === 2. AMBIL REKAP PETUGAS ===
    $sql_rekap = "SELECT jp.tanggal, jp.jam_mulai, jp.jam_selesai, 
                  GROUP_CONCAT(DISTINCT g.nama ORDER BY g.nama SEPARATOR '\n') as daftar_guru, 
                  GROUP_CONCAT(DISTINCT p.nama ORDER BY p.nama SEPARATOR '\n') as daftar_penasehat 
                  FROM jadwal_presensi jp 
                  LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id 
                  LEFT JOIN guru g ON jg.guru_id = g.id 
                  LEFT JOIN jadwal_penasehat jn ON jp.id = jn.jadwal_id 
                  LEFT JOIN penasehat p ON jn.penasehat_id = p.id 
                  WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ? 
                  GROUP BY jp.tanggal, jp.jam_mulai, jp.jam_selesai 
                  ORDER BY jp.tanggal ASC";
    $stmt_rekap = $conn->prepare($sql_rekap);
    $stmt_rekap->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_rekap->execute();
    $result_rekap = $stmt_rekap->get_result();
    if ($result_rekap) {
        while ($row = $result_rekap->fetch_assoc()) {
            $rekap_petugas_data[] = $row;
        }
    }
}

// === 3. AMBIL DATA TARGET BULANAN (UNTUK MODAL) ===
$target_bulanan_list = [];
if ($selected_periode_id && !empty($guru_kelompok) && !empty($guru_kelas)) {
    $sql_target = "SELECT * FROM target_pembelajaran 
                   WHERE periode_id = ? 
                   AND (kelas = ? OR kelas = 'Semua') 
                   AND (kelompok = ? OR kelompok = 'Semua')
                   ORDER BY kategori ASC, judul_materi ASC";

    $stmt_target = $conn->prepare($sql_target);
    $stmt_target->bind_param("iss", $selected_periode_id, $guru_kelas, $guru_kelompok);
    $stmt_target->execute();
    $res_target = $stmt_target->get_result();

    if ($res_target) {
        while ($row = $res_target->fetch_assoc()) {
            $target_bulanan_list[] = $row;
        }
    }
    $stmt_target->close();
}
?>

<!-- ============================================================================ -->
<!-- --- FRONTEND: ANTARMUKA PENGGUNA (HTML) ---                               -->
<!-- ============================================================================ -->
<div class="bg-white p-4 sm:p-6 md:p-8 rounded-xl shadow-lg w-full mx-auto">

    <!-- Header Halaman -->
    <div class="mb-6 border-b pb-4 flex flex-col md:flex-row justify-between md:items-end gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Jadwal Mengajar
            </h1>
            <p class="text-md text-gray-500 mt-1">
                Pilih periode untuk melihat jadwal mengajar.
            </p>
        </div>

        <!-- TOMBOL MODAL TARGET (Hanya muncul jika ada target) -->
        <?php if (!empty($target_bulanan_list)): ?>
            <button id="btnOpenTarget" class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-3 md:py-2 px-4 rounded-lg shadow transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-bullseye"></i> Lihat Probul Bulan Ini
            </button>
        <?php else: ?>
            <button id="btnOpenTarget" class="w-full md:w-auto bg-gray-600 text-white font-bold py-3 md:py-2 px-4 rounded-lg flex items-center justify-center gap-2" disabled>
                <i class="fa-solid fa-bullseye"></i> Lihat Probul Bulan Ini
            </button>
        <?php endif; ?>
    </div>

    <!-- Filter Periode -->
    <div class="mb-6 bg-gray-50 p-3 md:p-4 rounded-lg border">
        <form id="filterForm" method="GET" action="">
            <input type="hidden" name="page" value="jadwal">
            <label for="periode_id" class="block text-xs md:text-sm font-medium text-gray-700">Pilih Periode</label>
            <div class="flex flex-col md:flex-row items-center gap-2 mt-1 md:mt-2">
                <select id="periode_id" name="periode_id" class="flex-grow w-full py-2.5 px-3 border border-gray-300 bg-white rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm" required>
                    <option value="">-- Tampilkan Jadwal untuk Periode --</option>
                    <?php foreach ($periode_list as $periode): ?>
                        <option value="<?php echo $periode['id']; ?>" <?php echo ($selected_periode_id == $periode['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($periode['nama_periode']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition text-sm">Tampilkan</button>
            </div>
        </form>
    </div>

    <!-- Tabel Data Jadwal Utama -->
    <?php if ($selected_periode_id): ?>

        <!-- JADWAL LIST: DESKTOP (Tabel Padat) -->
        <div class="hidden md:block mb-8 border border-gray-200 rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Tanggal & Jam</th>
                        <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Pemateri</th>
                        <th class="px-4 py-2.5 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Jurnal</th>
                        <th class="px-4 py-2.5 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($jadwal_list)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-6 text-gray-500 text-sm">Belum ada jadwal.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($jadwal_list as $jadwal): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-bold text-gray-900 text-sm"><?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo date("H:i", strtotime($jadwal['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal['jam_selesai'])); ?></div>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div><span class="font-semibold text-gray-600">Guru:</span> <span class="text-gray-900"><?php echo htmlspecialchars($jadwal['daftar_guru'] ?? '-'); ?></span></div>
                                    <div><span class="font-semibold text-gray-600">Penasehat:</span> <span class="text-gray-900"><?php echo htmlspecialchars($jadwal['daftar_penasehat'] ?? '-'); ?></span></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-full <?php echo !empty($jadwal['pengajar']) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo !empty($jadwal['pengajar']) ? 'Terisi' : 'Kosong'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <a href="?page=input_presensi&jadwal_id=<?php echo $jadwal['id']; ?>" class="bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 font-bold py-1.5 px-3 rounded text-xs transition duration-200">
                                        Input Presensi
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- JADWAL LIST: MOBILE (Card Compact) -->
        <div class="block md:hidden mb-8 space-y-3">
            <?php if (empty($jadwal_list)): ?>
                <div class="text-center py-6 text-gray-500 text-sm border rounded-lg bg-gray-50">Belum ada jadwal.</div>
            <?php else: ?>
                <?php foreach ($jadwal_list as $jadwal): ?>
                    <div class="bg-white border border-gray-200 p-3 rounded-lg shadow-sm">
                        <!-- Baris Atas: Tanggal & Jam -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2 mb-2">
                            <div class="font-bold text-gray-900 text-sm"><?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?></div>
                            <div class="text-[11px] text-gray-600 bg-gray-100 px-2 py-0.5 rounded font-semibold"><i class="fa-regular fa-clock"></i> <?php echo date("H:i", strtotime($jadwal['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal['jam_selesai'])); ?></div>
                        </div>
                        <!-- Baris Tengah: Pemateri -->
                        <div class="text-xs space-y-1 mb-2">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Guru:</span>
                                <span class="text-gray-900 font-semibold text-right max-w-[65%]"><?php echo htmlspecialchars($jadwal['daftar_guru'] ?? '-'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Penasehat:</span>
                                <span class="text-gray-900 font-semibold text-right max-w-[65%]"><?php echo htmlspecialchars($jadwal['daftar_penasehat'] ?? '-'); ?></span>
                            </div>
                        </div>
                        <!-- Baris Bawah: Status & Tombol -->
                        <div class="flex justify-between items-center pt-2 border-t border-gray-50">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded <?php echo !empty($jadwal['pengajar']) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <?php echo !empty($jadwal['pengajar']) ? 'Jurnal Terisi' : 'Jurnal Kosong'; ?>
                            </span>
                            <a href="?page=input_presensi&jadwal_id=<?php echo $jadwal['id']; ?>" class="text-xs bg-indigo-50 text-indigo-700 font-bold px-3 py-1.5 rounded border border-indigo-200 text-center flex-grow ml-3">
                                Input Presensi
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ========================================================= -->
        <!-- REKAP PETUGAS (Jadwal Guru & Penasehat) -->
        <!-- ========================================================= -->
        <div class="border border-gray-200 md:border-black bg-white p-4 mt-4 rounded-xl shadow-sm">
            <h3 class="text-lg font-bold md:font-semibold text-center text-gray-800 leading-tight">Jadwal Guru & Penasehat</h3>
            <p class="text-[13px] text-center text-gray-600 mb-4 mt-1">
                <span class="font-semibold text-gray-800 capitalize"><?php echo htmlspecialchars($selected_kelompok); ?></span> -
                <span class="font-semibold text-gray-800 capitalize"><?php echo htmlspecialchars($selected_kelas); ?></span>
            </p>

            <!-- REKAP PETUGAS: DESKTOP (Tabel Standar Rapat) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-center text-gray-700 w-10">No</th>
                            <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-center text-gray-700 w-48">Tanggal</th>
                            <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-center text-gray-700">Guru</th>
                            <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-center text-gray-700">Penasehat</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <?php if (empty($rekap_petugas_data)): ?>
                            <tr>
                                <td colspan="4" class="border border-gray-300 text-center py-4 text-sm text-gray-500">Tidak ada data.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1;
                            foreach ($rekap_petugas_data as $item): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="border border-gray-300 px-3 py-2 text-center text-sm text-gray-700 font-semibold align-top"><?php echo $no++; ?></td>
                                    <td class="border border-gray-300 px-3 py-2 text-center align-top">
                                        <div class="font-bold text-gray-800 text-[13px]"><?php echo format_hari_tanggal(date("l, d M Y", strtotime($item['tanggal']))); ?></div>
                                        <div class="text-[11px] text-gray-500"><?php echo date("H:i", strtotime($item['jam_mulai'])) . ' - ' . date("H:i", strtotime($item['jam_selesai'])); ?></div>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-sm text-center text-blue-900 whitespace-pre-line align-top"><?php echo !empty($item['daftar_guru']) ? nl2br(htmlspecialchars($item['daftar_guru'])) : '<i class="text-gray-400 font-normal">--</i>'; ?></td>
                                    <td class="border border-gray-300 px-3 py-2 text-sm text-center text-green-900 whitespace-pre-line align-top"><?php echo !empty($item['daftar_penasehat']) ? nl2br(htmlspecialchars($item['daftar_penasehat'])) : '<i class="text-gray-400 font-normal">--</i>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- REKAP PETUGAS: MOBILE (Grid Card Compact) -->
            <div class="block md:hidden space-y-3">
                <?php if (empty($rekap_petugas_data)): ?>
                    <div class="text-center py-4 text-sm text-gray-500 border rounded bg-gray-50">Tidak ada data.</div>
                <?php else: ?>
                    <?php foreach ($rekap_petugas_data as $item): ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-2.5 shadow-sm">
                            <!-- Header Tanggal -->
                            <div class="flex justify-between items-center border-b border-gray-100 pb-1.5 mb-1.5">
                                <div class="font-bold text-indigo-700 text-[13px]"><?php echo format_hari_tanggal(date("l, d M Y", strtotime($item['tanggal']))); ?></div>
                                <div class="text-[10px] font-bold bg-gray-100 text-gray-600 px-2 py-0.5 rounded"><i class="fa-regular fa-clock"></i> <?php echo date("H:i", strtotime($item['jam_mulai'])) . ' - ' . date("H:i", strtotime($item['jam_selesai'])); ?></div>
                            </div>
                            <!-- Isi Guru & Penasehat -->
                            <div class="grid grid-cols-2 gap-2">
                                <div class="bg-blue-50/50 p-1.5 rounded border border-blue-100">
                                    <div class="text-[9px] text-blue-500 font-bold uppercase tracking-wide">👨‍🏫 Guru</div>
                                    <div class="text-gray-800 text-[12px] font-medium whitespace-pre-line leading-tight mt-0.5">
                                        <?php echo !empty($item['daftar_guru']) ? nl2br(htmlspecialchars($item['daftar_guru'])) : '<i class="text-gray-400 font-normal">Kosong</i>'; ?>
                                    </div>
                                </div>
                                <div class="bg-green-50/50 p-1.5 rounded border border-green-100">
                                    <div class="text-[9px] text-green-500 font-bold uppercase tracking-wide">👳‍♂️ Penasehat</div>
                                    <div class="text-gray-800 text-[12px] font-medium whitespace-pre-line leading-tight mt-0.5">
                                        <?php echo !empty($item['daftar_penasehat']) ? nl2br(htmlspecialchars($item['daftar_penasehat'])) : '<i class="text-gray-400 font-normal">Kosong</i>'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL TARGET BULANAN -->
<div id="modalTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 hidden backdrop-blur-sm px-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col transform transition-all scale-100">
        <!-- Header Modal -->
        <div class="bg-teal-600 px-6 py-4 flex justify-between items-center shrink-0">
            <div>
                <h3 class="text-lg font-bold text-white">Target Kurikulum</h3>
                <p class="text-teal-100 text-xs">Periode: <?php echo htmlspecialchars($selected_periode_nama); ?></p>
            </div>
            <button id="btnCloseTarget" class="text-white hover:text-gray-200 focus:outline-none">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Body Modal (Scrollable) -->
        <div class="p-4 md:p-6 overflow-y-auto custom-scrollbar">
            <?php if (empty($target_bulanan_list)): ?>
                <div class="text-center py-10 text-gray-500 bg-gray-50 rounded-lg border-2 border-dashed">
                    <i class="fa-solid fa-clipboard-list text-4xl mb-2 text-gray-300"></i>
                    <p>Belum ada target yang diatur Admin untuk periode ini.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500 border border-gray-200">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th class="px-4 py-3 border-b">Kategori</th>
                                <th class="px-4 py-3 border-b">Materi / Judul</th>
                                <th class="px-4 py-3 border-b text-center">Target</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($target_bulanan_list as $t):
                                // Format Tampilan Target
                                $display_target = "";
                                if ($t['tipe_input'] == 'CHECKLIST') {
                                    $display_target = '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-bold">Poin</span>';
                                } elseif ($t['tipe_input'] == 'RANGE') {
                                    $start = (float)$t['target_start'];
                                    $end = (float)$t['target_end'];
                                    $display_target = "<span class='text-xs'>{$t['satuan']}</span> <span class='font-semibold'>$start - $end</span>";
                                } else {
                                    $vol = (float)$t['total_volume'];
                                    $display_target = "<span class='text-xs'>{$t['satuan']}</span> <span class='font-semibold'>$vol</span>";
                                }
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900 border-r bg-gray-50 w-1/4">
                                        <?php echo htmlspecialchars($t['kategori']); ?>
                                    </td>
                                    <td class="px-4 py-3 border-r">
                                        <?php echo htmlspecialchars($t['judul_materi']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-center bg-gray-50 w-1/5">
                                        <?php echo $display_target; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer Modal -->
        <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex justify-end shrink-0">
            <button id="btnTutupBawah" class="w-full md:w-auto bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2.5 md:py-2 px-4 rounded-lg transition">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const periodeSelect = document.getElementById('periode_id');

        if (periodeSelect) {
            periodeSelect.addEventListener('change', function() {
                const selectedPeriodeId = this.value;
                if (selectedPeriodeId) {
                    window.location.href = '?page=jadwal&periode_id=' + selectedPeriodeId;
                }
            });
        }

        // === LOGIKA MODAL TARGET (BARU) ===
        const modalTarget = document.getElementById('modalTarget');
        const btnOpen = document.getElementById('btnOpenTarget');
        const btnClose = document.getElementById('btnCloseTarget');
        const btnTutupBawah = document.getElementById('btnTutupBawah');

        if (btnOpen && modalTarget) {
            const toggleModal = (show) => {
                if (show) modalTarget.classList.remove('hidden');
                else modalTarget.classList.add('hidden');
            };

            btnOpen.addEventListener('click', () => toggleModal(true));
            if (btnClose) btnClose.addEventListener('click', () => toggleModal(false));
            if (btnTutupBawah) btnTutupBawah.addEventListener('click', () => toggleModal(false));

            // Tutup jika klik di luar area modal (backdrop)
            modalTarget.addEventListener('click', (e) => {
                if (e.target === modalTarget) toggleModal(false);
            });
        }
    });
</script>