<?php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
$jadwal_id = isset($_GET['jadwal_id']) ? (int)$_GET['jadwal_id'] : 0;

if ($jadwal_id === 0) {
    echo '<div class="bg-red-100 border-red-400 text-red-700 p-4 rounded-lg">ID Jadwal tidak valid.</div>';
    return;
}

// Ambil data jadwal
$jadwal = $conn->query("SELECT * FROM jadwal_presensi WHERE id = $jadwal_id")->fetch_assoc();
$back_url = '?page=jadwal&periode_id=' . ($jadwal['periode_id'] ?? '') . '&kelompok=' . ($jadwal['kelompok'] ?? '') . '&kelas=' . ($jadwal['kelas'] ?? '');

// Ambil data peserta presensi
$peserta_presensi = [];
$sql_presensi = "SELECT rp.id, rp.status_kehadiran, rp.keterangan, p.nama_lengkap, p.nomor_hp_orang_tua, rp.kirim_wa 
                 FROM rekap_presensi rp 
                 JOIN peserta p ON rp.peserta_id = p.id 
                 WHERE rp.jadwal_id = ? 
                 ORDER BY p.nama_lengkap ASC";
$stmt_presensi = $conn->prepare($sql_presensi);
$stmt_presensi->bind_param("i", $jadwal_id);
$stmt_presensi->execute();
$result_presensi = $stmt_presensi->get_result();
if ($result_presensi) {
    while ($row = $result_presensi->fetch_assoc()) {
        $peserta_presensi[] = $row;
    }
}
?>

<!-- OVERLAY LOADING KHUSUS HALAMAN INI -->
<div id="loadingOverlay" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 hidden backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white p-8 rounded-2xl shadow-2xl text-center max-w-sm w-full transform scale-100 transition-transform duration-300">
        <!-- Spinner Keren -->
        <div class="relative w-20 h-20 mx-auto mb-6">
            <div class="absolute inset-0 border-4 border-indigo-200 rounded-full animate-pulse"></div>
            <div class="absolute inset-0 border-t-4 border-indigo-600 rounded-full animate-spin"></div>
            <div class="absolute inset-4 bg-indigo-50 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-cloud-arrow-up text-2xl text-indigo-600"></i>
            </div>
        </div>

        <h3 class="text-xl font-bold text-gray-800 mb-2">Menyimpan Data...</h3>
        <p class="text-gray-500 text-sm">Mohon jangan tutup halaman ini. <br>Sistem sedang mengirim notifikasi WhatsApp.</p>
    </div>
</div>

