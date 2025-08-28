<?php
// Variabel $conn dan data session sudah tersedia dari index.php
$guru_kelompok = $_SESSION['user_kelompok'] ?? '';
$guru_kelas = $_SESSION['user_kelas'] ?? '';

// === AMBIL DAFTAR PESERTA SESUAI KELOMPOK & KELAS GURU ===
$peserta_list = [];
if (!empty($guru_kelompok) && !empty($guru_kelas)) {
    // Ambil semua kolom yang dibutuhkan untuk detail
    $sql = "SELECT nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, nomor_hp, nama_orang_tua, nomor_hp_orang_tua 
            FROM peserta 
            WHERE kelompok = ? AND kelas = ? AND status = 'Aktif'
            ORDER BY nama_lengkap ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $guru_kelompok, $guru_kelas);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $peserta_list[] = $row;
        }
    }
    $stmt->close();
}
?>
<div class="bg-white p-6 sm:p-8 rounded-xl shadow-lg w-full mx-auto">
    <!-- Header Halaman -->
    <div class="mb-6 border-b pb-4">
        <h1 class="text-2xl font-bold text-gray-800">
            Daftar Peserta
        </h1>
        <p class="text-md text-gray-500 mt-1">
            Menampilkan peserta untuk Kelas <span class="font-semibold capitalize text-green-600"><?php echo htmlspecialchars($guru_kelas); ?></span>
            di Kelompok <span class="font-semibold capitalize text-green-600"><?php echo htmlspecialchars($guru_kelompok); ?></span>.
        </p>
    </div>

    <!-- Tabel Data Peserta -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Kelamin</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor HP Ortu</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody id="pesertaTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($peserta_list)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-10 text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="mt-2 font-semibold">Tidak ada data peserta</p>
                            <p class="text-sm">Belum ada peserta yang terdaftar di kelas dan kelompok ini.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1;
                    foreach ($peserta_list as $peserta): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $i++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($peserta['nomor_hp'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($peserta['jenis_kelamin']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($peserta['nama_orang_tua'] ?? '-'); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($peserta['nomor_hp_orang_tua'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="detail-btn text-indigo-600 hover:text-indigo-900" data-peserta='<?php echo json_encode($peserta); ?>'>
                                    Detail
                                </button>
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
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Detail Peserta</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="font-semibold text-gray-500">Nama Lengkap</dt>
                    <dd id="detail_nama_lengkap" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Jenis Kelamin</dt>
                    <dd id="detail_jenis_kelamin" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">TTL</dt>
                    <dd id="detail_ttl" class="text-gray-900 sm:col-span-1"></dd>
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
        const tableBody = document.getElementById('pesertaTableBody');

        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        if (detailModal) {
            detailModal.addEventListener('click', (event) => {
                if (event.target === detailModal || event.target.closest('.modal-close-btn')) {
                    closeModal(detailModal);
                }
            });
        }

        if (tableBody) {
            tableBody.addEventListener('click', function(event) {
                const target = event.target.closest('button');
                if (!target || !target.classList.contains('detail-btn')) return;

                const data = JSON.parse(target.dataset.peserta);

                document.getElementById('detail_nama_lengkap').textContent = data.nama_lengkap || '-';
                document.getElementById('detail_jenis_kelamin').textContent = data.jenis_kelamin || '-';
                const ttl = (data.tempat_lahir && data.tanggal_lahir) ? `${data.tempat_lahir}, ${data.tanggal_lahir}` : '-';
                document.getElementById('detail_ttl').textContent = ttl;
                document.getElementById('detail_nomor_hp').textContent = data.nomor_hp || '-';
                document.getElementById('detail_nama_orang_tua').textContent = data.nama_orang_tua || '-';
                document.getElementById('detail_nomor_hp_orang_tua').textContent = data.nomor_hp_orang_tua || '-';

                openModal(detailModal);
            });
        }
    });
</script>