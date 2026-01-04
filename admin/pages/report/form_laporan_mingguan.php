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
$tanggal_mulai_edit = date('Y-m-d'); // Default ke hari ini
$tanggal_akhir_edit = date('Y-m-d'); // Default ke hari ini

// Fungsi untuk mendapatkan Senin dari tanggal tertentu
function getMonday($date_str)
{
    try {
        $date = new DateTime($date_str);
        $dayOfWeek = $date->format('N'); // 1 (for Monday) through 7 (for Sunday)
        $diff = 1 - $dayOfWeek;
        $date->modify("$diff day");
        return $date->format('Y-m-d');
    } catch (Exception $e) {
        // Fallback jika format tanggal salah
        return date('Y-m-d', strtotime('last monday'));
    }
}
// Set default $tanggal_mulai_edit ke Senin minggu ini
$tanggal_mulai_edit = getMonday(date('Y-m-d'));


if (isset($_GET['id'])) {
    $id_laporan = (int)$_GET['id'];
    $stmt_edit = $conn->prepare("SELECT * FROM laporan_mingguan WHERE id = ? AND status_laporan = 'Draft'");
    if ($stmt_edit) { // Cek prepare
        $stmt_edit->bind_param("i", $id_laporan);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows > 0) {
            $edit_mode = true;
            $laporan_data = $result_edit->fetch_assoc();
            $tanggal_mulai_edit = $laporan_data['tanggal_mulai']; // Ambil tanggal mulai dari DB
            // Hitung tanggal akhir dari tanggal mulai (Senin + 6 hari)
            try {
                $tglMulaiDate = new DateTime($tanggal_mulai_edit);
                $tglMulaiDate->modify('+6 day');
                $tanggal_akhir_edit = $tglMulaiDate->format('Y-m-d');
            } catch (Exception $e) {
                $tanggal_akhir_edit = $tanggal_mulai_edit;
            }
        } else {
            // Ganti Error HTML dengan SweetAlert Redirect
            $swal_notification = "
                Swal.fire({
                    title: 'Akses Ditolak',
                    text: 'Laporan mingguan tidak ditemukan atau sudah berstatus Final.',
                    icon: 'error'
                }).then(() => {
                    window.location.href = '?page=report/daftar_laporan_mingguan';
                });
            ";
            $edit_mode = false;
        }
        $stmt_edit->close();
    } else {
        $edit_mode = false;
        $error_msg = addslashes($conn->error);
        $swal_notification = "Swal.fire('Error Database', '$error_msg', 'error');";
    }
} else {
    // Jika bukan mode edit, hitung tanggal akhir default (Minggu) dari Senin default
    try {
        $tglMulaiDate = new DateTime($tanggal_mulai_edit);
        $tglMulaiDate->modify('+6 day');
        $tanggal_akhir_edit = $tglMulaiDate->format('Y-m-d');
    } catch (Exception $e) {
        $tanggal_akhir_edit = $tanggal_mulai_edit;
    }
}