<div class="container mx-auto">
    <div class="mb-6"><a href="<?php echo $back_url; ?>" class="text-indigo-600 hover:underline flex items-center gap-1"><i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Jadwal</a></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- KARTU 1: JURNAL HARIAN -->
        <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-md h-fit sticky top-4">
            <h3 class="text-xl font-medium text-gray-800 mb-4 border-b pb-2">Jurnal Harian</h3>
            <p class="text-sm text-gray-500 mb-4">
                Jadwal: <strong class="capitalize"><?php echo htmlspecialchars($jadwal['kelas'] . ' - ' . $jadwal['kelompok']); ?></strong><br>
                Tanggal: <strong><?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?></strong>
            </p>

            <form id="form-jurnal">
                <input type="hidden" name="action" value="simpan_jurnal">
                <input type="hidden" name="jadwal_id" value="<?php echo $jadwal_id; ?>">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Pengajar*</label>
                        <input type="text" name="pengajar" value="<?php echo htmlspecialchars($jadwal['pengajar'] ?? ''); ?>" class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none transition" required placeholder="Siapa yang mengajar?">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Materi 1</label>
                        <textarea name="materi1" rows="2" class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none transition" placeholder="Materi utama..."><?php echo htmlspecialchars($jadwal['materi1'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Materi 2</label>
                        <textarea name="materi2" rows="2" class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none transition" placeholder="Materi tambahan..."><?php echo htmlspecialchars($jadwal['materi2'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Materi 3</label>
                        <textarea name="materi3" rows="2" class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none transition" placeholder="Catatan/Nasihat..."><?php echo htmlspecialchars($jadwal['materi3'] ?? ''); ?></textarea>
                    </div>
                    <div class="text-right pt-2">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition transform hover:scale-105 flex items-center gap-2 justify-center w-full">
                            <i class="fa-solid fa-save"></i> Simpan Jurnal
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- KARTU 2: INPUT KEHADIRAN -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md min-w-0">
            <h3 class="text-xl font-medium text-gray-800 mb-4 border-b pb-2 flex justify-between items-center">
                <span>Presensi Peserta</span>
                <span class="text-sm font-normal text-gray-500 bg-gray-100 px-2 py-1 rounded">Total: <?php echo count($peserta_presensi); ?> Siswa</span>
            </h3>

            <form id="form-presensi">
                <input type="hidden" name="action" value="simpan_kehadiran">
                <input type="hidden" name="jadwal_id" value="<?php echo $jadwal_id; ?>">

                <div class="overflow-x-auto overflow-y-auto max-h-[70vh] border rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="py-3 px-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama Siswa</th>
                                <th class="py-3 px-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status Kehadiran</th>
                                <th class="py-3 px-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="presensiTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($peserta_presensi as $peserta): $rekap_id = $peserta['id']; ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></div>
                                        <input type="hidden" name="nomor_hp_ortu[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['nomor_hp_orang_tua'] ?? ''); ?>">
                                        <input type="hidden" name="nama_peserta[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['nama_lengkap']); ?>">
                                        <input type="hidden" name="kirim_wa[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['kirim_wa'] ?? ''); ?>">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap justify-center gap-2">
                                            <?php
                                            $statuses = [
                                                'Hadir' => 'bg-green-100 text-green-800 border-green-200 peer-checked:bg-green-600 peer-checked:text-white',
                                                'Izin' => 'bg-blue-100 text-blue-800 border-blue-200 peer-checked:bg-blue-600 peer-checked:text-white',
                                                'Sakit' => 'bg-yellow-100 text-yellow-800 border-yellow-200 peer-checked:bg-yellow-500 peer-checked:text-white',
                                                'Alpa' => 'bg-red-100 text-red-800 border-red-200 peer-checked:bg-red-600 peer-checked:text-white'
                                            ];
                                            foreach ($statuses as $status => $class):
                                                $checked = ($peserta['status_kehadiran'] === $status) ? 'checked' : '';
                                            ?>
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="kehadiran[<?php echo $rekap_id; ?>]" value="<?php echo $status; ?>" class="sr-only peer status-radio" data-keterangan-id="keterangan-<?php echo $rekap_id; ?>" <?php echo $checked; ?>>
                                                    <span class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 hover:shadow-md <?php echo $class; ?>">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <input type="text"
                                            name="keterangan[<?php echo $rekap_id; ?>]"
                                            id="keterangan-<?php echo $rekap_id; ?>"
                                            value="<?php echo htmlspecialchars($peserta['keterangan'] ?? ''); ?>"
                                            class="block w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition disabled:bg-gray-100 disabled:text-gray-400"
                                            placeholder="Catatan...">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 text-right">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition transform hover:scale-105 flex items-center gap-2 ml-auto">
                        <i class="fa-solid fa-check-circle"></i> Simpan Kehadiran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const presensiTableBody = document.getElementById('presensiTableBody');
        const loadingOverlay = document.getElementById('loadingOverlay');

        // Fungsi Helper Loading
        const showLoading = () => loadingOverlay.classList.remove('hidden');
        const hideLoading = () => loadingOverlay.classList.add('hidden');

        // ==========================================
        // LOGIKA UPDATE KETERANGAN
        // ==========================================
        function updateKeterangan(radio) {
            const keteranganInput = document.getElementById(radio.dataset.keteranganId);
            if (!keteranganInput) return;

            const status = radio.value;

            // Reset style dasar
            keteranganInput.classList.remove('bg-gray-100', 'text-gray-500');

            if (status === 'Hadir') {
                keteranganInput.value = 'Hadir';
                keteranganInput.readOnly = true;
                keteranganInput.required = false;
                keteranganInput.classList.add('bg-gray-100', 'text-gray-500');
                keteranganInput.placeholder = '';
            } else if (status === 'Alpa') {
                keteranganInput.value = 'Tanpa Keterangan';
                keteranganInput.readOnly = true;
                keteranganInput.required = false;
                keteranganInput.classList.add('bg-gray-100', 'text-gray-500');
                keteranganInput.placeholder = '';
            } else {
                // Izin atau Sakit
                keteranganInput.readOnly = false;
                keteranganInput.required = true;
                keteranganInput.placeholder = 'Wajib diisi (Alasan)';

                // Jika isinya masih default sistem (Hadir/Tanpa Keterangan), kosongkan agar user bisa ketik
                // Jika sudah ada isinya dari DB (misal: "Sakit Demam"), biarkan saja
                if (keteranganInput.value === 'Hadir' || keteranganInput.value === 'Tanpa Keterangan') {
                    keteranganInput.value = '';
                }
            }
        }

        if (presensiTableBody) {
            // Init state saat load
            presensiTableBody.querySelectorAll('.status-radio:checked').forEach(updateKeterangan);

            // Event listener change radio
            presensiTableBody.addEventListener('change', e => {
                if (e.target.classList.contains('status-radio')) updateKeterangan(e.target);
            });
        }

        // =======================================================
        // HANDLE SUBMIT FORM JURNAL (AJAX)
        // =======================================================
        const formJurnal = document.getElementById('form-jurnal');
        if (formJurnal) {
            formJurnal.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation(); // <--- KUNCI: Mencegah trigger loader global index.php

                showLoading();

                const formData = new FormData(formJurnal);

                fetch('pages/ajax_input_presensi.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();

                        if (data.status === 'success') {
                            Swal.fire({
                                title: 'Berhasil!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                title: 'Gagal!',
                                text: data.message,
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('Error:', error);
                        Swal.fire('Error', 'Terjadi kesalahan koneksi server.', 'error');
                    });
            });
        }

        // =======================================================
        // HANDLE SUBMIT FORM PRESENSI (AJAX)
        // =======================================================
        const formPresensi = document.getElementById('form-presensi');
        if (formPresensi) {
            formPresensi.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation(); // <--- KUNCI: Mencegah trigger loader global index.php

                // Cek validasi manual (misal ada keterangan yg wajib tapi kosong)
                if (!formPresensi.checkValidity()) {
                    formPresensi.reportValidity();
                    return;
                }

                showLoading();

                const formData = new FormData(formPresensi);

                fetch('pages/ajax_input_presensi.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();

                        if (data.status === 'success') {
                            Swal.fire({
                                title: 'Tersimpan!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                title: 'Gagal!',
                                text: data.message,
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('Error:', error);
                        Swal.fire('Error', 'Terjadi kesalahan koneksi server.', 'error');
                    });
            });
        }
    });
</script>