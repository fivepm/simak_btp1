<?php
// ===================================================================
// BAGIAN 1: PROSESOR KHUSUS UNTUK AJAX (Diletakkan di paling atas)
// ===================================================================
// BLOK INI SEKARANG DIHAPUS KARENA SUDAH DIPINDAHKAN KE update_status.php

// ===================================================================
// BAGIAN 2: LOGIKA UNTUK PEMUATAN HALAMAN NORMAL
// ===================================================================
// Script di bawah ini hanya akan berjalan jika BUKAN request AJAX 'update_status'.

$redirect_url = null;
$success_message = '';
$error_message = '';
$id_musyawarah = $_GET['id'] ?? null;

// Keamanan: Pastikan ID Musyawarah valid untuk pemuatan halaman
if (!$id_musyawarah || !filter_var($id_musyawarah, FILTER_VALIDATE_INT)) {
    header('Location: ?page=musyawarah/daftar_musyawarah&status=error&msg=' . urlencode('ID Musyawarah tidak valid.'));
    exit();
}

// Proses form POST biasa (tambah/hapus peserta)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Aksi untuk menambah peserta baru
    if ($action === 'tambah_peserta') {
        $nama_peserta = trim($_POST['nama_peserta'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');

        if (!empty($nama_peserta)) {
            $stmt = $conn->prepare("INSERT INTO kehadiran_musyawarah (id_musyawarah, nama_peserta, jabatan) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $id_musyawarah, $nama_peserta, $jabatan);
            if ($stmt->execute()) {
                $success_message = "Peserta berhasil ditambahkan.";
            } else {
                $error_message = "Gagal menambahkan peserta.";
            }
            $stmt->close();
        } else {
            $error_message = "Nama peserta tidak boleh kosong.";
        }
    }
    // Aksi untuk menghapus peserta
    elseif ($action === 'hapus_peserta') {
        $id_kehadiran = $_POST['id_kehadiran'] ?? null;
        if ($id_kehadiran) {
            $stmt = $conn->prepare("DELETE FROM kehadiran_musyawarah WHERE id = ? AND id_musyawarah = ?");
            $stmt->bind_param("ii", $id_kehadiran, $id_musyawarah);
            if ($stmt->execute()) {
                $success_message = "Peserta berhasil dihapus.";
            } else {
                $error_message = "Gagal menghapus peserta.";
            }
            $stmt->close();
        }
    }
}

// Ambil data untuk ditampilkan di halaman
$stmt_musyawarah = $conn->prepare("SELECT nama_musyawarah, tanggal FROM musyawarah WHERE id = ?");
$stmt_musyawarah->bind_param("i", $id_musyawarah);
$stmt_musyawarah->execute();
$musyawarah = $stmt_musyawarah->get_result()->fetch_assoc();
$stmt_musyawarah->close();

$stmt_peserta = $conn->prepare("SELECT * FROM kehadiran_musyawarah WHERE id_musyawarah = ? ORDER BY urutan ASC, nama_peserta ASC");
$stmt_peserta->bind_param("i", $id_musyawarah);
$stmt_peserta->execute();
$daftar_peserta = $stmt_peserta->get_result();
$stmt_peserta->close();

$result_pengurus = $conn->query("SELECT nama_pengurus, jabatan FROM kepengurusan ORDER BY nama_pengurus ASC");
?>

<!-- (Sisa kode HTML dari file sebelumnya diletakkan di sini, tidak ada perubahan) -->
<div class="container mx-auto p-4 sm:p-6">
    <!-- Header Halaman -->
    <div class="mb-6">
        <a href="?page=musyawarah/daftar_kehadiran" class="text-cyan-600 hover:text-cyan-800 transition-colors">
            &larr; Kembali ke Daftar Kehadiran Musyawarah
        </a>
        <h1 class="text-3xl font-bold text-gray-800 mt-2">Daftar Hadir Musyawarah</h1>
        <p class="text-gray-600">
            <?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?> -
            <span class="font-medium"><?php echo date('d F Y', strtotime($musyawarah['tanggal'])); ?></span>
        </p>
    </div>

    <!-- Notifikasi -->
    <?php if (!empty($success_message)): ?>
        <div id="success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-lg" role="alert">
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div id="error-alert" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-lg" role="alert">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Form Tambah Peserta -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Tambah Peserta</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="tambah_peserta">
                    <div class="space-y-4">
                        <div>
                            <label for="nama_peserta" class="block text-sm font-medium text-gray-700">Nama Peserta</label>
                            <input list="daftar-pengurus" id="nama_peserta" name="nama_peserta" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500" placeholder="Ketik atau pilih nama..." required>
                            <datalist id="daftar-pengurus">
                                <?php if ($result_pengurus->num_rows > 0): ?>
                                    <?php while ($pengurus = $result_pengurus->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($pengurus['nama_pengurus']); ?>">
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                            </datalist>
                        </div>
                        <div>
                            <label for="jabatan" class="block text-sm font-medium text-gray-700">Dapukan</label>
                            <input type="text" id="jabatan" name="jabatan" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="Dapukan peserta (opsional)">
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300">
                            <i class="fas fa-plus mr-2"></i> Tambahkan ke Daftar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Kolom Kanan: Tabel Daftar Hadir -->
        <div class="lg:col-span-2">
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Peserta Terdaftar</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">#</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">No</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Nama Peserta</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Dapukan</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Status</th>
                                <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700" id="sortable-list">
                            <?php if ($daftar_peserta->num_rows > 0): ?>
                                <?php $nomor = 1; ?>
                                <?php while ($peserta = $daftar_peserta->fetch_assoc()): ?>
                                    <tr class="border-b" data-id="<?php echo $peserta['id']; ?>">
                                        <td class="py-3 px-2 text-center text-gray-400 cursor-move drag-handle">
                                            <i class="fas fa-grip-vertical"></i>
                                        </td>
                                        <td class="py-3 px-4"><?php echo $nomor++; ?></td>
                                        <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($peserta['nama_peserta']); ?></td>
                                        <td class="py-3 px-4 text-sm text-gray-500"><?php echo htmlspecialchars($peserta['jabatan']); ?></td>
                                        <td class="py-3 px-4">
                                            <select class="status-kehadiran border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500" data-id="<?php echo $peserta['id']; ?>">
                                                <option value="Tanpa Keterangan" <?php if ($peserta['status'] == 'Tanpa Keterangan') echo 'selected'; ?>>Tanpa Keterangan</option>
                                                <option value="Hadir" <?php if ($peserta['status'] == 'Hadir') echo 'selected'; ?>>Hadir</option>
                                                <option value="Izin" <?php if ($peserta['status'] == 'Izin') echo 'selected'; ?>>Izin</option>
                                            </select>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <form method="POST" action="" onsubmit="return confirm('Anda yakin ingin menghapus peserta ini?');">
                                                <input type="hidden" name="action" value="hapus_peserta">
                                                <input type="hidden" name="id_kehadiran" value="<?php echo $peserta['id']; ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700" title="Hapus Peserta">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-gray-500">Belum ada peserta yang ditambahkan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="toast-notification" class="fixed bottom-5 right-5 bg-gray-800 text-white py-2 px-4 rounded-lg shadow-lg transition-opacity duration-300 opacity-0 z-50">
    <span id="toast-message"></span>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- SCRIPT UNTUK UPDATE STATUS KEHADIRAN VIA AJAX ---
        document.querySelectorAll('.status-kehadiran').forEach(select => {
            select.addEventListener('change', function() {
                const id_kehadiran = this.dataset.id;
                const status_baru = this.value;
                // Pastikan URL ini sesuai dengan struktur routing Anda.
                // URL ini mengirim request ke halaman ini sendiri.
                const url = `pages/musyawarah/update_status.php?id=<?php echo $id_musyawarah; ?>`;

                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('id_kehadiran', id_kehadiran);
                formData.append('status', status_baru);

                fetch(url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            // Coba baca pesan error dari server jika ada
                            return response.json().then(errorData => {
                                throw new Error(errorData.message || 'Server responded with an error!');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            tampilkanToast('Status berhasil disimpan!', 'success');
                        } else {
                            tampilkanToast(data.message || 'Gagal menyimpan status.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch Error:', error);
                        tampilkanToast(error.message || 'Gagal memproses permintaan.', 'error');
                    });
            });
        });

        // --- FUNGSI UNTUK NOTIFIKASI TOAST ---
        const toastElement = document.getElementById('toast-notification');
        const toastMessage = document.getElementById('toast-message');

        function tampilkanToast(message, type = 'success') {
            toastMessage.textContent = message;

            if (type === 'success') {
                toastElement.classList.remove('bg-red-600');
                toastElement.classList.add('bg-green-600');
            } else {
                toastElement.classList.remove('bg-green-600');
                toastElement.classList.add('bg-red-600');
            }

            toastElement.classList.remove('opacity-0');

            setTimeout(() => {
                toastElement.classList.add('opacity-0');
            }, 3000);
        }

        const autoHideAlert = (alertId) => {
            const alertElement = document.getElementById(alertId);
            if (alertElement) {
                setTimeout(() => {
                    alertElement.style.transition = 'opacity 0.5s ease';
                    alertElement.style.opacity = '0';
                    setTimeout(() => {
                        alertElement.style.display = 'none';
                    }, 500); // Waktu untuk animasi fade-out
                }, 3000); // 3000 milidetik = 3 detik
            }
        };
        autoHideAlert('success-alert');
        autoHideAlert('error-alert');

        //FUNGSI URUTAN PESERTA
        const sortableList = document.getElementById('sortable-list');
        const id_musyawarah = <?php echo $id_musyawarah; ?>;

        if (sortableList) {
            new Sortable(sortableList, {
                handle: '.drag-handle', // Tentukan elemen mana yang bisa di-drag
                animation: 150, // Animasi saat di-drag
                onEnd: function(evt) {
                    // Fungsi ini berjalan setelah user selesai men-drag
                    const urutanPeserta = [];
                    const rows = sortableList.querySelectorAll('tr');

                    // Loop melalui setiap baris untuk memperbarui nomor urut
                    rows.forEach((row, index) => {
                        // Cari kolom pertama (kolom nomor) di dalam baris
                        const nomorCell = row.querySelector('td:nth-child(2)');

                        // Cek jika kolomnya ada, lalu update isinya
                        if (nomorCell) {
                            // Update teks di dalamnya menjadi nomor urut yang baru (index + 1)
                            nomorCell.textContent = index + 1;
                        }
                    });

                    rows.forEach(row => {
                        urutanPeserta.push(row.dataset.id);
                    });

                    // Kirim urutan baru ke server
                    fetch('pages/musyawarah/simpan_urutan.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id_musyawarah: id_musyawarah,
                                urutan_ids: urutanPeserta
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                tampilkanToast('Urutan berhasil disimpan!');
                            } else {
                                tampilkanToast(data.message || 'Gagal menyimpan urutan.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            tampilkanToast('Terjadi kesalahan jaringan.', 'error');
                        });
                }
            });
        }
    });
</script>

<?php $conn->close(); ?>