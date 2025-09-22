<?php
$success_message = '';
$error_message = '';
// ===================================================================
// BLOK PEMROSESAN DATA (CREATE, UPDATE, DELETE)
// Logika ini hanya berjalan jika ada request POST dari form
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'tambah':
            $nama = $_POST['nama_musyawarah'];
            $tanggal = $_POST['tanggal'];
            $waktu = $_POST['waktu_mulai'];
            $pimpinan = $_POST['pimpinan_rapat'];
            $tempat = $_POST['tempat'];

            $stmt = $conn->prepare("INSERT INTO musyawarah (nama_musyawarah, tanggal, waktu_mulai, pimpinan_rapat, tempat) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nama, $tanggal, $waktu, $pimpinan, $tempat);

            if ($stmt->execute()) {
                $success_message = "Musyawarah baru berhasil ditambahkan.";
            } else {
                $error_message = "Gagal menambahkan musyawarah: " . $stmt->error;
            }
            $stmt->close();
            break;

        case 'edit':
            $id = $_POST['id'];
            $nama = $_POST['nama_musyawarah'];
            $tanggal = $_POST['tanggal'];
            $waktu = $_POST['waktu_mulai'];
            $pimpinan = $_POST['pimpinan_rapat'];
            $tempat = $_POST['tempat'];

            $stmt = $conn->prepare("UPDATE musyawarah SET nama_musyawarah=?, tanggal=?, waktu_mulai=?, pimpinan_rapat=?, tempat=? WHERE id=?");
            $stmt->bind_param("sssssi", $nama, $tanggal, $waktu, $pimpinan, $tempat, $id);

            if ($stmt->execute()) {
                $success_message = "Data musyawarah berhasil diperbarui.";
            } else {
                $error_message = "Gagal memperbarui data: " . $stmt->error;
            }
            $stmt->close();
            break;

        case 'hapus':
            $id = $_POST['id'];

            $stmt = $conn->prepare("DELETE FROM musyawarah WHERE id=?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $success_message = "Data musyawarah berhasil dihapus.";
            } else {
                $error_message = "Gagal menghapus data: " . $stmt->error;
            }
            $stmt->close();
            break;

        case 'update_status':
            $id_musyawarah = $_POST['id_musyawarah'] ?? null;
            $status_baru = $_POST['status_baru'] ?? '';

            if ($id_musyawarah && ($status_baru == 'Selesai' || $status_baru == 'Dibatalkan')) {
                $sql = "UPDATE musyawarah SET status=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $status_baru, $id_musyawarah);
                if ($stmt->execute()) {
                    $success_message = "Status musyawarah berhasil diperbarui.";
                } else {
                    $error_message = "Gagal memperbarui status.";
                }
            }
            $stmt->close();
            break;
    }
}

// ===================================================================
// BLOK PENGAMBILAN DATA (READ) UNTUK DITAMPILKAN
// ===================================================================
$sql = "SELECT * FROM musyawarah ORDER BY tanggal DESC";
$result = $conn->query($sql);

?>

<!-- Di sini Anda bisa menyertakan header atau layout utama admin -->
<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">Manajemen Musyawarah</h1>

    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $success_message; ?></span></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $error_message; ?></span></div><?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-700">Daftar Musyawarah</h2>
            <button id="tombolTambah" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300">
                <i class="fas fa-plus mr-2"></i>Tambah Musyawarah
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Nama Musyawarah</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Tanggal</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Pimpinan Rapat</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Tempat</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Status</th>
                        <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4"><?= htmlspecialchars($row['nama_musyawarah']) ?></td>
                                <td class="py-3 px-4"><?= date('d F Y', strtotime($row['tanggal'])) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['pimpinan_rapat']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['tempat']) ?></td>
                                <td class="py-3 px-4">
                                    <?php
                                    $status_class = '';
                                    switch ($row['status']) {
                                        case 'Selesai':
                                            $status_class = 'bg-green-200 text-green-800';
                                            break;
                                        case 'Dibatalkan':
                                            $status_class = 'bg-red-200 text-red-800';
                                            break;
                                        default:
                                            $status_class = 'bg-yellow-200 text-yellow-800';
                                    }
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $status_class ?>"><?= $row['status'] ?></span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="grid grid-cols-1 gap-2">
                                        <?php if ($row['status'] == 'Terjadwal'): ?>
                                            <!-- Form untuk menandai Selesai -->
                                            <form action="" method="POST" class="w-full">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id_musyawarah" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="status_baru" value="Selesai">
                                                <button type="submit" class="w-full text-left flex items-center px-4 py-2 text-sm text-green-700 bg-gray-200 hover:bg-gray-300 rounded-lg">
                                                    <i class="fas fa-check-circle w-5 mr-3"></i> Tandai Selesai
                                                </button>
                                            </form>

                                            <!-- Form untuk menandai Batal -->
                                            <form action="" method="POST" class="w-full">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id_musyawarah" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="status_baru" value="Dibatalkan">
                                                <button type="submit" class="w-full text-left flex items-center px-4 py-2 text-sm text-red-700 bg-gray-200 hover:bg-gray-300 rounded-lg">
                                                    <i class="fas fa-times-circle w-5 mr-3"></i> Tandai Batal
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="?page=musyawarah/ringkasan_musyawarah&id=<?= $row['id'] ?>" class="bg-blue-500 hover:bg-blue-600 text-white font-bold p-2 rounded-lg text-xs transition duration-300" title="Catat Notulensi">
                                            <i class="fa-solid fa-eye"></i> Hasil Musyawarah
                                        </a>
                                        <a href="pages/musyawarah/cetak_notulensi?id=<?= $row['id'] ?>" target="_blank" class="bg-green-500 hover:bg-green-600 text-white font-bold p-2 rounded-lg text-xs transition duration-300" title="Catat Notulensi">
                                            <i class="fas fa-print"></i> Print Notulensi
                                        </a>
                                        <button class="tombolEdit bg-yellow-500 hover:bg-yellow-600 text-white font-bold p-2 rounded-lg text-xs transition duration-300" title="Edit"
                                            data-id="<?= $row['id'] ?>"
                                            data-nama="<?= htmlspecialchars($row['nama_musyawarah']) ?>"
                                            data-tanggal="<?= $row['tanggal'] ?>"
                                            data-waktu="<?= $row['waktu_mulai'] ?>"
                                            data-pimpinan="<?= htmlspecialchars($row['pimpinan_rapat']) ?>"
                                            data-tempat="<?= htmlspecialchars($row['tempat']) ?>">
                                            <i class="fas fa-pencil-alt"></i> Edit
                                        </button>
                                        <button class="tombolHapus bg-red-500 hover:bg-red-600 text-white font-bold p-2 rounded-lg text-xs transition duration-300" title="Hapus" data-id="<?= $row['id'] ?>">
                                            <i class="fas fa-trash-alt"></i> Hapus
                                        </button>
                                    </div>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-gray-500">Belum ada data musyawarah.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form Tambah/Edit -->
