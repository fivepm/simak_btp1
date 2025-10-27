<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL
$filter_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

// Jika admin tingkat kelompok, paksa filter kelompok
if ($ketuapjp_tingkat === 'kelompok') {
    $filter_kelompok = $ketuapjp_kelompok;
}

// === AMBIL DATA PESERTA BERDASARKAN FILTER ===
$peserta_list = [];
$sql = "SELECT * FROM peserta";
$where_conditions = [];
$params = [];
$types = "";

if ($filter_kelompok !== 'semua') {
    $where_conditions[] = "kelompok = ?";
    $params[] = $filter_kelompok;
    $types .= "s";
}
if ($filter_kelas !== 'semua') {
    $where_conditions[] = "kelas = ?";
    $params[] = $filter_kelas;
    $types .= "s";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE status = 'Aktif' AND " . implode(" AND ", $where_conditions);
} else {
    $sql .= " WHERE status = 'Aktif'";
}

$sql .= " ORDER BY kelompok ASC, FIELD(kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'), nama_lengkap ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $peserta_list[] = $row;
    }
}
$stmt->close();


$peserta_per_kelas_rinci = [];
$peserta_per_kelas_total = [];
$grand_totals = [];
/// --- LOGIKA PHP ANDA UNTUK RINCIAN PESERTA (DIPINDAHKAN KE SINI) ---
if ($ketuapjp_tingkat === 'desa') {
    // Query rinci untuk admin desa
    $sql_kelas_rinci = "SELECT kelas, kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' GROUP BY kelas, kelompok, jenis_kelamin";
    $result_kelas_rinci = $conn->query($sql_kelas_rinci);
    if ($result_kelas_rinci) {
        while ($row = $result_kelas_rinci->fetch_assoc()) {
            // Pastikan konsistensi case (misal: semua lowercase)
            $kelas_key = strtolower($row['kelas']);
            $kelompok_key = strtolower($row['kelompok']);
            $peserta_per_kelas_rinci[$kelas_key][$kelompok_key][$row['jenis_kelamin']] = $row['jumlah'];
        }
    }
    // Hitung Grand Total untuk footer tabel (hanya untuk admin desa)
    $sql_grand_total = "SELECT kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' GROUP BY kelompok, jenis_kelamin";
    $result_grand_total = $conn->query($sql_grand_total);
    if ($result_grand_total) {
        while ($row = $result_grand_total->fetch_assoc()) {
            $kelompok_key = strtolower($row['kelompok']);
            $grand_totals[$kelompok_key][$row['jenis_kelamin']] = $row['jumlah'];
        }
    }
} else { // admin_level === 'kelompok'
    // Query total untuk admin kelompok
    $sql_kelas_total = "SELECT kelas, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' AND kelompok = ? GROUP BY kelas";
    $stmt_kelas_total = $conn->prepare($sql_kelas_total);
    if ($stmt_kelas_total) {
        $stmt_kelas_total->bind_param("s", $ketuapjp_kelompok);
        $stmt_kelas_total->execute();
        $result_kelas_total = $stmt_kelas_total->get_result();
        if ($result_kelas_total) {
            while ($row = $result_kelas_total->fetch_assoc()) {
                $kelas_key = strtolower($row['kelas']);
                $peserta_per_kelas_total[$kelas_key] = $row['jumlah'];
            }
        }
        $stmt_kelas_total->close();
    } else {
        error_log("Gagal prepare statement sql_kelas_total: " . $conn->error);
    }
}
// --- AKHIR LOGIKA RINCIAN PESERTA ---