// Logika Simpan (POST) - Mirip Harian, tapi pakai tanggal_mulai & tanggal_akhir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    date_default_timezone_set('Asia/Jakarta');
    $waktu_sekarang_php = date('Y-m-d H:i:s');

    // Ambil tanggal mulai & akhir dari hidden input
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? null;
    $tanggal_akhir = $_POST['tanggal_akhir'] ?? null;

    $data_statistik_json = $_POST['data_statistik_json'] ?? '{}';
    $catatan_kondisi = $_POST['catatan_kondisi'] ?? '';
    $rekomendasi_tindakan = $_POST['rekomendasi_tindakan'] ?? '';
    $status_laporan = $_POST['status_laporan'] ?? 'Draft';

    if (json_decode($data_statistik_json) === null || empty($tanggal_mulai) || empty($tanggal_akhir)) {
        $swal_notification = "Swal.fire('Data Tidak Valid', 'Pastikan rentang tanggal dipilih dan data statistik ditarik sebelum menyimpan.', 'warning');";
    } else {

        if ($edit_mode && isset($_POST['id_laporan'])) {
            // --- LOGIKA UPDATE ---
            $id_laporan_update = (int)$_POST['id_laporan'];
            $sql = "UPDATE laporan_mingguan SET 
                        tanggal_mulai = ?, tanggal_akhir = ?, data_statistik = ?, 
                        catatan_kondisi = ?, rekomendasi_tindakan = ?, status_laporan = ?,
                        timestamp_diperbarui = ?
                    WHERE id = ? AND status_laporan = 'Draft'";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssssssi", $tanggal_mulai, $tanggal_akhir, $data_statistik_json, $catatan_kondisi, $rekomendasi_tindakan, $status_laporan, $waktu_sekarang_php, $id_laporan_update);
                if ($stmt->execute()) {
                    // --- CCTV ---
                    $deskripsi_log = "Memperbarui *Laporan Mingguan* pada tanggal " . formatTanggalIndonesia($tanggal_mulai) . " - " . formatTanggalIndonesia($tanggal_akhir) . " (Status : *$status_laporan*).";
                    writeLog('UPDATE', $deskripsi_log);
                    // -------------------------------------------

                    // SWAL SUKSES UPDATE
                    $pesan_sukses = ($status_laporan === 'Final') ? 'Laporan Mingguan berhasil difinalisasi.' : 'Laporan Mingguan berhasil diperbarui.';
                    $swal_notification = "
                        Swal.fire({
                            title: 'Berhasil!',
                            text: '$pesan_sukses',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = '?page=report/daftar_laporan_mingguan';
                        });
                    ";
                } else {
                    $error_msg = addslashes($stmt->error);
                    $swal_notification = "Swal.fire('Gagal Update', '$error_msg', 'error');";
                }
                $stmt->close();
            } else {
                $error_msg = addslashes($conn->error);
                $swal_notification = "Swal.fire('Error Prepare', '$error_msg', 'error');";
            }
        } else {
            // --- LOGIKA INSERT BARU ---
            $sql = "INSERT INTO laporan_mingguan 
                        (tanggal_mulai, tanggal_akhir, id_admin_pembuat, nama_admin_pembuat, status_laporan, data_statistik, catatan_kondisi, rekomendasi_tindakan, timestamp_dibuat) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssissssss", $tanggal_mulai, $tanggal_akhir, $id_admin_pembuat, $nama_admin_pembuat, $status_laporan, $data_statistik_json, $catatan_kondisi, $rekomendasi_tindakan, $waktu_sekarang_php);
                if ($stmt->execute()) {
                    // --- CCTV ---
                    $deskripsi_log = "Membuat *Laporan Mingguan* baru pada tanggal " . formatTanggalIndonesia($tanggal_mulai) . " - " . formatTanggalIndonesia($tanggal_akhir) . " (Status : *$status_laporan*).";
                    writeLog('INSERT', $deskripsi_log);
                    // -------------------------------------------

                    // SWAL SUKSES INSERT
                    $pesan_sukses = ($status_laporan === 'Final') ? 'Laporan Mingguan berhasil difinalisasi.' : 'Laporan Mingguan berhasil disimpan.';
                    $swal_notification = "
                        Swal.fire({
                            title: 'Berhasil!',
                            text: '$pesan_sukses',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = '?page=report/daftar_laporan_mingguan';
                        });
                    ";
                } else {
                    // Tangani error duplikasi tanggal_mulai
                    if ($conn->errno == 1062) {
                        $swal_notification = "Swal.fire('Duplikasi', 'Laporan untuk minggu yang dimulai tanggal $tanggal_mulai sudah ada.', 'warning');";
                    } else {
                        $error_msg = addslashes($stmt->error);
                        $swal_notification = "Swal.fire('Gagal Simpan', '$error_msg', 'error');";
                    }
                }
                $stmt->close();
            } else {
                $error_msg = addslashes($conn->error);
                $swal_notification = "Swal.fire('Error Prepare', '$error_msg', 'error');";
            }
        }
    } // Akhir cek valid
} // Akhir cek POST