<div id="modalForm" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
        <form action="" method="POST"> <!-- action dikosongkan agar submit ke halaman ini sendiri -->
            <div class="flex justify-between items-center p-4 border-b">
                <h3 id="modalTitle" class="text-xl font-semibold text-gray-800">Tambah Musyawarah</h3>
                <button type="button" class="tombolTutupModal text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <input type="hidden" name="id" id="formId">
                <input type="hidden" name="action" id="formAction" value="tambah">

                <div class="mb-4">
                    <label for="nama_musyawarah" class="block text-gray-700 text-sm font-bold mb-2">Nama Musyawarah:</label>
                    <input type="text" id="nama_musyawarah" name="nama_musyawarah" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" required>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="tanggal" class="block text-gray-700 text-sm font-bold mb-2">Tanggal:</label>
                        <input type="date" id="tanggal" name="tanggal" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" required>
                    </div>
                    <div>
                        <label for="waktu_mulai" class="block text-gray-700 text-sm font-bold mb-2">Waktu Mulai:</label>
                        <input type="time" id="waktu_mulai" name="waktu_mulai" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="pimpinan_rapat" class="block text-gray-700 text-sm font-bold mb-2">Pimpinan Rapat:</label>
                    <input type="text" id="pimpinan_rapat" name="pimpinan_rapat" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                </div>
                <div class="mb-4">
                    <label for="tempat" class="block text-gray-700 text-sm font-bold mb-2">Tempat:</label>
                    <input type="text" id="tempat" name="tempat" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                </div>
            </div>
            <div class="flex justify-end p-4 bg-gray-50 border-t">
                <button type="button" class="tombolTutupModal border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold py-2 px-4 rounded-lg mr-2">Batal</button>
                <button type="submit" id="tombolSimpan" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div id="modalHapus" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm">
        <form action="" method="POST"> <!-- action dikosongkan agar submit ke halaman ini sendiri -->
            <div class="p-6 text-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Anda Yakin?</h3>
                <p class="text-gray-600">Data yang dihapus tidak dapat dikembalikan.</p>
                <input type="hidden" name="id" id="hapusId">
                <input type="hidden" name="action" value="hapus">
            </div>
            <div class="flex justify-center p-4 bg-gray-50 border-t">
                <button type="button" class="tombolTutupModal bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg mr-2">Batal</button>
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
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

        const modalForm = document.getElementById('modalForm');
        const modalHapus = document.getElementById('modalHapus');
        const tombolTambah = document.getElementById('tombolTambah');
        const semuaTombolTutup = document.querySelectorAll('.tombolTutupModal');

        const form = modalForm.querySelector('form');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const formId = document.getElementById('formId');
        const hapusId = document.getElementById('hapusId');

        // Fungsi untuk membuka/menutup modal
        const toggleModal = (modal, show) => {
            if (show) modal.classList.remove('hidden');
            else modal.classList.add('hidden');
        };

        // Buka modal tambah
        tombolTambah.addEventListener('click', () => {
            form.reset();
            modalTitle.textContent = 'Tambah Musyawarah';
            formAction.value = 'tambah';
            formId.value = '';
            toggleModal(modalForm, true);
        });

        // Buka modal edit
        document.querySelectorAll('.tombolEdit').forEach(button => {
            button.addEventListener('click', () => {
                modalTitle.textContent = 'Edit Musyawarah';
                formAction.value = 'edit';

                formId.value = button.dataset.id;
                document.getElementById('nama_musyawarah').value = button.dataset.nama;
                document.getElementById('tanggal').value = button.dataset.tanggal;
                document.getElementById('waktu_mulai').value = button.dataset.waktu;
                document.getElementById('pimpinan_rapat').value = button.dataset.pimpinan;
                document.getElementById('tempat').value = button.dataset.tempat;

                toggleModal(modalForm, true);
            });
        });

        // Buka modal hapus
        document.querySelectorAll('.tombolHapus').forEach(button => {
            button.addEventListener('click', () => {
                hapusId.value = button.dataset.id;
                toggleModal(modalHapus, true);
            });
        });

        // Event untuk semua tombol tutup modal
        semuaTombolTutup.forEach(button => {
            button.addEventListener('click', () => {
                toggleModal(modalForm, false);
                toggleModal(modalHapus, false);
            });
        });
    });
</script>

<?php $conn->close(); ?>
<!-- Di sini Anda bisa menyertakan footer -->