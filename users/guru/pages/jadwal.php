<?php
// Variabel $conn dan data session sudah tersedia dari index.php
$guru_kelompok = $_SESSION['user_kelompok'] ?? '';
$guru_kelas = $_SESSION['user_kelas'] ?? '';

// Ambil periode yang dipilih dari URL
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : null;
$selected_kelompok = $_SESSION['user_kelompok'] ?? '';
$selected_kelas = $_SESSION['user_kelas'] ?? '';

// === AMBIL DAFTAR PERIODE AKTIF UNTUK FILTER ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode && $result_periode->num_rows > 0) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

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

// === AMBIL DAFTAR JADWAL JIKA PERIODE DIPILIH ===
$jadwal_list = [];
$rekap_petugas_data = [];
if ($selected_periode_id && !empty($guru_kelompok) && !empty($guru_kelas)) {
    // $sql = "SELECT jp.id, jp.tanggal, jp.jam_mulai, jp.jam_selesai, jp.pengajar, p.nama_periode
    //         FROM jadwal_presensi jp
    //         JOIN periode p ON jp.periode_id = p.id
    //         WHERE jp.kelompok = ? 
    //           AND jp.kelas = ? 
    //           AND p.id = ?
    //         ORDER BY jp.tanggal DESC, jp.jam_mulai DESC";

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

    // 2. Ambil data untuk tabel kedua (Rekapitulasi Petugas)
    $sql_rekap = "SELECT jp.tanggal, jp.jam_mulai, jp.jam_selesai, GROUP_CONCAT(DISTINCT g.nama ORDER BY g.nama SEPARATOR '\n') as daftar_guru, GROUP_CONCAT(DISTINCT p.nama ORDER BY p.nama SEPARATOR '\n') as daftar_penasehat FROM jadwal_presensi jp LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id LEFT JOIN guru g ON jg.guru_id = g.id LEFT JOIN jadwal_penasehat jn ON jp.id = jn.jadwal_id LEFT JOIN penasehat p ON jn.penasehat_id = p.id WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ? GROUP BY jp.tanggal, jp.jam_mulai, jp.jam_selesai ORDER BY jp.tanggal ASC";
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
?>
<div class="bg-white p-6 sm:p-8 rounded-xl shadow-lg w-full mx-auto">
    <!-- Header Halaman -->
    <div class="mb-6 border-b pb-4">
        <h1 class="text-2xl font-bold text-gray-800">
            Jadwal Mengajar Anda
        </h1>
        <p class="text-md text-gray-500 mt-1">
            Pilih periode untuk melihat jadwal mengajar Anda.
        </p>
    </div>

    <!-- Filter Periode -->
    <div class="mb-6 bg-gray-50 p-4 rounded-lg border">
        <form id="filterForm" method="GET" action="">
            <input type="hidden" name="page" value="jadwal">
            <label for="periode_id" class="block text-sm font-medium text-gray-700">Pilih Periode</label>
            <div class="flex items-center gap-2 mt-1">
                <select id="periode_id" name="periode_id" class="flex-grow mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                    <option value="">-- Tampilkan Jadwal untuk Periode --</option>
                    <?php foreach ($periode_list as $periode): ?>
                        <option value="<?php echo $periode['id']; ?>" <?php echo ($selected_periode_id === (int)$periode['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($periode['nama_periode']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Tampilkan</button>
            </div>
        </form>
    </div>

    <!-- Tabel Data Jadwal (Hanya tampil jika periode dipilih) -->
    <?php if ($selected_periode_id): ?>
        <div class="p-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal & Jam</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pemateri</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jurnal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($jadwal_list)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-10 text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p class="mt-2 font-semibold">Tidak ada jadwal</p>
                                <p class="text-sm">Belum ada jadwal yang dibuat untuk kelas Anda pada periode ini.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($jadwal_list as $jadwal): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date("H:i", strtotime($jadwal['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal['jam_selesai'])); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div>
                                        <span class="font-semibold">Guru:</span>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($jadwal['daftar_guru'] ?? 'Belum Diatur'); ?></span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Penasehat:</span>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($jadwal['daftar_penasehat'] ?? 'Belum Diatur'); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo !empty($jadwal['pengajar']) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo !empty($jadwal['pengajar']) ? 'Terisi' : 'Kosong'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="?page=input_presensi&jadwal_id=<?php echo $jadwal['id']; ?>" class="text-indigo-600 hover:text-indigo-900 font-bold">
                                        Input Presensi
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TABEL REKAP PETUGAS BARU -->
        <div class="border border-black bg-white p-6 mt-4 rounded-lg shadow-md overflow-x-auto">
            <h3 class="text-xl font-medium text-center text-gray-800">Jadwal Guru dan Penasehat</h3>
            <p class="text-md text-center text-gray-800">
                Periode: <span class="font-semibold"><?php echo htmlspecialchars($selected_periode_nama); ?></span>
            </p>
            <p class="text-md text-center text-gray-800 mb-4">
                <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelompok); ?></span> -
                <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelas); ?></span>
            </p>
            <table class="border min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-1/12 border px-4 py-2 text-left text-xs font-medium text-center text-gray-500 uppercase">No</th>
                        <th class="w-3/12 border px-4 py-2 text-left text-xs font-medium text-center text-gray-500 uppercase">Tanggal</th>
                        <th class="w-4/12 border px-4 py-2 text-left text-xs font-medium text-center text-gray-500 uppercase">Guru</th>
                        <th class="w-4/12 border px-4 py-2 text-left text-xs font-medium text-center text-gray-500 uppercase">Penasehat</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($rekap_petugas_data)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">Tidak ada data petugas yang ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $no = 1;
                        foreach ($rekap_petugas_data as $item): ?>
                            <tr>
                                <td class="border px-4 py-3 align-top font-semibold text-center"><?php echo $no++; ?></td>
                                <td class="border px-4 py-3 align-top font-semibold text-center">
                                    <?php echo format_hari_tanggal(date("l, d F Y", strtotime($item['tanggal']))); ?>
                                    <p class="text-sm text-gray-500"><?php echo date("H:i", strtotime($item['jam_mulai'])) . ' - ' . date("H:i", strtotime($item['jam_selesai'])); ?></p>
                                </td>
                                <td class="border px-4 py-3 align-top text-sm whitespace-pre-line text-center"><?php echo !empty($item['daftar_guru']) ? nl2br(htmlspecialchars($item['daftar_guru'])) : '<i class="text-gray-400">--</i>'; ?></td>
                                <td class="border px-4 py-3 align-top text-sm whitespace-pre-line text-center"><?php echo !empty($item['daftar_penasehat']) ? nl2br(htmlspecialchars($item['daftar_penasehat'])) : '<i class="text-gray-400">--</i>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const periodeSelect = document.getElementById('periode_id');

        periodeSelect.addEventListener('change', function() {
            const selectedPeriodeId = this.value;
            if (selectedPeriodeId) {
                window.location.href = '?page=jadwal&periode_id=' + selectedPeriodeId;
            }
        });
    });
</script>