// Helper format tanggal pendek
if (!function_exists('formatTanggalIndoShort')) {
    function formatTanggalIndoShort($tanggal_db)
    {
        if (empty($tanggal_db) || $tanggal_db === '0000-00-00') return '';
        try {
            $date = new DateTime($tanggal_db);
            $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return $date->format('j') . ' ' . $bulan[(int)$date->format('n')];
        } catch (Exception $e) {
            return date('d/m', strtotime($tanggal_db));
        }
    }
}
?>
<div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
    <form id="form-laporan-mingguan" method="POST" action="?page=report/form_laporan_mingguan<?php echo $edit_mode && isset($id_laporan) ? '&id=' . $id_laporan : ''; ?>">

        <?php if ($edit_mode && isset($laporan_data['id'])): ?>
            <input type="hidden" name="id_laporan" value="<?php echo $laporan_data['id']; ?>">
        <?php endif; ?>

        <input type="hidden" name="data_statistik_json" id="data_statistik_json" value="<?php echo htmlspecialchars($laporan_data['data_statistik'] ?? '{}'); ?>">
        <input type="hidden" name="status_laporan" id="status_laporan_hidden" value="Draft">
        <input type="hidden" name="tanggal_mulai" id="tanggal_mulai_hidden" value="<?php echo $tanggal_mulai_edit; ?>">
        <input type="hidden" name="tanggal_akhir" id="tanggal_akhir_hidden" value="<?php echo $tanggal_akhir_edit; ?>">

        <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $edit_mode ? 'Edit Laporan Mingguan' : 'Buat Laporan Mingguan Baru'; ?></h1>

        <!-- Bagian 1: Pilih Minggu & Tarik Data -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">1. Pilih Minggu & Tarik Data</h2>
            <div class="flex flex-col sm:flex-row sm:items-end sm:gap-4">
                <div class="flex-grow">
                    <label for="tanggal_pilihan" class="block text-sm font-medium text-gray-700">Pilih Tanggal dalam Minggu</label>
                    <input type="date" id="tanggal_pilihan"
                        value="<?php echo $tanggal_mulai_edit; ?>"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500">
                    <p class="text-sm text-gray-500 mt-1">Minggu yang dipilih: <strong id="rentang-minggu-display">...</strong></p>
                </div>
                <button type="button" id="btn-tarik-data-mingguan" class="mt-4 sm:mt-0 w-full sm:w-auto bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center transition duration-300">
                    <i class="fas fa-sync-alt mr-2"></i> Tarik Data Mingguan
                </button>
            </div>

            <div id="loader-statistik-mingguan" class="text-center py-4 hidden">
                <i class="fas fa-spinner fa-spin text-cyan-600 text-2xl"></i>
                <p class="text-gray-500">Menarik data mingguan... (Analisis AI mungkin butuh waktu)</p>
            </div>
            <div id="error-container-mingguan" class="text-center py-4 text-red-600 font-semibold hidden"></div>

            <div id="statistik-container-mingguan" class="mt-6 space-y-4">
                <div id="statistik-global-display-mingguan"></div>

                <!-- Kontainer Grafik (Sekarang Grouped Bar) -->
                <div id="statistik-chart-container-mingguan" class="mt-6 hidden">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Grafik Persentase Status Kehadiran Mingguan</h3>
                    <div class="space-y-6">
                        <div>
                            <h4 class="text-md font-semibold text-center capitalize">Bintaran</h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg"><canvas id="kehadiranChartMingguan_bintaran"></canvas></div>
                        </div>
                        <div>
                            <h4 class="text-md font-semibold text-center capitalize">Gedongkuning</h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg"><canvas id="kehadiranChartMingguan_gedongkuning"></canvas></div>
                        </div>
                        <div>
                            <h4 class="text-md font-semibold text-center capitalize">Jombor</h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg"><canvas id="kehadiranChartMingguan_jombor"></canvas></div>
                        </div>
                        <div>
                            <h4 class="text-md font-semibold text-center capitalize">Sunten</h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg"><canvas id="kehadiranChartMingguan_sunten"></canvas></div>
                        </div>
                    </div>
                </div>

                <div id="statistik-rincian-display-mingguan" class="overflow-x-auto mt-8"></div>
                <div id="statistik-alpa-container-mingguan" class="mt-6"></div>
            </div>
        </div>

        <!-- Bagian 2: Analisis Admin -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">2. Analisis & Rekomendasi Mingguan (Draf oleh AI)</h2>
            <div class="space-y-4">
                <div>
                    <label for="catatan_kondisi" class="block text-sm font-medium text-gray-700">Catatan Kondisi Minggu Ini</label>
                    <textarea name="catatan_kondisi" id="catatan_kondisi" rows="5" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500" placeholder="Klik 'Tarik Data Mingguan' untuk analisis otomatis..."><?php echo htmlspecialchars($laporan_data['catatan_kondisi'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label for="rekomendasi_tindakan" class="block text-sm font-medium text-gray-700">Rekomendasi Tindakan Minggu Ini</label>
                    <textarea name="rekomendasi_tindakan" id="rekomendasi_tindakan" rows="5" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500" placeholder="Klik 'Tarik Data Mingguan' untuk analisis otomatis..."><?php echo htmlspecialchars($laporan_data['rekomendasi_tindakan'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Bagian 3: Simpan -->
        <div class="flex justify-end gap-4">
            <a href="?page=report/daftar_laporan_mingguan" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300">Batal</a>
            <button type="button" id="btn-save-draft-mingguan" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                Simpan sebagai Draft
            </button>
            <button type="button" id="btn-show-final-confirm-mingguan" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                Simpan & Finalisasi
            </button>
        </div>
    </form>
</div>

<!-- Modal Konfirmasi Final -->
<div id="modal-confirm-final-mingguan" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg font-medium text-gray-900">Konfirmasi Finalisasi Laporan Mingguan</h3>
                        <p class="text-sm text-gray-500 mt-2">Laporan mingguan yang sudah final tidak dapat diedit kembali. Yakin?</p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="btn-confirm-final-mingguan" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md sm:ml-3">Ya, Finalisasi</button>
                <button type="button" id="btn-cancel-final-mingguan" class="bg-white hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-md border border-gray-300">Batal</button>
            </div>
        </div>
    </div>
</div>

