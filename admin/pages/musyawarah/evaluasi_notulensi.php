<?php
// ===================================================================
// BAGIAN 1: PROSESOR KHUSUS UNTUK PERMINTAAN AJAX
// BLOK INI TELAH DIHAPUS DAN DIPINDAHKAN KE update_evaluasi.php
// ===================================================================


// ===================================================================
// BAGIAN 2: LOGIKA UNTUK PEMUATAN HALAMAN NORMAL
// ===================================================================
$id_musyawarah = $_GET['id'] ?? null;

// Keamanan: Pastikan ID Musyawarah valid
if (!$id_musyawarah || !filter_var($id_musyawarah, FILTER_VALIDATE_INT)) {
    header('Location: ?page=musyawarah/daftar_musyawarah&status=error&msg=' . urlencode('ID Musyawarah tidak valid.'));
    exit();
}

// Ambil detail musyawarah
$stmt_musyawarah = $conn->prepare("SELECT nama_musyawarah, tanggal FROM musyawarah WHERE id = ?");
$stmt_musyawarah->bind_param("i", $id_musyawarah);
$stmt_musyawarah->execute();
$musyawarah = $stmt_musyawarah->get_result()->fetch_assoc();
$stmt_musyawarah->close();

if (!$musyawarah) {
    header('Location: ?page=musyawarah/daftar_musyawarah&status=error&msg=' . urlencode('ID Musyawarah tidak valid.'));
    exit();
}

// Ambil semua poin notulensi untuk musyawarah ini
$stmt_poin = $conn->prepare("SELECT * FROM notulensi_poin WHERE id_musyawarah = ? ORDER BY id ASC");
$stmt_poin->bind_param("i", $id_musyawarah);
$stmt_poin->execute();
$result_poin = $stmt_poin->get_result();
$stmt_poin->close();

?>

<!-- Mulai Halaman HTML -->

<!-- BARU: Blok Style untuk memastikan class tidak di-purge oleh Tailwind -->
<style>
    .status-btn.is-active.is-terlaksana {
        color: #16a34a;
        /* setara dengan text-green-600 */
        background-color: #dcfce7;
        /* setara dengan bg-green-100 */
    }

    .status-btn.is-active.is-belum-terlaksana {
        color: #dc2626;
        /* setara dengan text-red-600 */
        background-color: #fee2e2;
        /* setara dengan bg-red-100 */
    }
</style>

<div class="container mx-auto p-4 sm:p-6">

    <!-- Header Halaman -->
    <div class="mb-6">
        <a href="?page=musyawarah/daftar_notulensi" class="text-cyan-600 hover:text-cyan-800 transition-colors">
            &larr; Kembali ke Daftar Musyawarah
        </a>
        <h1 class="text-3xl font-bold text-gray-800 mt-2">Evaluasi Tindak Lanjut Notulensi</h1>
        <p class="text-gray-600">
            Musyawarah: <span class="font-semibold"><?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?></span>
            (<?php echo date('d F Y', strtotime($musyawarah['tanggal'])); ?>)
        </p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 w-12 text-left text-sm font-semibold text-gray-600 uppercase">#</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Poin Pembahasan</th>
                        <th class="py-3 px-4 w-48 text-center text-sm font-semibold text-gray-600 uppercase">Status Pelaksanaan</th>
                        <th class="py-3 px-4 w-1/3 text-left text-sm font-semibold text-gray-600 uppercase">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if ($result_poin->num_rows > 0): ?>
                        <?php $nomor = 1; ?>
                        <?php while ($poin = $result_poin->fetch_assoc()): ?>
                            <tr class="border-b" data-poin-id="<?php echo $poin['id']; ?>">
                                <td class="py-3 px-4 align-top"><?php echo $nomor++; ?>.</td>
                                <td class="py-3 px-4 align-top">
                                    <?php echo nl2br(htmlspecialchars($poin['poin_pembahasan'])); ?>
                                </td>
                                <td class="py-3 px-4 align-top">
                                    <div class="flex justify-center items-center gap-4">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status_<?php echo $poin['id']; ?>" value="Terlaksana" class="status-radio sr-only" <?php echo ($poin['status_evaluasi'] == 'Terlaksana') ? 'checked' : ''; ?>>
                                            <div class="status-btn text-gray-400 hover:text-green-600 p-2 rounded-full <?php echo ($poin['status_evaluasi'] == 'Terlaksana') ? 'is-active is-terlaksana' : ''; ?>" title="Terlaksana">
                                                <i class="fas fa-check-circle fa-2x"></i>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status_<?php echo $poin['id']; ?>" value="Belum Terlaksana" class="status-radio sr-only" <?php echo ($poin['status_evaluasi'] == 'Belum Terlaksana') ? 'checked' : ''; ?>>
                                            <div class="status-btn text-gray-400 hover:text-red-600 p-2 rounded-full <?php echo ($poin['status_evaluasi'] == 'Belum Terlaksana') ? 'is-active is-belum-terlaksana' : ''; ?>" title="Belum Terlaksana">
                                                <i class="fas fa-times-circle fa-2x"></i>
                                            </div>
                                        </label>
                                    </div>
                                </td>
                                <td class="py-3 px-4 align-top">
                                    <textarea class="keterangan-textarea w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500 transition-all" rows="3" placeholder="Beri keterangan jika belum terlaksana..."
                                        <?php if ($poin['status_evaluasi'] == 'Terlaksana') echo 'readonly'; ?>><?php echo ($poin['keterangan'] != NULL) ? htmlspecialchars($poin['keterangan']) : ''; ?></textarea>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-6 text-gray-500">
                                Belum ada poin notulensi yang dicatat untuk musyawarah ini. <br>
                                <a href="?page=musyawarah/catat_notulensi&id=<?php echo $id_musyawarah; ?>" class="text-cyan-600 hover:underline mt-2 inline-block">Catat Poin Sekarang</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Elemen Notifikasi Toast untuk AJAX -->
