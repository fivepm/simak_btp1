<?php
// Pastikan $conn dan $_SESSION sudah ada
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ambil data admin dari sesi
$id_admin_pembuat = $_SESSION['user_id'] ?? 0;
$nama_admin_pembuat = $_SESSION['user_nama'] ?? 'Admin';

// Variabel untuk notifikasi SweetAlert
$swal_notification = '';

// Cek apakah ini mode EDIT
$edit_mode = false;
$laporan_data = null;
if (isset($_GET['id'])) {
    $id_laporan = (int)$_GET['id'];
    $stmt_edit = $conn->prepare("SELECT * FROM laporan_harian WHERE id = ? AND status_laporan = 'Draft'");
    $stmt_edit->bind_param("i", $id_laporan);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    if ($result_edit->num_rows > 0) {
        $edit_mode = true;
        $laporan_data = $result_edit->fetch_assoc();
    } else {
        // Ganti pesan error teks biasa dengan notifikasi (opsional, tapi lebih rapi)
        // echo "<p class='text-red-500'>Error: Laporan tidak ditemukan atau sudah Final.</p>";
        $swal_notification = "
            Swal.fire({
                title: 'Akses Ditolak',
                text: 'Laporan tidak ditemukan atau sudah berstatus Final.',
                icon: 'error'
            }).then(() => {
                window.location.href = '?page=report/daftar_laporan_harian';
            });
        ";
    }
    $stmt_edit->close();
}

// ===================================================================
// BAGIAN 1: LOGIKA SIMPAN (POST)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ==========================================================
    // ▼▼▼ PERBAIKAN: Set Timezone & Ambil Waktu PHP ▼▼▼
    // ==========================================================
    date_default_timezone_set('Asia/Jakarta');
    $waktu_sekarang_php = date('Y-m-d H:i:s');
    // ==========================================================

    // Ambil data dari form
    $tanggal_laporan = $_POST['tanggal_laporan'] ?? date('Y-m-d');
    $data_statistik_json = $_POST['data_statistik_json'] ?? '{}';
    $catatan_kondisi = $_POST['catatan_kondisi'] ?? '';
    $rekomendasi_tindakan = $_POST['rekomendasi_tindakan'] ?? '';
    $status_laporan = $_POST['status_laporan'] ?? 'Draft';

    // Cek jika JSON valid
    if (json_decode($data_statistik_json) === null) {
        die("Error: Data statistik tidak valid. Coba tarik data lagi.");
    }

    if ($edit_mode && isset($_POST['id_laporan'])) {
        // --- LOGIKA UPDATE ---
        $id_laporan_update = (int)$_POST['id_laporan'];

        $sql = "UPDATE laporan_harian SET 
                    tanggal_laporan = ?, 
                    data_statistik = ?, 
                    catatan_kondisi = ?, 
                    rekomendasi_tindakan = ?, 
                    status_laporan = ?,
                    timestamp_diperbarui = ? 
                WHERE id = ? AND status_laporan = 'Draft'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $tanggal_laporan, $data_statistik_json, $catatan_kondisi, $rekomendasi_tindakan, $status_laporan, $waktu_sekarang_php, $id_laporan_update);
    } else {
        // --- LOGIKA INSERT BARU ---
        $sql = "INSERT INTO laporan_harian 
                    (tanggal_laporan, id_admin_pembuat, nama_admin_pembuat, status_laporan, data_statistik, catatan_kondisi, rekomendasi_tindakan, timestamp_dibuat) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssss", $tanggal_laporan, $id_admin_pembuat, $nama_admin_pembuat, $status_laporan, $data_statistik_json, $catatan_kondisi, $rekomendasi_tindakan, $waktu_sekarang_php);
    }

    // Eksekusi dan redirect
    if ($stmt->execute()) {
        // --- CCTV ---
        if ($edit_mode) {
            $deskripsi_log = "Memperbarui *Laporan Harian* pada tanggal " . formatTanggalIndonesia($tanggal_laporan) . " (Status : *$status_laporan*).";
            writeLog('UPDATE', $deskripsi_log);
        } else {
            $deskripsi_log = "Membuat *Laporan Harian* baru pada tanggal " . formatTanggalIndonesia($tanggal_laporan) . " (Status : *$status_laporan*).";
            writeLog('INSERT', $deskripsi_log);
        }
        // -------------------------------------------

        // --- GANTI ALERT JS DENGAN SWEETALERT ---
        $pesan_sukses = ($status_laporan === 'Final') ? 'Laporan berhasil difinalisasi.' : 'Laporan berhasil disimpan sebagai Draft.';

        $swal_notification = "
            Swal.fire({
                title: 'Berhasil!',
                text: '$pesan_sukses',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = '?page=report/daftar_laporan_harian';
            });
        ";
        // Kita tidak menggunakan exit; di sini agar halaman merender SweetAlert di bagian bawah

    } else {
        $pesan_error = addslashes($stmt->error);
        $swal_notification = "
            Swal.fire({
                title: 'Gagal!',
                text: 'Error saat menyimpan laporan: $pesan_error',
                icon: 'error'
            });
        ";
    }
    $stmt->close();
}