<!-- Library Chart.js & SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tanggalPilihanInput = document.getElementById('tanggal_pilihan');
        const rentangDisplay = document.getElementById('rentang-minggu-display');
        const tanggalMulaiHidden = document.getElementById('tanggal_mulai_hidden');
        const tanggalAkhirHidden = document.getElementById('tanggal_akhir_hidden');
        const btnTarikData = document.getElementById('btn-tarik-data-mingguan');
        const loader = document.getElementById('loader-statistik-mingguan');
        const errorContainer = document.getElementById('error-container-mingguan');
        const globalContainer = document.getElementById('statistik-global-display-mingguan');
        const rincianContainer = document.getElementById('statistik-rincian-display-mingguan');
        const alpaContainer = document.getElementById('statistik-alpa-container-mingguan');
        const chartContainer = document.getElementById('statistik-chart-container-mingguan');
        const hiddenJsonInput = document.getElementById('data_statistik_json');
        const formLaporan = document.getElementById('form-laporan-mingguan');
        const statusHiddenInput = document.getElementById('status_laporan_hidden');
        const btnSaveDraft = document.getElementById('btn-save-draft-mingguan');
        const btnShowFinalConfirm = document.getElementById('btn-show-final-confirm-mingguan');
        const modalConfirmFinal = document.getElementById('modal-confirm-final-mingguan');
        const btnConfirmFinal = document.getElementById('btn-confirm-final-mingguan');
        const btnCancelFinal = document.getElementById('btn-cancel-final-mingguan');

        let kehadiranChartInstancesMingguan = {};
        Chart.register(ChartDataLabels);

        const URUTAN_KELOMPOK = ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
        const URUTAN_KELAS = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];

        // Definisikan Warna Status 
        const WARNA_STATUS = {
            hadir: 'rgba(34, 197, 94, 0.7)', // Hijau
            sakit: 'rgba(59, 130, 246, 0.7)', // Biru
            izin: 'rgba(245, 158, 11, 0.7)', // Kuning
            alpa: 'rgba(239, 68, 68, 0.7)', // Merah
            kosong: 'rgba(107, 114, 128, 0.5)', // Abu sedang
        };
        // Warna border (opsional, bisa dihapus jika tidak mau border)
        const WARNA_BORDER = {
            hadir: 'rgba(22, 163, 74, 1)',
            sakit: 'rgba(37, 99, 235, 1)',
            izin: 'rgba(234, 179, 8, 1)',
            alpa: 'rgba(220, 38, 38, 1)',
            kosong: 'rgba(55, 65, 81, 0.8)', // Abu tua
        }

        // --- Fungsi Update Rentang Minggu ---
        function updateRentangMinggu() {
            const selectedDate = new Date(tanggalPilihanInput.value + 'T00:00:00');
            if (isNaN(selectedDate)) {
                rentangDisplay.textContent = 'Tanggal tidak valid';
                tanggalMulaiHidden.value = '';
                tanggalAkhirHidden.value = '';
                return;
            }
            const dayOfWeek = selectedDate.getDay();
            const diffToMonday = (dayOfWeek === 0) ? -6 : 1 - dayOfWeek;
            const monday = new Date(selectedDate);
            monday.setDate(selectedDate.getDate() + diffToMonday);
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);

            const formatDate = (date) => {
                const y = date.getFullYear();
                const m = (date.getMonth() + 1).toString().padStart(2, '0'); // getMonth() adalah 0-11
                const d = date.getDate().toString().padStart(2, '0');
                return `${y}-${m}-${d}`;
            };

            const tglMulai = formatDate(monday);
            const tglAkhir = formatDate(sunday);
            const formatDisplay = (date) => date.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short'
            });
            rentangDisplay.textContent = `${formatDisplay(monday)} - ${formatDisplay(sunday)} ${monday.getFullYear()}`;
            tanggalMulaiHidden.value = tglMulai;
            tanggalAkhirHidden.value = tglAkhir;
        }
        updateRentangMinggu();
        tanggalPilihanInput.addEventListener('change', updateRentangMinggu);

        // --- Fungsi Log ---
        function logPesanDebug(messages) {
            if (messages && Array.isArray(messages) && messages.length > 0) {
                console.warn("=== Pesan Debug dari PHP ===");
                messages.forEach(msg => console.log(msg));
                console.warn("===========================");
            }
        }

        // ==========================================================
        // ▼▼▼ FUNGSI TAMPILKAN STATISTIK (Mingguan - Grouped Bar) ▼▼▼
        // ==========================================================
        function tampilkanStatistikMingguan(data) {
            // ... (Isi Otomatis AI - tidak berubah) ...
            const catatanTextarea = document.getElementById('catatan_kondisi');
            const rekomendasiTextarea = document.getElementById('rekomendasi_tindakan');
            <?php if (!$edit_mode): ?>
                if (data.catatan_kondisi_ai) catatanTextarea.value = data.catatan_kondisi_ai;
                if (data.rekomendasi_tindakan_ai) rekomendasiTextarea.value = data.rekomendasi_tindakan_ai;
            <?php else: ?>
                if (!catatanTextarea.value && data.catatan_kondisi_ai) catatanTextarea.value = data.catatan_kondisi_ai;
                if (!rekomendasiTextarea.value && data.rekomendasi_tindakan_ai) rekomendasiTextarea.value = data.rekomendasi_tindakan_ai;
            <?php endif; ?>

            // ... (Tampilkan Global - tidak berubah) ...
            let globalHtml = '<h3 class="text-lg font-semibold text-gray-800">Ringkasan Mingguan</h3><ul class="list-disc list-inside pl-2 text-gray-700 grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2">';
            globalHtml += `<li>Total Jadwal: <strong>${data.global?.total_jadwal_minggu_ini ?? 0}</strong></li>`;
            globalHtml += `<li>Presensi Terisi: <strong>${data.global?.total_presensi_terisi ?? 0}</strong></li>`;
            globalHtml += `<li>Jurnal Terisi: <strong>${data.global?.total_jurnal_terisi ?? 0}</strong></li>`;
            globalHtml += `<li>Jadwal Terlewat: <strong>${data.global?.jadwal_terlewat ?? 0}</strong></li>`;
            globalHtml += `<li class="text-green-600">Total Hadir: <strong>${data.global?.total_siswa_hadir ?? 0}</strong></li>`;
            globalHtml += `<li class="text-blue-600">Total Izin: <strong>${data.global?.total_siswa_izin ?? 0}</strong></li>`;
            globalHtml += `<li class="text-yellow-600">Total Sakit: <strong>${data.global?.total_siswa_sakit ?? 0}</strong></li>`;
            globalHtml += `<li class="text-red-600">Total Alpa: <strong>${data.global?.total_siswa_alpa ?? 0}</strong></li>`;
            globalHtml += '</ul>';
            globalContainer.innerHTML = globalHtml;


            // ... (Tampilkan Rincian - tidak berubah) ...
            let rincianHtml = '<h3 class="text-lg font-semibold text-gray-800 mt-4">Rincian Mingguan per Kelas</h3>';
            rincianHtml += '<table class="min-w-full divide-y divide-gray-200 text-sm mt-2">';
            // Header tabel tambah 'Kosong'
            rincianHtml += '<thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left font-medium text-gray-500">Kelompok</th><th class="px-4 py-2 text-left font-medium text-gray-500">Kelas</th><th class="px-4 py-2 text-center font-medium text-gray-500">Hadir</th><th class="px-4 py-2 text-center font-medium text-gray-500">Izin</th><th class="px-4 py-2 text-center font-medium text-gray-500">Sakit</th><th class="px-4 py-2 text-center font-medium text-gray-500">Alpa</th><th class="px-4 py-2 text-center font-medium text-gray-500">Kosong</th><th class="px-4 py-2 text-center font-medium text-gray-500">Presensi Terisi</th><th class="px-4 py-2 text-center font-medium text-gray-500">Jurnal Terisi</th></tr></thead>';
            rincianHtml += '<tbody class="bg-white divide-y divide-gray-200">';
            let hasRincian = false;
            if (data.rincian_per_kelompok && Object.keys(data.rincian_per_kelompok).length > 0) {
                URUTAN_KELOMPOK.forEach(kelompok => {
                    URUTAN_KELAS.forEach(kelas => {
                        const d = data.rincian_per_kelompok?.[kelompok]?.[kelas];
                        if (d) {
                            hasRincian = true;
                            const totalJadwalMinggu = d.total_jadwal_minggu || 0;
                            const hadir = d.hadir || 0;
                            const izin = d.izin || 0;
                            const sakit = d.sakit || 0;
                            const alpa = d.alpa || 0;
                            const presensiTerisi = d.jadwal_presensi_terisi || 0;
                            const jurnalTerisi = d.jadwal_jurnal_terisi || 0;
                            // Ambil 'kosong' dari data jika ada, jika tidak hitung
                            const kosong = d.kosong !== undefined ? (d.kosong || 0) : Math.max(0, totalJadwalMinggu - (hadir + izin + sakit + alpa));


                            if (totalJadwalMinggu === 0) {
                                rincianHtml += '<tr class="bg-gray-50">';
                                rincianHtml += `<td class="px-4 py-2 font-medium capitalize text-gray-500">${kelompok}</td><td class="px-4 py-2 capitalize text-gray-500">${kelas}</td>`;
                                // colspan jadi 7
                                rincianHtml += `<td colspan="7" class="px-4 py-2 text-center text-gray-400 italic">Tidak ada jadwal</td>`;
                                rincianHtml += '</tr>';
                            } else {
                                rincianHtml += '<tr>';
                                rincianHtml += `<td class="px-4 py-2 font-medium capitalize">${kelompok}</td><td class="px-4 py-2 capitalize">${kelas}</td>`;
                                rincianHtml += `<td class="px-4 py-2 text-center text-green-600 font-semibold">${hadir}</td>`;
                                rincianHtml += `<td class="px-4 py-2 text-center text-yellow-600 font-semibold">${izin}</td>`;
                                rincianHtml += `<td class="px-4 py-2 text-center text-blue-600 font-semibold">${sakit}</td>`;
                                rincianHtml += `<td class="px-4 py-2 text-center text-red-600 font-semibold">${alpa}</td>`;
                                // Tampilkan TD untuk 'kosong'
                                rincianHtml += `<td class="px-4 py-2 text-center text-gray-500 font-semibold">${kosong}</td>`;
                                rincianHtml += `<td class="px-4 py-2 text-center ${presensiTerisi < totalJadwalMinggu ? 'text-red-500' : ''}">${presensiTerisi}/${totalJadwalMinggu}</td>`;
                                rincianHtml += `<td class="px-4 py-2 text-center ${jurnalTerisi < totalJadwalMinggu ? 'text-red-500' : ''}">${jurnalTerisi}/${totalJadwalMinggu}</td>`;
                                rincianHtml += '</tr>';
                            }
                        }
                    });
                });
            }
            if (!hasRincian) {
                // colspan jadi 9
                rincianHtml += '<tr><td colspan="9" class="text-center py-4 text-gray-500">Tidak ada data rincian ditemukan.</td></tr>';
            }
            rincianHtml += '</tbody></table>';
            rincianContainer.innerHTML = rincianHtml;

            // ... (Tampilkan Daftar Alpa - tidak berubah) ...
            const alpaContainer = document.getElementById('statistik-alpa-container-mingguan');
            let alpaHtml = '';
            if (data.daftar_alpa && data.daftar_alpa.length > 0) {
                alpaHtml = '<h3 class="text-lg font-semibold text-gray-800 mt-4 text-red-600">Daftar Siswa Alpa Minggu Ini</h3>';
                alpaHtml += '<p class="text-sm text-gray-600 mb-2">Siswa yang tercatat Alpa minimal satu kali selama seminggu.</p>';
                alpaHtml += '<table class="min-w-full divide-y divide-gray-200 text-sm mt-2">';
                alpaHtml += '<thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left font-medium text-gray-500">Nama Siswa</th><th class="px-4 py-2 text-left font-medium text-gray-500">Kelompok</th><th class="px-4 py-2 text-left font-medium text-gray-500">Kelas</th><th class="px-4 py-2 text-center font-medium text-gray-500">Jml Alpa</th><th class="px-4 py-2 text-left font-medium text-gray-500">Nama Ortu</th><th class="px-4 py-2 text-left font-medium text-gray-500">No. HP Ortu</th></tr></thead>';
                alpaHtml += '<tbody class="bg-white divide-y divide-gray-200">';
                data.daftar_alpa.forEach(siswa => {
                    alpaHtml += `<tr><td class="px-4 py-2 font-medium">${siswa.nama_lengkap || ''}</td><td class="px-4 py-2 capitalize">${siswa.kelompok || ''}</td><td class="px-4 py-2 capitalize">${siswa.kelas || ''}</td><td class="px-4 py-2 text-center text-red-600 font-bold">${siswa.jumlah_alpa || 0}</td><td class="px-4 py-2">${siswa.nama_orang_tua || '-'}</td><td class="px-4 py-2">${siswa.nomor_hp_orang_tua || '-'}</td></tr>`;
                });
                alpaHtml += '</tbody></table>';
            } else {
                alpaHtml = '<h3 class="text-lg font-semibold text-gray-800 mt-4">Daftar Siswa Alpa Minggu Ini</h3>';
                alpaHtml += '<p class="text-sm text-gray-600 mt-2 p-4 border rounded-md bg-green-50 text-green-700">Tidak ada siswa alpa minggu ini.</p>';
            }
            alpaContainer.innerHTML = alpaHtml;

            // ==========================================================
            // ▼▼▼ PERUBAHAN BESAR: Logika Grafik Grouped Bar ▼▼▼
            // ==========================================================

            // Cek jika data rincian ada
            if (!data.rincian_per_kelompok || Object.keys(data.rincian_per_kelompok).length === 0) {
                chartContainer.classList.add('hidden');
                Object.values(kehadiranChartInstancesMingguan).forEach(chart => {
                    if (chart) chart.destroy();
                });
                kehadiranChartInstancesMingguan = {};
                return;
            }
            chartContainer.classList.remove('hidden');

            // Loop per kelompok
            URUTAN_KELOMPOK.forEach(kelompok => {
                // Hancurkan chart lama
                if (kehadiranChartInstancesMingguan[kelompok]) {
                    kehadiranChartInstancesMingguan[kelompok].destroy();
                }

                const canvasId = 'kehadiranChartMingguan_' + kelompok; // ID Canvas Mingguan
                const ctx = document.getElementById(canvasId);

                if (ctx && data.rincian_per_kelompok[kelompok]) {
                    const kelompokData = data.rincian_per_kelompok[kelompok];
                    const chartLabels = [];
                    // Siapkan array data untuk setiap status
                    const dataHadir = [],
                        dataSakit = [],
                        dataIzin = [],
                        dataAlpa = [],
                        dataKosong = [];

                    // Loop per kelas sesuai urutan
                    URUTAN_KELAS.forEach(kelas => {
                        chartLabels.push(kelas.charAt(0).toUpperCase() + kelas.slice(1)); // Label kelas

                        const d = kelompokData[kelas] ?? null;
                        let totalJadwalMinggu = 0; // Total jadwal seharusnya ada
                        let totalEntri = 0; // Tidak perlu lagi, pakai totalJadwalMinggu
                        let hadir = 0,
                            izin = 0,
                            sakit = 0,
                            alpa = 0,
                            kosong = 0;

                        // =============================================
                        // ▼▼▼ PERBAIKAN Logika Pengambilan Data ▼▼▼
                        // =============================================
                        if (d) {
                            totalJadwalMinggu = d.total_jadwal_minggu || 0;
                            totalEntri = (d.hadir + d.izin + d.sakit + d.alpa + d.kosong);
                            // Hanya ambil data jika ADA jadwal minggu ini
                            if (totalJadwalMinggu > 0) {
                                hadir = d.hadir || 0;
                                izin = d.izin || 0;
                                sakit = d.sakit || 0;
                                alpa = d.alpa || 0;
                                kosong = d.kosong || 0; // Ambil 'kosong' dari PHP
                            }
                        }
                        // Jika d == null ATAU totalJadwalMinggu == 0, 
                        // semua nilai (hadir, izin, sakit, alpa, kosong) akan tetap 0.
                        // =============================================
                        // ▲▲▲ AKHIR PERBAIKAN ▲▲▲
                        // =============================================


                        // =============================================
                        // ▼▼▼ PERBAIKAN Perhitungan Persentase ▼▼▼
                        // =============================================
                        // Pembagi sekarang SELALU totalJadwalMinggu (total jadwal yg seharusnya ada)
                        // Jika totalJadwalMinggu = 0 (tidak ada jadwal sama sekali), hasil = null (N/A)
                        const persen = (val) => totalJadwalMinggu > 0 ? parseFloat(((val / totalEntri) * 100).toFixed(1)) : null;

                        dataHadir.push(persen(hadir));
                        dataSakit.push(persen(sakit));
                        dataIzin.push(persen(izin));
                        dataAlpa.push(persen(alpa));
                        dataKosong.push(persen(kosong)); // Kosong juga pakai totalJadwalMinggu
                        // =============================================
                        // ▲▲▲ AKHIR PERBAIKAN ▲▲▲
                        // =============================================

                    }); // Akhir loop kelas

                    // Buat Chart Grouped Bar Baru
                    kehadiranChartInstancesMingguan[kelompok] = new Chart(ctx, {
                        type: 'bar', // Grouped bar
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                    label: 'Hadir',
                                    data: dataHadir,
                                    backgroundColor: WARNA_STATUS.hadir,
                                },
                                {
                                    label: 'Izin',
                                    data: dataIzin,
                                    backgroundColor: WARNA_STATUS.izin
                                },
                                {
                                    label: 'Sakit',
                                    data: dataSakit,
                                    backgroundColor: WARNA_STATUS.sakit
                                },
                                {
                                    label: 'Alpa',
                                    data: dataAlpa,
                                    backgroundColor: WARNA_STATUS.alpa
                                },
                                {
                                    label: 'Kosong',
                                    data: dataKosong,
                                    backgroundColor: WARNA_STATUS.kosong
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    max: 100, // Tetap max 100%         
                                    ticks: {
                                        callback: function(value) {
                                            return value + "%"
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 12,
                                        padding: 10,
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            const value = context.parsed.y;
                                            // Handle null untuk N/A
                                            if (value === null || isNaN(value)) {
                                                return label + 'N/A';
                                            }
                                            return label + value.toFixed(1) + '%';
                                        }
                                    }
                                },
                                datalabels: {
                                    display: true, // Selalu coba tampilkan
                                    anchor: 'end',
                                    // align: 'top',
                                    align: (value, context) => {
                                        if (value === null) return 'center';
                                        return value >= 90 ? 'bottom' : 'top';
                                    },
                                    offset: -5, // Jarak sedikit di atas bar
                                    font: {
                                        weight: 'bold',
                                        size: 10 // PERBESAR UKURAN FONT
                                    },
                                    // Formatter untuk menampilkan nilai + '%' atau null jika 0/null
                                    formatter: (value, context) => {
                                        // Jika nilainya null (tidak ada jadwal), tampilkan N/A
                                        if (value === null) {
                                            return 'N/A';
                                        }
                                        // Jika nilainya > 0, tampilkan persentase bulat
                                        if (value >= 0) {
                                            return Math.round(value) + '%';
                                        }
                                        // Jika nilainya 0, jangan tampilkan apa-apa (return null atau string kosong)
                                        // return null;
                                    },
                                    // Warna teks: Putih jika di bar 'Kosong', Abu2 jika 'N/A', Hitam jika lainnya
                                    color: (context) => {
                                        // Safety check
                                        if (!context || context.datasetIndex === undefined || !context.dataset || !context.dataset.data || context.dataIndex === undefined) {
                                            return '#000'; // Default hitam
                                        }
                                        const value = context.dataset.data[context.dataIndex];
                                        if (value === null) return '#9ca3af'; // N/A abu-abu
                                        // Putih jika di bar kosong (index 4)
                                        return context.datasetIndex === 4 ? '#000' : '#000';
                                    },
                                    display: function(context) {
                                        // Ambil nilai mentah dari data
                                        const value = context.dataset.data[context.dataIndex];
                                    }
                                }
                            }
                        }
                    }); // Akhir new Chart
                } else if (ctx) {
                    // Tampilkan pesan N/A jika tidak ada data kelompok
                    const context = ctx.getContext('2d');
                    context.clearRect(0, 0, ctx.width, ctx.height);
                    context.font = "14px Arial";
                    context.fillStyle = "#9ca3af";
                    context.textAlign = "center";
                    context.fillText("Data tidak tersedia", ctx.width / 2, ctx.height / 2);
                }
            }); // Akhir loop kelompok
            // ==========================================================
            // ▲▲▲ AKHIR PERUBAHAN GRAFIK ▲▲▲
            // ==========================================================
        } // Akhir fungsi tampilkanStatistikMingguan

        // --- Fetch Event Listener (tidak berubah) ---
        btnTarikData.addEventListener('click', function() {
            const tglMulai = tanggalMulaiHidden.value;
            const tglAkhir = tanggalAkhirHidden.value;
            if (!tglMulai || !tglAkhir) {
                Swal.fire('Info', 'Rentang tanggal minggu tidak valid.', 'info');
                return;
            }
            loader.classList.remove('hidden');
            errorContainer.classList.add('hidden');
            globalContainer.innerHTML = '';
            rincianContainer.innerHTML = '';
            alpaContainer.innerHTML = '';
            chartContainer.classList.add('hidden');
            btnTarikData.disabled = true;

            fetch(`pages/report/ajax_get_stats_mingguan.php?tanggal_mulai=${tglMulai}&tanggal_akhir=${tglAkhir}`)
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errorData => {
                            const errorMessage = errorData.error || `Error ${response.status}`;
                            const errorLogs = errorData.debug_logs || [];
                            const error = new Error(errorMessage);
                            error.logs = errorLogs;
                            throw error;
                        }).catch((parseError) => {
                            const error = new Error(`Respon server tidak valid (${response.status})`);
                            error.logs = ["[JS] Gagal parse JSON dari respons error server."];
                            throw error;
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("[JS] Data mingguan diterima:", data);
                    logPesanDebug(data.debug_logs);
                    hiddenJsonInput.value = JSON.stringify(data);
                    tampilkanStatistikMingguan(data);
                })
                .catch(error => {
                    console.error('[JS] Error saat fetch:', error);
                    errorContainer.innerHTML = `Terjadi kesalahan: ${error.message}`;
                    errorContainer.classList.remove('hidden');
                    hiddenJsonInput.value = '{}';
                    if (error.logs) {
                        logPesanDebug(error.logs);
                    }
                })
                .finally(() => {
                    loader.classList.add('hidden');
                    btnTarikData.disabled = false;
                });
        });

        // --- Jika mode EDIT, tarik data lama (tidak berubah) ---
        <?php if ($edit_mode && !empty($laporan_data['data_statistik'])): ?>
            try {
                const dataLama = JSON.parse(<?php echo json_encode($laporan_data['data_statistik']); ?>);
                if (dataLama && dataLama.global) {
                    console.log("[JS] Memuat data statistik mingguan lama:", dataLama);
                    logPesanDebug(dataLama.debug_logs);
                    // Tampilkan rentang tanggal dari data lama
                    const tglMulaiLama = new Date(dataLama.global.tanggal_mulai + 'T00:00:00');
                    const tglAkhirLama = new Date(dataLama.global.tanggal_akhir + 'T00:00:00');
                    const formatDisplay = (date) => date.toLocaleDateString('id-ID', {
                        day: 'numeric',
                        month: 'short'
                    });
                    rentangDisplay.textContent = `${formatDisplay(tglMulaiLama)} - ${formatDisplay(tglAkhirLama)} ${tglMulaiLama.getFullYear()}`;
                    tampilkanStatistikMingguan(dataLama);
                } else {
                    console.warn("[JS] Data statistik lama (mingguan) tidak valid.");
                }
            } catch (e) {
                console.error("Gagal memuat data lama:", e);
            }
        <?php endif; ?>

        // --- Logika Modal Konfirmasi Final (tidak berubah) ---
        function showConfirmModalMingguan() {
            if (modalConfirmFinal) modalConfirmFinal.classList.remove('hidden');
        }

        function hideConfirmModalMingguan() {
            if (modalConfirmFinal) modalConfirmFinal.classList.add('hidden');
        }
        if (btnShowFinalConfirm) {
            btnShowFinalConfirm.addEventListener('click', showConfirmModalMingguan);
        }
        if (btnCancelFinal) {
            btnCancelFinal.addEventListener('click', hideConfirmModalMingguan);
        }
        if (modalConfirmFinal) {
            modalConfirmFinal.addEventListener('click', function(event) {
                if (event.target === modalConfirmFinal) {
                    hideConfirmModalMingguan();
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