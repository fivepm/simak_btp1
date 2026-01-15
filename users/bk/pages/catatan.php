<?php
// Pastikan variabel $conn tersedia (dari index.php)
if (!isset($conn)) die("Akses dilarang.");

$bk_id = $_SESSION['user_id'];
$bk_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$bk_kelompok = $_SESSION['user_kelompok'] ?? '';

// === LOGIKA PENCARIAN & VIEW DATA (GET REQUEST SAJA) ===
$selected_peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : null;
$search_query = $_GET['cari'] ?? '';

if (!$selected_peserta_id && !empty($search_query)) {
    $sql_search = "SELECT id FROM peserta WHERE nama_lengkap LIKE ? AND status='Aktif'";
    if ($bk_tingkat === 'kelompok') $sql_search .= " AND kelompok = '$bk_kelompok'";
    $stmt_search = $conn->prepare($sql_search);
    $param_search = "%" . $search_query . "%";
    $stmt_search->bind_param("s", $param_search);
    $stmt_search->execute();
    $result_search = $stmt_search->get_result();
    if ($result_search->num_rows > 0) {
        $found_student = $result_search->fetch_assoc();
        $selected_peserta_id = $found_student['id'];
    }
}

$siswa = null;
if ($selected_peserta_id) {
    $sql_siswa = "SELECT * FROM peserta WHERE id = ? AND status='Aktif'";
    if ($bk_tingkat === 'kelompok') $sql_siswa .= " AND kelompok = ?";
    $stmt_siswa = $conn->prepare($sql_siswa);
    if ($bk_tingkat === 'kelompok') $stmt_siswa->bind_param("is", $selected_peserta_id, $bk_kelompok);
    else $stmt_siswa->bind_param("i", $selected_peserta_id);
    $stmt_siswa->execute();
    $siswa = $stmt_siswa->get_result()->fetch_assoc();
    if (!$siswa) $selected_peserta_id = null;
}

// QUERY LIST & CATATAN
$peserta_list = [];
$sql_list = "SELECT id, nama_lengkap, kelas, kelompok FROM peserta WHERE status='Aktif' AND status='Aktif'";
if ($bk_tingkat === 'kelompok') $sql_list .= " AND kelompok = '$bk_kelompok'";
$sql_list .= " ORDER BY nama_lengkap ASC";
$res_list = $conn->query($sql_list);
while ($r = $res_list->fetch_assoc()) $peserta_list[] = $r;

$catatan_list = [];
if ($selected_peserta_id) {
    $sql_c = "SELECT cb.*, u.nama as nama_pencatat FROM catatan_bk cb LEFT JOIN users u ON cb.dicatat_oleh_user_id = u.id WHERE cb.peserta_id = ? ORDER BY cb.tanggal_catatan DESC, cb.created_at DESC";
    $stmt_c = $conn->prepare($sql_c);
    $stmt_c->bind_param("i", $selected_peserta_id);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();
} else {
    $sql_c = "SELECT cb.*, u.nama as nama_pencatat, p.nama_lengkap, p.kelas, p.kelompok, p.id as real_peserta_id FROM catatan_bk cb JOIN peserta p ON cb.peserta_id = p.id LEFT JOIN users u ON cb.dicatat_oleh_user_id = u.id WHERE p.status='Aktif'";
    if ($bk_tingkat === 'kelompok') $sql_c .= " AND p.kelompok = '$bk_kelompok'";
    $sql_c .= " ORDER BY cb.tanggal_catatan DESC, cb.created_at DESC LIMIT 50";
    $res_c = $conn->query($sql_c);
}
if ($res_c) while ($row = $res_c->fetch_assoc()) $catatan_list[] = $row;
?>