// ===================================================================
// BAGIAN 2: TAMPILAN HTML (Form)
// ===================================================================
?>
<div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
    <!-- Form Utama -->
    <form id="form-laporan-harian" method="POST" action="?page=report/form_laporan_harian<?php echo $edit_mode ? '&id=' . $id_laporan : ''; ?>">

        <?php if ($edit_mode): ?>
            <input type="hidden" name="id_laporan" value="<?php echo $laporan_data['id']; ?>">
        <?php endif; ?>

        <!-- Input tersembunyi -->
        <input type="hidden" name="data_statistik_json" id="data_statistik_json" value="<?php echo htmlspecialchars($laporan_data['data_statistik'] ?? '{}'); ?>">
        <input type="hidden" name="status_laporan" id="status_laporan_hidden" value="Draft">


        <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $edit_mode ? 'Edit Laporan Harian' : 'Buat Laporan Harian Baru'; ?></h1>

        <!-- Bagian 1: Tarik Data -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">1. Data Statistik</h2>
            <div class="flex flex-col sm:flex-row sm:items-end sm:gap-4">
                <div class="flex-grow">
                    <label for="tanggal_laporan" class="block text-sm font-medium text-gray-700">Tanggal Laporan</label>
                    <input type="date" name="tanggal_laporan" id="tanggal_laporan"
                        value="<?php echo htmlspecialchars($laporan_data['tanggal_laporan'] ?? date('Y-m-d', strtotime('yesterday'))); ?>"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500">
                </div>
                <button type="button" id="btn-tarik-data" class="mt-4 sm:mt-0 w-full sm:w-auto bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center transition duration-300">
                    <i class="fas fa-sync-alt mr-2"></i> Tarik Data
                </button>
            </div>

            <!-- Loader & Error -->
            <div id="loader-statistik" class="text-center py-4 hidden">
                <i class="fas fa-spinner fa-spin text-cyan-600 text-2xl"></i>
                <p class="text-gray-500">Menarik data statistik... (Analisis AI mungkin butuh beberapa detik)</p>
            </div>
            <div id="error-container" class="text-center py-4 text-red-600 font-semibold hidden"></div>

            <div id="statistik-container" class="mt-6 space-y-4">
                <!-- Hasil Global -->
                <div id="statistik-global-display"></div>

                <!-- Grafik -->
                <div id="statistik-chart-container" class="mt-6 hidden">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Grafik Persentase Kehadiran (Hadir / Total Terisi)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-md font-semibold text-center text-gray-700 capitalize">Bintaran</h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg">
                                <canvas id="kehadiranChart_bintaran"></canvas>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-md font-semibold text-center text-gray-700 capitalize">Gedongkuning</h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg">
                                <canvas id="kehadiranChart_gedongkuning"></canvas>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-md font-semibold text-center text-gray-700 capitalize">Jombor</h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg">
                                <canvas id="kehadiranChart_jombor"></canvas>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-md font-semibold text-center text-gray-700 capitalize">Sunten</h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg">
                                <canvas id="kehadiranChart_sunten"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hasil Rincian -->
                <div id="statistik-rincian-display" class="overflow-x-auto"></div>

                <!-- Daftar Alpa -->
                <div id="statistik-alpa-container" class="mt-6"></div>
            </div>
        </div>

        <!-- Bagian 2: Analisis Admin -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">2. Analisis & Rekomendasi (Draf oleh AI)</h2>
            <div class="space-y-4">
                <div>
                    <label for="catatan_kondisi" class="block text-sm font-medium text-gray-700">Catatan Kondisi Hari Ini</label>
                    <textarea name="catatan_kondisi" id="catatan_kondisi" rows="5" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500" placeholder="Klik 'Tarik Data' untuk analisis otomatis..."><?php echo htmlspecialchars($laporan_data['catatan_kondisi'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label for="rekomendasi_tindakan" class="block text-sm font-medium text-gray-700">Rekomendasi Tindakan</label>
                    <textarea name="rekomendasi_tindakan" id="rekomendasi_tindakan" rows="5" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500" placeholder="Klik 'Tarik Data' untuk analisis otomatis..."><?php echo htmlspecialchars($laporan_data['rekomendasi_tindakan'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Bagian 3: Tombol Aksi -->
        <div class="flex justify-end gap-4">
            <a href="?page=report/daftar_laporan_harian" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300">Batal</a>

            <button type="button" id="btn-save-draft" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                Simpan sebagai Draft
            </button>

            <button type="button" id="btn-show-final-confirm" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                Simpan & Finalisasi
            </button>
        </div>

    </form>