// Definisikan list kelas dan kelompok untuk digunakan di HTML
$kelas_list_display = ['Paud', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah'];
$kelompok_list_display = ['Bintaran', 'Gedongkuning', 'Jombor', 'Sunten'];


// === TAMPILAN HTML ===
?>
<div class="container mx-auto">
    <!-- Header Halaman -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Daftar Peserta</h3>
        <div class="flex space-x-2">
            <!-- Tombol untuk membuka modal rincian peserta -->
            <button
                type="button"
                id="bukaRincianPesertaBtn"
                class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded-lg">
                <i class="fas fa-users mr-2"></i> Rincian Peserta
            </button>
        </div>
    </div>

    <!-- BAGIAN FILTER BARU -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
        <form method="GET" action="">
            <input type="hidden" name="page" value="master/daftar_peserta">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium">Filter Kelompok</label>
                    <?php if ($ketuapjp_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($ketuapjp_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md border-gray-300" disabled>
                    <?php else: ?>
                        <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                            <option value="semua">Semua Kelompok</option>
                            <option value="bintaran" <?php echo ($filter_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($filter_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($filter_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($filter_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium">Filter Kelas</label>
                    <select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                        <option value="semua">Semua Kelas</option>
                        <?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah']; ?>
                        <?php foreach ($kelas_opts as $k): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($filter_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabel Data -->
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelompok</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody id="pesertaTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($peserta_list)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">Belum ada data peserta.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1;
                    foreach ($peserta_list as $peserta): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $i++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($peserta['jenis_kelamin']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize"><?php echo htmlspecialchars($peserta['kelas']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize"><?php echo htmlspecialchars($peserta['kelompok']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo ($peserta['status'] === 'Aktif') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo htmlspecialchars($peserta['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="detail-btn text-gray-600 hover:text-gray-900" data-peserta='<?php echo json_encode($peserta); ?>'>Detail</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Detail Peserta -->
<div id="detailPesertaModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Detail Peserta</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="font-semibold text-gray-500">Nama Lengkap</dt>
                    <dd id="detail_nama_lengkap" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Jenis Kelamin</dt>
                    <dd id="detail_jenis_kelamin" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">TTL</dt>
                    <dd id="detail_ttl" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Kelas</dt>
                    <dd id="detail_kelas" class="text-gray-900 sm:col-span-1 capitalize"></dd>
                    <dt class="font-semibold text-gray-500">Kelompok</dt>
                    <dd id="detail_kelompok" class="text-gray-900 sm:col-span-1 capitalize"></dd>
                    <dt class="font-semibold text-gray-500">Status</dt>
                    <dd id="detail_status" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Nomor HP</dt>
                    <dd id="detail_nomor_hp" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Nama Orang Tua</dt>
                    <dd id="detail_nama_orang_tua" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Nomor HP Ortu</dt>
                    <dd id="detail_nomor_hp_orang_tua" class="text-gray-900 sm:col-span-1"></dd>
                </dl>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Tutup</button>
            </div>
        </div>
    </div>
</div>


<!-- Modal Rincian Peserta (sekarang berada di file ini) -->
<div id="modalRincianPeserta" class="fixed z-30 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">

        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-4xl">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 text-center" id="modal-title">
                            Rincian Jumlah Peserta
                        </h3>
                        <!-- ▼▼▼ KONTEN MODAL DIGANTI DENGAN KODE HTML ANDA ▼▼▼ -->
                        <div class="mt-4">
                            <?php if ($ketuapjp_tingkat === 'desa'): // Tampilan Tabel Rinci untuk Admin Desa 
                            ?>
                                <div class="overflow-x-auto border rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th rowspan="2" class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase align-middle border-r">Kelas</th>
                                                <?php foreach ($kelompok_list_display as $kelompok): ?>
                                                    <th colspan="2" class="px-4 py-2 text-center text-xs font-bold text-gray-600 uppercase border-l"><?php echo htmlspecialchars(ucfirst($kelompok)); ?></th>
                                                <?php endforeach; ?>
                                                <th rowspan="2" class="px-4 py-2 text-center text-xs font-bold text-gray-600 uppercase align-middle border-l">Total Kelas</th>
                                            </tr>
                                            <tr>
                                                <?php foreach ($kelompok_list_display as $kelompok): ?>
                                                    <th class="px-2 py-1 text-center text-xs font-bold text-gray-500 uppercase border-l">L</th>
                                                    <th class="px-2 py-1 text-center text-xs font-bold text-gray-500 uppercase">P</th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php
                                            $grand_totals_modal = []; // Reset untuk perhitungan di modal
                                            ?>
                                            <?php foreach ($kelas_list_display as $kelas):
                                                $total_per_kelas_modal = 0;
                                                $kelas_key = strtolower($kelas); // Gunakan lowercase key
                                            ?>
                                                <tr>
                                                    <td class="px-4 py-3 whitespace-nowrap font-semibold capitalize text-gray-800 border-r"><?php echo htmlspecialchars($kelas); ?></td>
                                                    <?php foreach ($kelompok_list_display as $kelompok):
                                                        $kelompok_key = strtolower($kelompok); // Gunakan lowercase key
                                                        // Mengambil data dari array PHP
                                                        $jumlah_l_modal = $peserta_per_kelas_rinci[$kelas_key][$kelompok_key]['Laki-laki'] ?? 0;
                                                        $jumlah_p_modal = $peserta_per_kelas_rinci[$kelas_key][$kelompok_key]['Perempuan'] ?? 0;
                                                        $total_per_kelas_modal += ($jumlah_l_modal + $jumlah_p_modal);
                                                    ?>
                                                        <td class="px-2 py-3 whitespace-nowrap text-center text-sm font-medium text-gray-700 border-l"><?php echo $jumlah_l_modal; ?></td>
                                                        <td class="px-2 py-3 whitespace-nowrap text-center text-sm font-medium text-gray-700"><?php echo $jumlah_p_modal; ?></td>
                                                    <?php endforeach; ?>
                                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-bold text-indigo-600 bg-indigo-50 border-l"><?php echo $total_per_kelas_modal; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="bg-gray-200 font-bold">
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap text-gray-800 border-r">TOTAL</td>
                                                <?php $grand_total_semua_modal = 0; ?>
                                                <?php foreach ($kelompok_list_display as $kelompok):
                                                    $kelompok_key = strtolower($kelompok); // Gunakan lowercase key
                                                    // Ambil dari $grand_totals yang dihitung di PHP atas
                                                    $total_l_modal = $grand_totals[$kelompok_key]['Laki-laki'] ?? 0;
                                                    $total_p_modal = $grand_totals[$kelompok_key]['Perempuan'] ?? 0;
                                                    $grand_total_semua_modal += ($total_l_modal + $total_p_modal);
                                                ?>
                                                    <td class="px-2 py-3 whitespace-nowrap text-center text-sm text-gray-800 border-l"><?php echo $total_l_modal; ?></td>
                                                    <td class="px-2 py-3 whitespace-nowrap text-center text-sm text-gray-800"><?php echo $total_p_modal; ?></td>
                                                <?php endforeach; ?>
                                                <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-indigo-700 bg-indigo-100 border-l"><?php echo $grand_total_semua_modal; ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php else: // Tampilan Kartu Total untuk Admin Kelompok 
                            ?>
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Peserta per Kelas di Kelompok <?php echo htmlspecialchars($ketuapjp_kelompok); ?></h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                    <?php foreach ($kelas_list_display as $kelas):
                                        $kelas_key = strtolower($kelas); // Gunakan lowercase key
                                    ?>
                                        <div class="text-center bg-gray-50 p-4 rounded-lg border">
                                            <p class="capitalize text-sm font-semibold text-gray-500"><?php echo htmlspecialchars($kelas); ?></p>
                                            <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $peserta_per_kelas_total[$kelas_key] ?? 0; ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- ▲▲▲ AKHIR KONTEN YANG DIGANTI ▲▲▲ -->
                    </div>
                </div>
            </div>
            <div class="bg-gray-100 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="modal-close-btn-rincian mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const detailModal = document.getElementById('detailPesertaModal');
        const btnTambah = document.getElementById('tambahPesertaBtn');
        const tableBody = document.getElementById('pesertaTableBody');

        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');


        // --- Event Listener untuk Tombol di Tabel ---
        tableBody.addEventListener('click', function(event) {
            const target = event.target;
            const data = target.dataset.peserta ? JSON.parse(target.dataset.peserta) : null;

            // Buka Modal Detail
            if (target.classList.contains('detail-btn')) {
                document.getElementById('detail_nama_lengkap').textContent = data.nama_lengkap || '-';
                document.getElementById('detail_jenis_kelamin').textContent = data.jenis_kelamin || '-';
                const ttl = (data.tempat_lahir && data.tanggal_lahir) ? `${data.tempat_lahir}, ${data.tanggal_lahir}` : '-';
                document.getElementById('detail_ttl').textContent = ttl;
                document.getElementById('detail_kelas').textContent = data.kelas || '-';
                document.getElementById('detail_kelompok').textContent = data.kelompok || '-';
                document.getElementById('detail_status').textContent = data.status || '-';
                document.getElementById('detail_nomor_hp').textContent = data.nomor_hp || '-';
                document.getElementById('detail_nama_orang_tua').textContent = data.nama_orang_tua || '-';
                document.getElementById('detail_nomor_hp_orang_tua').textContent = data.nomor_hp_orang_tua || '-';
                openModal(detailModal);
            }
        });

        // --- Tutup Semua Modal ---
        document.querySelectorAll('.fixed.z-20').forEach(modal => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal || event.target.closest('.modal-close-btn')) {
                    closeModal(modal);
                }
            });
        });

        // --- Logika untuk Tombol Rincian Peserta ---
        const tombolBukaRincian = document.getElementById('bukaRincianPesertaBtn');
        const modalRincian = document.getElementById('modalRincianPeserta');

        const bukaModalRincian = () => {
            if (modalRincian) modalRincian.classList.remove('hidden');
        }
        const tutupModalRincian = () => {
            if (modalRincian) modalRincian.classList.add('hidden');
        }

        if (tombolBukaRincian) {
            tombolBukaRincian.addEventListener('click', bukaModalRincian);
        }

        if (modalRincian) {
            modalRincian.addEventListener('click', function(event) {
                if (event.target === modalRincian || event.target.closest('.modal-close-btn-rincian')) {
                    tutupModalRincian();
                }
            });
            const tombolTutupModal = modalRincian.querySelector('.modal-close-btn-rincian');
            if (tombolTutupModal) {
                tombolTutupModal.addEventListener('click', tutupModalRincian);
            }
        }
    });
</script>