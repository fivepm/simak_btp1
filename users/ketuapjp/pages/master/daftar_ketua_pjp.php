<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$redirect_url = ''; // Variabel untuk menyimpan URL redirect

// === AMBIL DATA UNTUK DITAMPILKAN ===
$pjp_users = [];
$sql = "SELECT id, nama, username, kelompok, tingkat, role, barcode FROM users WHERE role = 'ketua pjp' ORDER BY nama ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pjp_users[] = $row;
    }
}

// === TAMPILAN HTML ===
?>
<div class="container mx-auto">
    <!-- Header Halaman -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Daftar Ketua PJP</h3>
    </div>

    <!-- Tabel Data -->
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tingkat</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelompok</th>
                </tr>
            </thead>
            <tbody id="pjpTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($pjp_users)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">Belum ada data Ketua PJP.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1;
                    foreach ($pjp_users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $i++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize"><?php echo htmlspecialchars($user['nama']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize"><?php echo htmlspecialchars($user['tingkat']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize"><?php echo htmlspecialchars($user['kelompok']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal QR Code -->
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
        // --- JavaScript Redirect ---
        <?php if (!empty($redirect_url)): ?>
            window.location.href = '<?php echo $redirect_url; ?>';
        <?php endif; ?>

        // --- Modal Controls ---
        const modals = document.querySelectorAll('.fixed.z-20');
        const openModalButtons = {
            'tambahPjpBtn': 'tambahPjpModal',
        };
        const tableBody = document.getElementById('pjpTableBody');

        const openModal = (modalId) => {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.remove('hidden');
        }
        const closeModal = (modal) => {
            if (modal) modal.classList.add('hidden');
        }

        for (const [btnId, modalId] of Object.entries(openModalButtons)) {
            const btn = document.getElementById(btnId);
            if (btn) btn.onclick = () => openModal(modalId);
        }

        modals.forEach(modal => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal || event.target.closest('.modal-close-btn')) {
                    closeModal(modal);
                }
            });
        });

        // --- Dynamic Button Listeners ---
        if (tableBody) {
            tableBody.addEventListener('click', function(event) {
                const target = event.target;

                if (target.classList.contains('qr-code-btn')) {
                    openModal('qrCodeModal');
                    const container = document.getElementById('qrcode-container');
                    container.innerHTML = '';
                    document.getElementById('qr_nama').textContent = target.dataset.nama;
                    new QRCode(container, {
                        text: target.dataset.barcode,
                        width: 200,
                        height: 200
                    });
                    setTimeout(() => {
                        const canvas = container.querySelector('canvas');
                        if (canvas) {
                            const downloadLink = document.getElementById('download-qr-link');
                            downloadLink.href = canvas.toDataURL("image/png");
                            downloadLink.download = `qrcode-${target.dataset.nama.replace(/\s+/g, '-')}.png`;
                        }
                    }, 100);
                }
            });
        }
    });
</script>