</div>

<!-- Modal Konfirmasi Finalisasi -->
<div id="modal-confirm-final" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Konfirmasi Finalisasi Laporan
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Apakah Anda yakin ingin memfinalisasi laporan ini? Laporan yang sudah final tidak dapat diedit kembali.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="btn-confirm-final" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Ya, Finalisasi
                </button>
                <button type="button" id="btn-cancel-final" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Library Chart.js & SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnTarikData = document.getElementById('btn-tarik-data');
        const tanggalInput = document.getElementById('tanggal_laporan');
        const loader = document.getElementById('loader-statistik');
        const errorContainer = document.getElementById('error-container');
        const globalContainer = document.getElementById('statistik-global-display');
        const rincianContainer = document.getElementById('statistik-rincian-display');
        const hiddenJsonInput = document.getElementById('data_statistik_json');
        const chartContainer = document.getElementById('statistik-chart-container');

        const formLaporan = document.getElementById('form-laporan-harian');
        const statusHiddenInput = document.getElementById('status_laporan_hidden');

        const btnSaveDraft = document.getElementById('btn-save-draft');
        const btnShowFinalConfirm = document.getElementById('btn-show-final-confirm');

        const modalConfirmFinal = document.getElementById('modal-confirm-final');
        const btnConfirmFinal = document.getElementById('btn-confirm-final');
        const btnCancelFinal = document.getElementById('btn-cancel-final');

        let kehadiranChartInstances = {};
        Chart.register(ChartDataLabels);

        const URUTAN_KELOMPOK = ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
        const URUTAN_KELAS = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];

        function logPesanDebug(messages) {
            if (messages && Array.isArray(messages) && messages.length > 0) {
                console.warn("=== Pesan Debug dari PHP ===");
                messages.forEach(msg => console.log(msg));
                console.warn("===========================");
            }
        }

        function tampilkanStatistik(data) {
            // --- Isi otomatis form AI ---
            const catatanTextarea = document.getElementById('catatan_kondisi');
            const rekomendasiTextarea = document.getElementById('rekomendasi_tindakan');
            <?php if (!$edit_mode): ?>
                if (data.catatan_kondisi_ai) catatanTextarea.value = data.catatan_kondisi_ai;
                if (data.rekomendasi_tindakan_ai) rekomendasiTextarea.value = data.rekomendasi_tindakan_ai;
            <?php else: ?>
                if (!catatanTextarea.value && data.catatan_kondisi_ai) catatanTextarea.value = data.catatan_kondisi_ai;
                if (!rekomendasiTextarea.value && data.rekomendasi_tindakan_ai) rekomendasiTextarea.value = data.rekomendasi_tindakan_ai;
            <?php endif; ?>

            // --- Tampilkan Global ---
            let globalHtml = '<h3 class="text-lg font-semibold text-gray-800">Ringkasan Global</h3><ul class="list-disc list-inside pl-2 text-gray-700 grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2">';
            globalHtml += `<li>Jadwal Hari Ini: <strong>${data.global.total_jadwal_hari_ini}</strong></li>`;
            globalHtml += `<li>Presensi Terisi: <strong>${data.global.total_presensi_terisi}</strong></li>`;
            globalHtml += `<li>Jurnal Terisi: <strong>${data.global.total_jurnal_terisi}</strong></li>`;
            globalHtml += `<li>Jadwal Terlewat: <strong>${data.global.jadwal_terlewat}</strong></li>`;
            globalHtml += `<li class="text-green-600">Total Hadir: <strong>${data.global.total_siswa_hadir}</strong></li>`;
            globalHtml += `<li class="text-blue-600">Total Izin: <strong>${data.global.total_siswa_izin}</strong></li>`;
            globalHtml += `<li class="text-yellow-600">Total Sakit: <strong>${data.global.total_siswa_sakit}</strong></li>`;
            globalHtml += `<li class="text-red-600">Total Alpa: <strong>${data.global.total_siswa_alpa}</strong></li>`;
            globalHtml += '</ul>';
            globalContainer.innerHTML = globalHtml;

            // --- Tampilkan Rincian Sesuai Urutan Standar ---
            let rincianHtml = '<h3 class="text-lg font-semibold text-gray-800 mt-4">Rincian per Kelas</h3>';
            rincianHtml += '<table class="min-w-full divide-y divide-gray-200 text-sm mt-2">';
            rincianHtml += '<thead class="bg-gray-50"><tr>';
            rincianHtml += '<th class="px-4 py-2 text-left font-medium text-gray-500">Kelompok</th>';
            rincianHtml += '<th class="px-4 py-2 text-left font-medium text-gray-500">Kelas</th>';
            rincianHtml += '<th class="px-4 py-2 text-center font-medium text-gray-500">Hadir</th>';
            rincianHtml += '<th class="px-4 py-2 text-center font-medium text-gray-500">Izin</th>';
            rincianHtml += '<th class="px-4 py-2 text-center font-medium text-gray-500">Sakit</th>';
            rincianHtml += '<th class="px-4 py-2 text-center font-medium text-gray-500">Alpa</th>';
            rincianHtml += '<th class="px-4 py-2 text-center font-medium text-gray-500">Presensi</th>';
            rincianHtml += '<th class="px-4 py-2 text-center font-medium text-gray-500">Jurnal</th>';
            rincianHtml += '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

            let hasRincian = false;
            URUTAN_KELOMPOK.forEach(kelompok => {
                URUTAN_KELAS.forEach(kelas => {
                    if (data.rincian_per_kelompok && data.rincian_per_kelompok[kelompok] && data.rincian_per_kelompok[kelompok][kelas]) {
                        hasRincian = true;
                        const d = data.rincian_per_kelompok[kelompok][kelas];
                        if (d.total_jadwal === 0) {
                            rincianHtml += '<tr class="bg-gray-50">';
                            rincianHtml += `<td class="px-4 py-2 font-medium capitalize text-gray-500">${kelompok}</td>`;
                            rincianHtml += `<td class="px-4 py-2 capitalize text-gray-500">${kelas}</td>`;
                            rincianHtml += `<td colspan="6" class="px-4 py-2 text-center text-gray-400 italic">Tidak ada jadwal</td>`;
                            rincianHtml += '</tr>';
                        } else {
                            rincianHtml += '<tr>';
                            rincianHtml += `<td class="px-4 py-2 font-medium capitalize">${kelompok}</td>`;
                            rincianHtml += `<td class="px-4 py-2 capitalize">${kelas}</td>`;
                            rincianHtml += `<td class="px-4 py-2 text-center text-green-600 font-semibold">${d.hadir}</td>`;
                            rincianHtml += `<td class="px-4 py-2 text-center text-blue-600 font-semibold">${d.izin}</td>`;
                            rincianHtml += `<td class="px-4 py-2 text-center text-yellow-600 font-semibold">${d.sakit}</td>`;
                            rincianHtml += `<td class="px-4 py-2 text-center text-red-600 font-semibold">${d.alpa}</td>`;
                            const presensiIcon = (d.jadwal_terisi === d.total_jadwal && d.total_jadwal > 0) ? '<span class="text-green-500 font-bold">✓</span>' : '<span class="text-red-500 font-bold">X</span>';
                            const jurnalIcon = (d.jurnal_terisi === d.total_jadwal && d.total_jadwal > 0) ? '<span class="text-green-500 font-bold">✓</span>' : '<span class="text-red-500 font-bold">X</span>';
                            rincianHtml += `<td class="px-4 py-2 text-center">${presensiIcon}</td>`;
                            rincianHtml += `<td class="px-4 py-2 text-center">${jurnalIcon}</td>`;
                            rincianHtml += '</tr>';
                        }
                    }
                });
            });
            if (!hasRincian) {
                rincianHtml += '<tr><td colspan="8" class="text-center py-4 text-gray-500">Tidak ada data rincian ditemukan.</td></tr>';
            }
            rincianHtml += '</tbody></table>';
            rincianContainer.innerHTML = rincianHtml;

            // --- Tampilkan Daftar Siswa Alpa ---
            const alpaContainer = document.getElementById('statistik-alpa-container');
            let alpaHtml = '';
            if (data.daftar_alpa && data.daftar_alpa.length > 0) {
                alpaHtml = '<h3 class="text-lg font-semibold text-gray-800 mt-4 text-red-600">Daftar Siswa Alpa</h3>';
                alpaHtml += '<p class="text-sm text-gray-600 mb-2">Siswa yang tercatat Alpa pada jadwal KBM hari ini. Mohon segera ditindaklanjuti.</p>';
                alpaHtml += '<table class="min-w-full divide-y divide-gray-200 text-sm mt-2">';
                alpaHtml += '<thead class="bg-gray-50"><tr>';
                alpaHtml += '<th class="px-4 py-2 text-left font-medium text-gray-500">Nama Siswa</th>';
                alpaHtml += '<th class="px-4 py-2 text-left font-medium text-gray-500">Kelompok</th>';
                alpaHtml += '<th class="px-4 py-2 text-left font-medium text-gray-500">Kelas</th>';
                alpaHtml += '<th class="px-4 py-2 text-left font-medium text-gray-500">Nama Orang Tua</th>';
                alpaHtml += '<th class="px-4 py-2 text-left font-medium text-gray-500">No. HP Orang Tua</th>';
                alpaHtml += '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                data.daftar_alpa.forEach(siswa => {
                    alpaHtml += '<tr>';
                    alpaHtml += `<td class="px-4 py-2 font-medium">${siswa.nama_lengkap}</td>`;
                    alpaHtml += `<td class="px-4 py-2 capitalize">${siswa.kelompok}</td>`;
                    alpaHtml += `<td class="px-4 py-2 capitalize">${siswa.kelas}</td>`;
                    alpaHtml += `<td class="px-4 py-2">${siswa.nama_orang_tua || '-'}</td>`;
                    alpaHtml += `<td class="px-4 py-2">${siswa.nomor_hp_orang_tua || '-'}</td>`;
                    alpaHtml += '</tr>';
                });
                alpaHtml += '</tbody></table>';
            } else {
                alpaHtml = '<h3 class="text-lg font-semibold text-gray-800 mt-4">Daftar Siswa Alpa</h3>';
                alpaHtml += '<p class="text-sm text-gray-600 mt-2 p-4 border rounded-md bg-green-50 text-green-700">Tidak ada siswa yang tercatat alpa pada tanggal ini.</p>';
            }
            alpaContainer.innerHTML = alpaHtml;

            // --- Chart Logic ---
            const getBarColor = (value) => {
                if (value === null) return 'rgba(156, 163, 175, 0.6)';
                if (value < 50) return 'rgba(239, 68, 68, 0.6)';
                if (value >= 50 && value <= 75) return 'rgba(245, 158, 11, 0.6)';
                return 'rgba(34, 197, 94, 0.6)';
            };
            const getBorderColor = (value) => {
                if (value === null) return 'rgba(156, 163, 175, 1)';
                if (value < 50) return 'rgba(239, 68, 68, 1)';
                if (value >= 50 && value <= 75) return 'rgba(245, 158, 11, 1)';
                return 'rgba(34, 197, 94, 1)';
            };
            if (!data.rincian_per_kelompok || Object.keys(data.rincian_per_kelompok).length === 0) {
                chartContainer.classList.add('hidden');
                return;
            }
            chartContainer.classList.remove('hidden');
            URUTAN_KELOMPOK.forEach(kelompok => {
                if (kehadiranChartInstances[kelompok]) {
                    kehadiranChartInstances[kelompok].destroy();
                }
                const canvasId = 'kehadiranChart_' + kelompok;
                const ctx = document.getElementById(canvasId);
                if (ctx) {
                    const chartLabels = [];
                    const chartData = [];
                    URUTAN_KELAS.forEach(kelas => {
                        const d = (data.rincian_per_kelompok[kelompok] && data.rincian_per_kelompok[kelompok][kelas]) ? data.rincian_per_kelompok[kelompok][kelas] : null;
                        chartLabels.push(kelas.charAt(0).toUpperCase() + kelas.slice(1));
                        let percentage = null;
                        if (d) {
                            const totalTerisi = d.hadir + d.izin + d.sakit + d.alpa;
                            if (d.total_jadwal > 0) {
                                percentage = (totalTerisi > 0) ? (d.hadir / totalTerisi) * 100 : 0;
                            }
                        }
                        chartData.push(percentage !== null ? Math.round(percentage) : null);
                    });
                    kehadiranChartInstances[kelompok] = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: 'Kehadiran (%)',
                                data: chartData,
                                backgroundColor: chartData.map(value => getBarColor(value)),
                                borderColor: chartData.map(value => getBorderColor(value)),
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 105
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) label += ': ';
                                            const value = context.raw;
                                            if (value === null) return label + 'N/A';
                                            return label + value + '%';
                                        }
                                    }
                                },
                                datalabels: {
                                    anchor: (context) => 'end',
                                    align: (context) => {
                                        const value = context.dataset.data[context.dataIndex];
                                        if (value === null) return 'center';
                                        return value >= 90 ? 'bottom' : 'top';
                                    },
                                    offset: (context) => {
                                        const value = context.dataset.data[context.dataIndex];
                                        if (value === null) return 0;
                                        return value >= 90 ? 8 : -6;
                                    },
                                    formatter: (value, context) => {
                                        if (value === null) return 'N/A';
                                        return value + '%';
                                    },
                                    color: (context) => {
                                        const value = context.dataset.data[context.dataIndex];
                                        if (value === null) return '#9ca3af';
                                        return value >= 90 ? '#ffffff' : '#6b7280';
                                    },
                                    font: {
                                        weight: 'bold'
                                    }
                                }
                            }
                        }
                    });
                }
            });
        }

        // --- FETCH EVENT LISTENER ---
        btnTarikData.addEventListener('click', function() {
            const tanggal = tanggalInput.value;
            if (!tanggal) {
                Swal.fire('Info', 'Silakan pilih tanggal terlebih dahulu.', 'info');
                return;
            }
            loader.classList.remove('hidden');
            errorContainer.classList.add('hidden');
            globalContainer.innerHTML = '';
            rincianContainer.innerHTML = '';
            chartContainer.classList.add('hidden');
            const alpaContainer = document.getElementById('statistik-alpa-container');
            if (alpaContainer) alpaContainer.innerHTML = '';
            btnTarikData.disabled = true;

            fetch(`pages/report/ajax_get_stats_harian.php?tanggal=${tanggal}`)
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errorData => {
                            throw new Error(errorData.error || `Error ${response.status}`);
                        }).catch(() => {
                            throw new Error(`Respon server tidak valid (${response.status})`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    hiddenJsonInput.value = JSON.stringify(data);
                    tampilkanStatistik(data);
                })
                .catch(error => {
                    console.error('[JS] Error:', error);
                    errorContainer.innerHTML = `Terjadi kesalahan: ${error.message}`;
                    errorContainer.classList.remove('hidden');
                    hiddenJsonInput.value = '{}';
                })
                .finally(() => {
                    loader.classList.add('hidden');
                    btnTarikData.disabled = false;
                });
        });

        <?php if ($edit_mode && $laporan_data['data_statistik']): ?>
            try {
                const dataLama = JSON.parse(<?php echo json_encode($laporan_data['data_statistik']); ?>);
                if (dataLama && dataLama.global) {
                    tampilkanStatistik(dataLama);
                }
            } catch (e) {
                console.error("Gagal memuat data statistik lama:", e);
            }
        <?php endif; ?>

        // --- MODAL LOGIC ---
        function showConfirmModal() {
            if (modalConfirmFinal) modalConfirmFinal.classList.remove('hidden');
        }

        function hideConfirmModal() {
            if (modalConfirmFinal) modalConfirmFinal.classList.add('hidden');
        }

        if (btnShowFinalConfirm) {
            btnShowFinalConfirm.addEventListener('click', showConfirmModal);
        }

        if (btnCancelFinal) {
            btnCancelFinal.addEventListener('click', hideConfirmModal);
        }

        if (modalConfirmFinal) {
            modalConfirmFinal.addEventListener('click', function(event) {
                if (event.target === modalConfirmFinal) {
                    hideConfirmModal();
                }
            });
        }

        if (btnConfirmFinal) {
            btnConfirmFinal.addEventListener('click', function() {
                if (statusHiddenInput) statusHiddenInput.value = 'Final';
                if (formLaporan) formLaporan.submit();
            });
        }

        if (btnSaveDraft) {
            btnSaveDraft.addEventListener('click', function() {
                if (statusHiddenInput) statusHiddenInput.value = 'Draft';
                if (formLaporan) formLaporan.submit();
            });
        }
    });
</script>