<div id="toast-notification" class="fixed bottom-5 right-5 bg-gray-800 text-white py-2 px-4 rounded-lg shadow-lg transition-transform duration-300 transform translate-x-[120%] z-50">
    <span id="toast-message"></span>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const id_musyawarah = <?php echo $id_musyawarah; ?>;
        let saveTimeout;

        // Fungsi untuk menyimpan data via AJAX
        function saveEvaluation(rowElement) {
            const id_poin = rowElement.dataset.poinId;
            const statusRadio = rowElement.querySelector('.status-radio:checked');
            const status_evaluasi = statusRadio ? statusRadio.value : 'Belum Dievaluasi';
            const keteranganTextarea = rowElement.querySelector('.keterangan-textarea');
            const keterangan = keteranganTextarea.value;

            const formData = new FormData();
            formData.append('id_poin', id_poin); // 'action' tidak perlu lagi karena file sudah spesifik
            formData.append('status_evaluasi', status_evaluasi);
            formData.append('keterangan', keterangan);

            // PERUBAHAN DI SINI: URL Fetch menunjuk ke file baru
            // Pastikan path ini benar sesuai struktur folder Anda dari file index.php utama
            const url = `pages/musyawarah/update_evaluasi.php?id=<?php echo $id_musyawarah; ?>`;

            fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        // Coba untuk mendapatkan pesan error dari body respons
                        return response.json().then(errorData => {
                            throw new Error(errorData.message || 'Server responded with an error!');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        tampilkanToast('Perubahan disimpan!');
                    } else {
                        tampilkanToast(data.message || 'Gagal menyimpan.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    tampilkanToast(error.message || 'Terjadi kesalahan jaringan.', 'error');
                });
        }

        // Event listener untuk tombol radio status
        // --- LOGIKA UI YANG DIPERBARUI ---
        document.querySelectorAll('.status-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                const row = this.closest('tr');
                const textarea = row.querySelector('.keterangan-textarea');
                const greenBtnDiv = row.querySelector('input[value="Terlaksana"]').nextElementSibling;
                const redBtnDiv = row.querySelector('input[value="Belum Terlaksana"]').nextElementSibling;

                // 1. Reset class custom dari kedua tombol
                greenBtnDiv.classList.remove('is-active', 'is-terlaksana');
                redBtnDiv.classList.remove('is-active', 'is-belum-terlaksana');

                // 2. Terapkan style dan logika berdasarkan tombol yang dipilih
                if (this.value === 'Terlaksana') {
                    textarea.value = 'Sudah Terlaksana';
                    textarea.readOnly = true;
                    greenBtnDiv.classList.add('is-active', 'is-terlaksana');
                } else if (this.value === 'Belum Terlaksana') {
                    // Hanya kosongkan jika isinya default, jangan hapus keterangan custom
                    if (textarea.value === 'Sudah Terlaksana') {
                        textarea.value = '';
                    }
                    textarea.readOnly = false;
                    redBtnDiv.classList.add('is-active', 'is-belum-terlaksana');
                    textarea.focus();
                }

                // 3. Simpan perubahan
                saveEvaluation(row);
            });
        });

        // Event listener untuk textarea keterangan
        document.querySelectorAll('.keterangan-textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                const row = this.closest('tr');
                saveTimeout = setTimeout(() => {
                    saveEvaluation(row);
                }, 1000); // Simpan setelah 1 detik tidak mengetik
            });
        });

        // --- FUNGSI UNTUK NOTIFIKASI TOAST ---
        const toastElement = document.getElementById('toast-notification');
        const toastMessage = document.getElementById('toast-message');

        function tampilkanToast(message, type = 'success') {
            toastMessage.textContent = message;
            toastElement.classList.remove('bg-red-600', 'bg-gray-800');

            if (type === 'success') {
                toastElement.classList.add('bg-gray-800');
            } else {
                toastElement.classList.add('bg-red-600');
            }

            toastElement.classList.remove('translate-x-[120%]');

            setTimeout(() => {
                toastElement.classList.add('translate-x-[120%]');
            }, 3000);
        }
    });
</script>

<?php $conn->close(); ?>