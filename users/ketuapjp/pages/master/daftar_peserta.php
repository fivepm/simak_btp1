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
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}
$sql .= " ORDER BY nama_lengkap ASC";

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

// === TAMPILAN HTML ===
?>
<div class="container mx-auto">
    <!-- Header Halaman -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Daftar Peserta</h3>
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
    });
</script>