<div class="space-y-6">
    <!-- HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 border-b pb-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Buku Konseling</h2>
            <p class="text-sm text-gray-500">
                <?php echo ($selected_peserta_id && $siswa) ? 'Menampilkan riwayat: <b>' . htmlspecialchars($siswa['nama_lengkap']) . '</b>' : 'Menampilkan aktivitas terbaru (Timeline)'; ?>
            </p>
        </div>
        <div class="w-full md:w-1/3">
            <form method="GET" action="">
                <input type="hidden" name="page" value="catatan">
                <select name="peserta_id" onchange="this.form.submit()" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">-- Cari / Pilih Siswa --</option>
                    <?php foreach ($peserta_list as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($selected_peserta_id == $p['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['nama_lengkap']); ?> (<?php echo $p['kelas']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <?php if ($selected_peserta_id && $siswa): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6 sticky top-4 text-center">
                    <div class="h-24 w-24 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-3xl font-bold mx-auto mb-4">
                        <?php echo strtoupper(substr($siswa['nama_lengkap'], 0, 1)); ?>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></h3>
                    <p class="text-gray-500 mb-6"><?php echo ucfirst($siswa['kelas']) . ' - ' . ucfirst($siswa['kelompok']); ?></p>
                    <button id="btnTambahCatatan" class="w-full bg-indigo-600 text-white font-bold py-2 px-4 rounded hover:bg-indigo-700 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-plus"></i> Tambah Catatan
                    </button>
                    <a href="?page=catatan" class="block mt-3 text-sm text-gray-500 underline hover:text-indigo-600">&larr; Kembali ke Buku Konseling</a>
                </div>
            </div>
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow p-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Riwayat Kasus</h3>
                    <?php if (empty($catatan_list)): ?>
                        <p class="text-center text-gray-400 py-4">Belum ada catatan.</p>
                        <?php else: foreach ($catatan_list as $c): $json = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8'); ?>
                            <div class="border rounded-lg p-4 bg-white hover:shadow-sm group relative">
                                <div class="flex justify-between mb-2">
                                    <span class="font-semibold text-gray-700 text-sm"><?php echo date('d M Y', strtotime($c['tanggal_catatan'])); ?></span>
                                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button class="text-blue-600 btn-edit" data-json="<?php echo $json; ?>"><i class="fa-solid fa-pen"></i></button>
                                        <button class="text-red-600 btn-hapus" data-id="<?php echo $c['id']; ?>"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                                <p class="text-gray-800 mb-2"><?php echo htmlspecialchars($c['permasalahan']); ?></p>
                                <div class="bg-gray-50 p-3 rounded border-l-4 <?php echo $c['tindak_lanjut'] ? 'border-green-500' : 'border-yellow-400'; ?>">
                                    <span class="block font-bold text-xs text-gray-400 uppercase">Tindak Lanjut</span>
                                    <?php if ($c['tindak_lanjut']): echo htmlspecialchars($c['tindak_lanjut']);
                                    else: ?>
                                        <button class="text-indigo-600 text-sm hover:underline btn-tindak-lanjut" data-json="<?php echo $json; ?>">+ Isi Tindak Lanjut</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- TIMELINE VIEW -->
        <div class="bg-white rounded-lg shadow p-6 space-y-4">
            <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Timeline Aktivitas</h3>
            <?php if (empty($catatan_list)): ?>
                <p class="text-center text-gray-400 py-10">Belum ada data.</p>
                <?php else: foreach ($catatan_list as $c):
                    if (isset($c['real_peserta_id'])) $c['peserta_id'] = $c['real_peserta_id'];
                    $json = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8'); ?>
                    <div class="border p-4 rounded-lg hover:shadow-md">
                        <div class="flex justify-between mb-2">
                            <div>
                                <span class="font-bold text-indigo-700"><?php echo htmlspecialchars($c['nama_lengkap']); ?></span>
                                <span class="text-xs bg-gray-100 px-2 py-1 rounded ml-2"><?php echo $c['kelas']; ?></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php echo date('d M Y', strtotime($c['tanggal_catatan'])); ?></span>
                        </div>
                        <p class="text-gray-800 mb-2"><?php echo htmlspecialchars($c['permasalahan']); ?></p>
                        <div class="flex justify-between pt-2 border-t mt-2">
                            <small class="text-gray-400">Oleh: <?php echo htmlspecialchars($c['nama_pencatat']); ?></small>
                            <button class="text-xs font-semibold text-indigo-600 btn-edit" data-json="<?php echo $json; ?>">Detail / Edit</button>
                        </div>
                    </div>
            <?php endforeach;
            endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL FORM -->
<div id="modalForm" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 opacity-75" onclick="closeModal()"></div>
        <div class="bg-white rounded-lg w-full max-w-lg z-10 overflow-hidden shadow-xl">
            <form id="formCatatan" method="POST">
                <!-- Action di JS akan mengarah ke proses_catatan.php -->
                <input type="hidden" name="action" id="formAction" value="tambah_catatan">
                <input type="hidden" name="catatan_id" id="inputId">
                <input type="hidden" name="peserta_id" id="inputPesertaId" value="<?php echo $selected_peserta_id; ?>">

                <div class="p-6 space-y-4">
                    <h3 class="text-lg font-medium" id="modalTitle">Tambah Catatan</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                        <input type="date" name="tanggal_catatan" id="inputTanggal" class="w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Permasalahan</label>
                        <textarea name="permasalahan" id="inputMasalah" rows="3" class="w-full border-gray-300 rounded-md shadow-sm" required></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tindak Lanjut</label>
                        <textarea name="tindak_lanjut" id="inputTL" rows="2" class="w-full border-gray-300 rounded-md shadow-sm" placeholder="Opsional..."></textarea>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-2">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">Simpan</button>
                    <button type="button" onclick="closeModal()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // --- HILANGKAN LOADING SPINNER DARI INDEX.PHP ---
    // Script ini memastikan loading overlay bawaan index (jika ada) disembunyikan
    // agar tidak menutupi konten halaman ini.
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.remove('show');
    }

    const modal = document.getElementById('modalForm');

    function openModal() {
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    // === HANDLE FORM SUBMIT VIA AJAX ===
    document.getElementById('formCatatan').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        // Tampilkan loading dari SweetAlert
        Swal.fire({
            title: 'Menyimpan...',
            didOpen: () => Swal.showLoading()
        });

        // Fetch ke file proses terpisah
        fetch('pages/proses_catatan.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Jika error server (bukan JSON)
                    throw new Error('Server error: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: data.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = data.redirect;
                    });
                } else {
                    Swal.fire('Gagal!', data.message || 'Terjadi kesalahan.', 'error');
                }
            })
            .catch(error => {
                console.error(error);
                Swal.fire('Error!', 'Gagal menghubungi server. Cek console untuk detail.', 'error');
            });
    });

    // === BUTTON LISTENERS (SAMA SEPERTI SEBELUMNYA) ===
    const btnAdd = document.getElementById('btnTambahCatatan');
    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            document.getElementById('formCatatan').reset();
            document.getElementById('formAction').value = 'tambah_catatan';
            document.getElementById('modalTitle').textContent = 'Tambah Catatan Baru';
            document.getElementById('inputId').value = '';
            document.getElementById('inputPesertaId').value = '<?php echo $selected_peserta_id; ?>';
            document.getElementById('inputTanggal').value = new Date().toISOString().split('T')[0];
            document.getElementById('inputMasalah').readOnly = false;
            openModal();
        });
    }

    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.json);
            document.getElementById('formAction').value = 'edit_catatan';
            document.getElementById('modalTitle').textContent = 'Edit Catatan';
            document.getElementById('inputId').value = data.id;
            document.getElementById('inputPesertaId').value = data.peserta_id;
            document.getElementById('inputTanggal').value = data.tanggal_catatan;
            document.getElementById('inputMasalah').value = data.permasalahan;
            document.getElementById('inputTL').value = data.tindak_lanjut;
            document.getElementById('inputMasalah').readOnly = false;
            openModal();
        });
    });

    document.querySelectorAll('.btn-tindak-lanjut').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.json);
            document.getElementById('formAction').value = 'edit_catatan';
            document.getElementById('modalTitle').textContent = 'Isi Tindak Lanjut';
            document.getElementById('inputId').value = data.id;
            document.getElementById('inputPesertaId').value = data.peserta_id;
            document.getElementById('inputTanggal').value = data.tanggal_catatan;
            document.getElementById('inputMasalah').value = data.permasalahan;
            document.getElementById('inputTL').value = '';
            document.getElementById('inputMasalah').readOnly = true;
            openModal();
        });
    });

    // === DELETE VIA AJAX KE FILE PROSES ===
    document.querySelectorAll('.btn-hapus').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            Swal.fire({
                title: 'Hapus Catatan?',
                text: "Data tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'hapus_catatan');
                    formData.append('hapus_id', id);
                    formData.append('peserta_id', '<?php echo $selected_peserta_id; ?>');

                    // PERUBAHAN PENTING: Fetch ke file proses terpisah
                    fetch('pages/proses_catatan.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    title: 'Terhapus!',
                                    text: data.message,
                                    icon: 'success',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.href = data.redirect;
                                });
                            } else {
                                Swal.fire('Gagal!', data.message, 'error');
                            }
                        })
                        .catch(err => Swal.fire('Error', 'Terjadi kesalahan koneksi', 'error'));
                }
            })
        });
    });
</script>