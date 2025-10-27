<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$jetuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL, sediakan nilai default 'semua'
$filter_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

// Jika admin tingkat kelompok, paksa filter kelompok
if ($jetuapjp_tingkat === 'kelompok') {
    $filter_kelompok = $ketuapjp_kelompok;
}

// === AMBIL DATA GURU BERDASARKAN FILTER ===
$guru_list = [];
$sql = "SELECT * FROM guru";
$params = [];
$types = "";
$where_conditions = [];

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
$sql .= " ORDER BY kelompok ASC, FIELD(kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'), nama ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $guru_list[] = $row;
    }
}
$stmt->close();
?>
<div class="container mx-auto">
    <!-- Header Halaman -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Daftar Guru</h3>
    </div>

    <!-- BAGIAN FILTER BARU -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
        <form method="GET" action="">
            <input type="hidden" name="page" value="master/daftar_guru">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium">Filter Kelompok</label>
                    <?php if ($jetuapjp_tingkat === 'kelompok'): ?>
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor WA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kartu Akses</th>
                </tr>
            </thead>
            <tbody id="guruTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($guru_list)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">Tidak ada data guru yang cocok dengan filter.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1;
                    foreach ($guru_list as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $i++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($user['nama']); ?></div>
                                <div class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($user['kelompok']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize font-semibold"><?php echo htmlspecialchars($user['kelas'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['nomor_wa'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <!-- <button class="qr-code-btn text-blue-500 hover:text-blue-700"
                                    data-barcode="<?php echo htmlspecialchars($user['barcode']); ?>"
                                    data-nama="<?php echo htmlspecialchars($user['nama']); ?>">Lihat & Download
                                </button> -->
                                <!-- TOMBOL BARU UNTUK MENCETAK KARTU -->
                                <a href="actions/cetak_kartu.php?guru_id=<?php echo $user['id']; ?>" target="_blank" class="text-blue-500 hover:text-blue-700">
                                    Cetak Kartu
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal QR Code BARU -->
<div id="qrCodeModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-center p-6 overflow-hidden shadow-xl transform transition-all sm:max-w-sm sm:w-full">
            <h3 class="text-lg font-medium text-gray-900">QR Code untuk <span id="qr_nama" class="font-bold"></span></h3>
            <div id="qrcode-container" class="my-4 flex justify-center"></div>
            <a id="download-qr-link" href="#" download="qrcode.png" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">Download</a>
            <button type="button" class="modal-close-btn ml-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg">Tutup</button>
        </div>
    </div>
</div>

<!-- Library untuk generate QR Code -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const modals = {
            qr: document.getElementById('qrCodeModal') // Tambahkan modal QR
        };
        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');
        document.getElementById('tambahGuruBtn').onclick = () => openModal(modals.tambah);
        Object.values(modals).forEach(modal => {
            if (modal) modal.addEventListener('click', e => {
                if (e.target === modal || e.target.closest('.modal-close-btn')) closeModal(modal);
            });
        });

        document.getElementById('guruTableBody').addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            // LOGIKA BARU UNTUK TOMBOL QR
            if (target.classList.contains('qr-code-btn')) {
                const container = document.getElementById('qrcode-container');
                const downloadLink = document.getElementById('download-qr-link');

                container.innerHTML = ''; // Kosongkan QR lama
                document.getElementById('qr_nama').textContent = target.dataset.nama;

                new QRCode(container, {
                    text: target.dataset.barcode,
                    width: 200,
                    height: 200,
                });

                // Beri jeda agar canvas sempat tergambar sebelum membuat link download
                setTimeout(() => {
                    const canvas = container.querySelector('canvas');
                    if (canvas) {
                        downloadLink.href = canvas.toDataURL("image/png");
                        downloadLink.download = `qrcode-${target.dataset.nama.replace(/\s+/g, '-')}.png`;
                    }
                }, 100);

                openModal(modals.qr);
            }
        });
    });
</script>