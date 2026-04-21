<?php
// === FILE FRONTEND: jadwal.php ===
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// === AMBIL DATA PERIODE ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode, tanggal_mulai, tanggal_selesai FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// === TENTUKAN PERIODE DEFAULT ===
$default_periode_id = null;
$today = date('Y-m-d');
foreach ($periode_list as $p) {
    if ($today >= $p['tanggal_mulai'] && $today <= $p['tanggal_selesai']) {
        $default_periode_id = $p['id'];
        break;
    }
}
if ($default_periode_id === null && !empty($periode_list)) {
    $default_periode_id = $periode_list[0]['id'];
}

// === AMBIL FILTER DARI URL ===
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : $default_periode_id;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
}

$selected_periode_nama = '';
$selected_periode_tanggal_mulai = '';
$selected_periode_tanggal_selesai = '';

if ($selected_periode_id) {
    $stmt_periode = $conn->prepare("SELECT nama_periode, tanggal_mulai, tanggal_selesai FROM periode WHERE id = ?");
    $stmt_periode->bind_param("i", $selected_periode_id);
    $stmt_periode->execute();
    $result_periode = $stmt_periode->get_result();
    if ($result_periode->num_rows > 0) {
        $periode_data = $result_periode->fetch_assoc();
        $selected_periode_nama = $periode_data['nama_periode'];
        $selected_periode_tanggal_mulai = $periode_data['tanggal_mulai'];
        $selected_periode_tanggal_selesai = $periode_data['tanggal_selesai'];
    }
}

// === AMBIL DATA MASTER UNTUK MODAL ===
$penasehat_options = [];
$res_penasehat = $conn->query("SELECT id, nama FROM penasehat ORDER BY nama ASC");
if ($res_penasehat) {
    while ($row = $res_penasehat->fetch_assoc()) {
        $penasehat_options[] = $row;
    }
}

$guru_options = [];
if ($selected_kelompok !== 'semua' && $selected_kelas !== 'semua') {
    $sql_guru_opts = "SELECT DISTINCT g.id, g.nama FROM guru g JOIN pengampu p ON g.id = p.id_guru WHERE g.kelompok = ? AND p.nama_kelas = ? AND g.deleted_at IS NULL ORDER BY g.nama ASC";
    $stmt_guru_opts = $conn->prepare($sql_guru_opts);
    $stmt_guru_opts->bind_param("ss", $selected_kelompok, $selected_kelas);
    $stmt_guru_opts->execute();
    $result_guru_opts = $stmt_guru_opts->get_result();
    if ($result_guru_opts) {
        while ($row = $result_guru_opts->fetch_assoc()) {
            $guru_options[] = $row;
        }
    }
}

// === AMBIL DATA PENGATURAN PENGINGAT (WA) ===
$jam_pengingat = 4; // Nilai Default jika belum diatur
if ($selected_kelompok !== 'semua' && $selected_kelas !== 'semua') {
    $stmt_aturan = $conn->prepare("SELECT waktu_pengingat_jam FROM pengaturan_pengingat WHERE kelompok = ? AND kelas = ?");
    if ($stmt_aturan) {
        $stmt_aturan->bind_param("ss", $selected_kelompok, $selected_kelas);
        $stmt_aturan->execute();
        $res_aturan = $stmt_aturan->get_result();
        if ($res_aturan->num_rows > 0) {
            $jam_pengingat = (int)$res_aturan->fetch_assoc()['waktu_pengingat_jam'];
        }
    }
}
?>

<!-- CSS Tambahan untuk Loading Spinner -->
<style>
    .table-container {
        position: relative;
    }
</style>

<div class="container mx-auto space-y-6">
    <!-- FILTER -->
    <div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Pengaturan Jadwal & Petugas</h3>
        <form method="GET" action="" id="filterForm">
            <input type="hidden" name="page" value="presensi/jadwal">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium">Periode</label>
                    <select id="filter_periode_id" name="periode_id" class="mt-1 block w-full py-2.5 px-3 border border-gray-300 rounded-md" required>
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?> data-mulai="<?php echo $p['tanggal_mulai']; ?>" data-selesai="<?php echo $p['tanggal_selesai']; ?>">
                                <?php echo htmlspecialchars($p['nama_periode']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 py-2.5 px-3 rounded-md" disabled>
                        <input id="filter_kelompok" type="hidden" name="kelompok" value="<?php echo $admin_kelompok; ?>">
                    <?php else: ?>
                        <select id="filter_kelompok" name="kelompok" class="mt-1 block w-full py-2.5 px-3 border border-gray-300 rounded-md" required>
                            <option value="semua" <?php echo ($selected_kelompok == 'semua') ? 'selected' : ''; ?>>-- Pilih Kelompok --</option>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium">Kelas</label>
                    <select id="filter_kelas" name="kelas" class="mt-1 block w-full py-2.5 px-3 border border-gray-300 rounded-md" required>
                        <option value="semua" <?php echo ($selected_kelas == 'semua') ? 'selected' : ''; ?>>-- Pilih Kelas --</option>
                        <?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                        foreach ($kelas_opts as $k): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-lg shadow transition">Tampilkan</button></div>
            </div>
        </form>
    </div>

    <?php if ($selected_periode_id && $selected_kelompok !== 'semua' && $selected_kelas !== 'semua'): ?>

        <!-- ========================================== -->
        <!-- TABEL 1: PENGATURAN GURU & PENASEHAT (CRUD) -->
        <!-- ========================================== -->
        <div class="bg-white p-4 md:p-6 rounded-lg shadow-md overflow-x-auto table-container border-t-4 border-indigo-500">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                <div>
                    <h3 class="text-lg md:text-xl font-bold text-gray-800">Atur Guru & Penasehat</h3>
                </div>
                <button id="btnBukaModalTambahJadwal" class="w-full md:w-auto bg-green-500 hover:bg-green-600 text-white font-bold py-2.5 md:py-2 px-4 rounded-lg shadow transition flex justify-center items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Tambah Jadwal
                </button>
            </div>

            <!-- DESKTOP VIEW (Table) -->
            <div class="hidden md:block overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Waktu</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase w-1/4">Guru</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase w-1/4">Penasehat</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase">Opsi</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_crud_jadwal" class="bg-white divide-y divide-gray-200">
                        <!-- Data akan dirender via AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- MOBILE VIEW (Card Grid) -->
            <div id="mobile_crud_jadwal" class="block md:hidden space-y-4">
                <!-- Data akan dirender via AJAX -->
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TABEL 2: REKAPITULASI & STATUS JURNAL (VIEW) -->
        <!-- ========================================== -->
        <div class="border-t-4 border-indigo-500 bg-white p-4 md:p-6 rounded-lg shadow-md overflow-x-auto table-container">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                <div>
                    <h3 class="text-lg md:text-xl font-bold text-gray-800">Jadwal Guru dan Penasehat</h3>
                    <p class="text-xs md:text-sm text-gray-600 mt-1">
                        Periode: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($selected_periode_nama); ?></span> |
                        <span class="capitalize font-semibold text-gray-800"><?php echo htmlspecialchars($selected_kelompok); ?> - <?php echo htmlspecialchars($selected_kelas); ?></span>
                    </p>
                </div>
                <form id="formExportJadwal" class="w-full md:w-auto">
                    <input type="hidden" name="periode_id" value="<?php echo $selected_periode_id; ?>">
                    <input type="hidden" name="kelompok" value="<?php echo $selected_kelompok; ?>">
                    <input type="hidden" name="kelas" value="<?php echo $selected_kelas; ?>">
                    <button type="submit" class="w-full md:w-auto bg-red-500 hover:bg-red-600 text-white font-bold py-2.5 md:py-2 px-4 rounded-lg shadow transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-file-pdf"></i> Export PDF
                    </button>
                </form>
            </div>

            <!-- DESKTOP VIEW -->
            <div class="hidden md:block overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase w-16">No</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase w-1/5">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase w-1/4">Guru</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase w-1/4">Penasehat</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase">Status Jurnal</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_rekap_jadwal" class="bg-white divide-y divide-gray-200">
                        <!-- Data akan dirender via AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- MOBILE VIEW -->
            <div id="mobile_rekap_jadwal" class="block md:hidden space-y-3">
                <!-- Data akan dirender via AJAX -->
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================== -->
<!-- SEMUA MODAL DISINI -->
<!-- ========================================== -->

<!-- Modal Tambah Jadwal -->
<div id="modalTambahJadwal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
        <div class="bg-white rounded-xl overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-10">
            <form id="formTambahJadwal">
                <input type="hidden" name="action" value="tambah_jadwal">
                <input type="hidden" name="periode_id" value="<?php echo $selected_periode_id; ?>">
                <input type="hidden" name="kelompok" value="<?php echo $selected_kelompok; ?>">
                <input type="hidden" name="kelas" value="<?php echo $selected_kelas; ?>">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Tambah Jadwal Baru</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal*</label>
                            <input type="date" id="inputTambahTanggal" name="tanggal" class="mt-1 block w-full border border-gray-300 py-2 px-3 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jam Mulai*</label>
                                <input type="time" name="jam_mulai" class="mt-1 block w-full border border-gray-300 py-2 px-3 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jam Selesai*</label>
                                <input type="time" name="jam_selesai" class="mt-1 block w-full border border-gray-300 py-2 px-3 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse border-t border-gray-100">
                    <button type="submit" class="w-full inline-flex justify-center rounded-lg shadow-sm px-6 py-2.5 bg-green-600 text-sm font-bold text-white hover:bg-green-700 sm:ml-3 sm:w-auto transition">Simpan</button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-lg shadow-sm px-6 py-2.5 bg-white border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto transition btn-tutup-modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Jadwal -->
<div id="modalEditJadwal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
        <div class="bg-white rounded-xl overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-10">
            <form id="formEditJadwal">
                <input type="hidden" name="action" value="edit_jadwal">
                <input type="hidden" name="jadwal_id" id="edit_jadwal_id">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Edit Jadwal</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                            <input type="date" name="tanggal" id="edit_tanggal" class="mt-1 block w-full border border-gray-300 py-2 px-3 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed" readonly>
                            <p class="text-[11px] text-red-500 mt-1">*Tanggal tidak dapat diubah untuk menjaga integritas data.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jam Mulai*</label>
                                <input type="time" name="jam_mulai" id="edit_jam_mulai" class="mt-1 block w-full border border-gray-300 py-2 px-3 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jam Selesai*</label>
                                <input type="time" name="jam_selesai" id="edit_jam_selesai" class="mt-1 block w-full border border-gray-300 py-2 px-3 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse border-t border-gray-100">
                    <button type="submit" class="w-full inline-flex justify-center rounded-lg shadow-sm px-6 py-2.5 bg-blue-600 text-sm font-bold text-white hover:bg-blue-700 sm:ml-3 sm:w-auto transition">Update</button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-lg shadow-sm px-6 py-2.5 bg-white border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto transition btn-tutup-modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Atur Guru -->
<div id="modalAturGuru" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
        <div class="bg-white rounded-xl overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-10">
            <form id="formAturGuru">
                <input type="hidden" name="action" value="tambah_guru_jadwal">
                <input type="hidden" name="jadwal_id" id="guru_jadwal_id">
                <input type="hidden" name="jam_mulai_pengingat" id="guru_jam_mulai">
                <input type="hidden" name="jam_selesai_pengingat" id="guru_jam_selesai">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Tugaskan Guru</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pilih Guru*</label>
                            <select name="guru_id" class="mt-1 block w-full border border-gray-300 py-2.5 px-3 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($guru_options as $g): ?>
                                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bg-cyan-50 p-3 rounded-lg border border-cyan-100 mt-4 flex items-start gap-2">
                            <i class="fa-solid fa-circle-info text-cyan-600 mt-0.5"></i>
                            <p class="text-xs text-gray-700 leading-tight">Pengingat WA akan dikirim ke guru tersebut pada <strong class="text-cyan-800" id="guru_info_waktu"></strong>.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse border-t border-gray-100">
                    <button type="submit" class="w-full inline-flex justify-center rounded-lg shadow-sm px-6 py-2.5 bg-indigo-600 text-sm font-bold text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto transition">Tugaskan</button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-lg shadow-sm px-6 py-2.5 bg-white border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto transition btn-tutup-modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Atur Penasehat -->
<div id="modalAturPenasehat" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
        <div class="bg-white rounded-xl overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-10">
            <form id="formAturPenasehat">
                <input type="hidden" name="action" value="tambah_penasehat_jadwal">
                <input type="hidden" name="jadwal_id" id="penasehat_jadwal_id">
                <input type="hidden" name="jam_mulai_pengingat" id="penasehat_jam_mulai">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Tugaskan Penasehat</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pilih Penasehat*</label>
                            <select name="penasehat_id" class="mt-1 block w-full border border-gray-300 py-2.5 px-3 rounded-lg focus:ring-yellow-500 focus:border-yellow-500" required>
                                <option value="">-- Pilih Penasehat --</option>
                                <?php foreach ($penasehat_options as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100 mt-4 flex items-start gap-2">
                            <i class="fa-solid fa-circle-info text-yellow-600 mt-0.5"></i>
                            <p class="text-xs text-gray-700 leading-tight">Pengingat WA akan dikirim ke penasehat pada <strong class="text-yellow-800" id="penasehat_info_waktu"></strong>.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse border-t border-gray-100">
                    <button type="submit" class="w-full inline-flex justify-center rounded-lg shadow-sm px-6 py-2.5 bg-yellow-500 text-sm font-bold text-white hover:bg-yellow-600 sm:ml-3 sm:w-auto transition">Tugaskan</button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-lg shadow-sm px-6 py-2.5 bg-white border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto transition btn-tutup-modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const API_URL = 'pages/presensi/ajax_jadwal.php';
        const filterData = {
            periode_id: '<?php echo $selected_periode_id; ?>',
            kelompok: '<?php echo $selected_kelompok; ?>',
            kelas: '<?php echo $selected_kelas; ?>'
        };

        // Variabel PHP untuk pengaturan jam pengingat WA
        const jamPengingatWa = <?php echo $jam_pengingat; ?>;

        // Helper: Sembunyikan Overlay Global setelah AJAX Submit selesai
        const hideGlobalOverlay = () => {
            const indexOverlay = document.getElementById('loading-overlay');
            if (indexOverlay) {
                indexOverlay.classList.remove('show');
            }
        };

        // Helper: Fungsi Pengurangan Jam
        function subtractHours(timeStr, hoursToSubtract) {
            if (!timeStr) return '';
            const [h, m] = timeStr.split(':');
            let date = new Date();
            date.setHours(parseInt(h, 10));
            date.setMinutes(parseInt(m, 10));
            date.setHours(date.getHours() - hoursToSubtract);
            return date.getHours().toString().padStart(2, '0') + ':' + date.getMinutes().toString().padStart(2, '0');
        }

        // --- FUNGSI LOAD DATA AJAX ---
        function loadDataJadwal() {
            if (!filterData.periode_id || filterData.kelompok === 'semua' || filterData.kelas === 'semua') return;

            fetch(`${API_URL}?action=get_data&periode_id=${filterData.periode_id}&kelompok=${filterData.kelompok}&kelas=${filterData.kelas}`)
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                    return res.json();
                })
                .then(res => {
                    if (res.status === 'success') {
                        renderTabelCRUD(res.data.jadwal_crud);
                        renderTabelRekap(res.data.jadwal_rekap);
                    } else {
                        Swal.fire('Error', res.message || 'Gagal memuat data dari server.', 'error');
                    }
                })
                .catch(err => {
                    console.error("Fetch Error:", err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Memuat Data',
                        text: 'Terjadi kesalahan sistem: ' + err.message,
                        footer: '<a href="#">Silakan cek console browser (F12) untuk detail error.</a>'
                    });
                })
                .finally(() => {
                    hideGlobalOverlay();
                });
        }

        // --- RENDER TABEL 1 (CRUD) ---
        function renderTabelCRUD(data) {
            const tbody = document.getElementById('tbody_crud_jadwal');
            const mobileContainer = document.getElementById('mobile_crud_jadwal');
            if (tbody) tbody.innerHTML = '';
            if (mobileContainer) mobileContainer.innerHTML = '';

            if (data.length === 0) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center py-6 text-sm text-gray-500">Belum ada jadwal.</td></tr>';
                if (mobileContainer) mobileContainer.innerHTML = '<div class="text-center py-8 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg">Belum ada jadwal.</div>';
                return;
            }

            data.forEach(item => {
                // Render HTML Guru (Desktop & Mobile)
                let guruHtml = '';
                let guruMobileHtml = '';
                if (item.guru.length === 0) {
                    guruHtml = `<span class="text-gray-400 italic text-xs">Belum ada guru</span>`;
                    guruMobileHtml = `<span class="text-gray-400 italic text-[11px] block mt-1">Kosong</span>`;
                } else {
                    item.guru.forEach(g => {
                        guruHtml += `
                        <div class="flex items-center justify-between group text-[13px] border-b border-gray-100 py-1.5 last:border-0">
                            <span class="text-blue-900 font-medium">${g.nama}</span>
                            <button class="text-red-500 hover:text-red-700 text-[10px] font-bold opacity-0 group-hover:opacity-100 btn-hapus-petugas transition-opacity" 
                                data-tipe="guru" data-jadwal="${item.id}" data-petugas="${g.id}">[Hapus]</button>
                        </div>`;
                        guruMobileHtml += `
                        <div class="flex items-center justify-between text-[11px] border-b border-blue-100/50 py-1.5 last:border-0 mt-1">
                            <span class="font-medium text-blue-900 leading-tight pr-1">${g.nama}</span>
                            <button class="text-red-500 bg-red-50 hover:bg-red-100 px-1.5 py-0.5 rounded text-[10px] font-bold btn-hapus-petugas shrink-0 transition" 
                                data-tipe="guru" data-jadwal="${item.id}" data-petugas="${g.id}"><i class="fa-solid fa-xmark"></i></button>
                        </div>`;
                    });
                }

                // Render HTML Penasehat (Desktop & Mobile)
                let penasehatHtml = '';
                let penasehatMobileHtml = '';
                if (item.penasehat.length === 0) {
                    penasehatHtml = `<span class="text-gray-400 italic text-xs">Belum ada penasehat</span>`;
                    penasehatMobileHtml = `<span class="text-gray-400 italic text-[11px] block mt-1">Kosong</span>`;
                } else {
                    item.penasehat.forEach(p => {
                        penasehatHtml += `
                        <div class="flex items-center justify-between group text-[13px] border-b border-gray-100 py-1.5 last:border-0">
                            <span class="text-green-900 font-medium">${p.nama}</span>
                            <button class="text-red-500 hover:text-red-700 text-[10px] font-bold opacity-0 group-hover:opacity-100 btn-hapus-petugas transition-opacity" 
                                data-tipe="penasehat" data-jadwal="${item.id}" data-petugas="${p.id}">[Hapus]</button>
                        </div>`;
                        penasehatMobileHtml += `
                        <div class="flex items-center justify-between text-[11px] border-b border-green-100/50 py-1.5 last:border-0 mt-1">
                            <span class="font-medium text-green-900 leading-tight pr-1">${p.nama}</span>
                            <button class="text-red-500 bg-red-50 hover:bg-red-100 px-1.5 py-0.5 rounded text-[10px] font-bold btn-hapus-petugas shrink-0 transition" 
                                data-tipe="penasehat" data-jadwal="${item.id}" data-petugas="${p.id}"><i class="fa-solid fa-xmark"></i></button>
                        </div>`;
                    });
                }

                // 1. DESKTOP ROW (Tabel Rapat)
                const tr = document.createElement('tr');
                tr.className = "hover:bg-gray-50 transition";
                tr.innerHTML = `
                <td class="px-4 py-3 align-top whitespace-nowrap">
                    <div class="font-bold text-gray-900 text-sm">${formatTanggalIndo(item.tanggal)}</div>
                    <div class="text-xs font-medium text-gray-500 mt-0.5">${item.jam_mulai.substring(0,5)} - ${item.jam_selesai.substring(0,5)}</div>
                </td>
                <td class="px-4 py-3 align-top">${guruHtml}</td>
                <td class="px-4 py-3 align-top">${penasehatHtml}</td>
                <td class="px-4 py-3 align-top">
                    <div class="flex flex-col gap-1.5 mx-auto max-w-[180px]">
                        <div class="flex gap-1.5">
                            <button class="text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-1.5 rounded flex-1 text-center text-xs font-semibold transition btn-atur-guru" 
                                data-id="${item.id}" data-mulai="${item.jam_mulai.substring(0,5)}" data-selesai="${item.jam_selesai.substring(0,5)}">
                                + Guru
                            </button>
                            <button class="text-yellow-700 hover:text-yellow-900 bg-yellow-50 hover:bg-yellow-100 px-2 py-1.5 rounded flex-1 text-center text-xs font-semibold transition btn-atur-penasehat" 
                                data-id="${item.id}" data-mulai="${item.jam_mulai.substring(0,5)}" data-selesai="${item.jam_selesai.substring(0,5)}">
                                + Penasehat
                            </button>
                        </div>
                        <a href="?page=presensi/input_presensi&jadwal_id=${item.id}" class="text-blue-700 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-2 py-1.5 rounded text-xs font-bold text-center transition block border border-blue-200">
                            <i class="fa-solid fa-clipboard-user mr-1"></i> Presensi
                        </a>
                        <div class="flex gap-1.5">
                            <button class="text-gray-700 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded flex-1 text-center text-[11px] font-semibold transition btn-edit-jadwal" 
                                data-id="${item.id}" data-tanggal="${item.tanggal}" data-mulai="${item.jam_mulai}" data-selesai="${item.jam_selesai}">
                                Edit
                            </button>
                            <button class="text-red-700 hover:text-red-900 bg-red-50 hover:bg-red-100 px-2 py-1 rounded flex-1 text-center text-[11px] font-semibold transition btn-hapus-jadwal" data-id="${item.id}">
                                Hapus
                            </button>
                        </div>
                    </div>
                </td>
                `;
                if (tbody) tbody.appendChild(tr);

                // 2. MOBILE CARD (Grid Compact)
                const card = document.createElement('div');
                card.className = "bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden";
                card.innerHTML = `
                <!-- Header Card: Tanggal & Waktu -->
                <div class="bg-gray-50 px-3 py-2 border-b border-gray-100 flex justify-between items-center">
                    <div class="font-bold text-gray-900 text-[13px]">${formatHariTanggal(item.tanggal)}</div>
                    <div class="text-[10px] font-bold bg-white text-gray-600 px-2 py-0.5 rounded border border-gray-200 shadow-sm">
                        <i class="fa-regular fa-clock"></i> ${item.jam_mulai.substring(0,5)} - ${item.jam_selesai.substring(0,5)}
                    </div>
                </div>

                <!-- Body Card: Guru & Penasehat -->
                <div class="p-3 grid grid-cols-2 gap-3">
                    <div class="bg-blue-50/50 p-2 rounded-lg border border-blue-100">
                        <div class="flex justify-between items-center mb-1">
                            <div class="text-[9px] text-blue-600 font-bold uppercase tracking-wider">👨‍🏫 Guru</div>
                            <button class="text-blue-700 hover:text-blue-900 bg-blue-100 hover:bg-blue-200 px-1.5 py-0.5 rounded text-[9px] font-bold btn-atur-guru transition"
                                data-id="${item.id}" data-mulai="${item.jam_mulai.substring(0,5)}" data-selesai="${item.jam_selesai.substring(0,5)}">
                                <i class="fa-solid fa-plus"></i> Tambah
                            </button>
                        </div>
                        <div class="space-y-0 text-left">${guruMobileHtml}</div>
                    </div>
                    <div class="bg-yellow-50/50 p-2 rounded-lg border border-yellow-100">
                         <div class="flex justify-between items-center mb-1">
                            <div class="text-[9px] text-yellow-600 font-bold uppercase tracking-wider">👳‍♂️ Penasehat</div>
                            <button class="text-yellow-700 hover:text-yellow-900 bg-yellow-100 hover:bg-yellow-200 px-1.5 py-0.5 rounded text-[9px] font-bold btn-atur-penasehat transition"
                                data-id="${item.id}" data-mulai="${item.jam_mulai.substring(0,5)}" data-selesai="${item.jam_selesai.substring(0,5)}">
                                <i class="fa-solid fa-plus"></i> Tambah
                            </button>
                        </div>
                        <div class="space-y-0 text-left">${penasehatMobileHtml}</div>
                    </div>
                </div>

                <!-- Footer Card: Aksi Lanjutan -->
                <div class="bg-gray-50 px-3 py-2.5 border-t border-gray-100 grid grid-cols-3 gap-2">
                    <a href="?page=presensi/input_presensi&jadwal_id=${item.id}" class="col-span-1 text-center bg-indigo-600 text-white text-[11px] font-bold py-1.5 rounded-lg flex items-center justify-center gap-1 shadow-sm hover:bg-indigo-700 transition">
                        <i class="fa-solid fa-clipboard-user"></i> Presensi
                    </a>
                    <button class="col-span-1 text-center bg-gray-200 text-gray-700 hover:bg-gray-300 text-[11px] font-bold py-1.5 rounded-lg flex items-center justify-center gap-1 transition btn-edit-jadwal"
                        data-id="${item.id}" data-tanggal="${item.tanggal}" data-mulai="${item.jam_mulai}" data-selesai="${item.jam_selesai}">
                        <i class="fa-solid fa-pen text-[10px]"></i> Edit
                    </button>
                    <button class="col-span-1 text-center bg-red-100 hover:bg-red-200 text-red-600 text-[11px] font-bold py-1.5 rounded-lg flex items-center justify-center gap-1 transition btn-hapus-jadwal" data-id="${item.id}">
                        <i class="fa-solid fa-trash text-[10px]"></i> Hapus
                    </button>
                </div>
                `;
                if (mobileContainer) mobileContainer.appendChild(card);
            });
        }

        // --- RENDER TABEL 2 (REKAP VIEW) ---
        function renderTabelRekap(data) {
            const tbody = document.getElementById('tbody_rekap_jadwal');
            const mobileContainer = document.getElementById('mobile_rekap_jadwal');
            if (tbody) tbody.innerHTML = '';
            if (mobileContainer) mobileContainer.innerHTML = '';

            if (data.length === 0) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center py-6 text-sm text-gray-500">Belum ada data.</td></tr>';
                if (mobileContainer) mobileContainer.innerHTML = '<div class="text-center py-8 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg">Belum ada data.</div>';
                return;
            }

            let no = 1;
            data.forEach(item => {
                const statusJurnal = item.pengajar ?
                    `<span class="px-2.5 py-1 inline-flex text-[11px] leading-5 font-bold rounded-full bg-green-100 text-green-800">Terisi</span>` :
                    `<span class="px-2.5 py-1 inline-flex text-[11px] leading-5 font-bold rounded-full bg-red-100 text-red-800">Kosong</span>`;

                const statusJurnalMobile = item.pengajar ?
                    `<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-green-100 text-green-800">Jurnal Terisi</span>` :
                    `<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-red-100 text-red-800">Jurnal Kosong</span>`;

                // Handle List Guru/Penasehat dengan baris baru (bisa dipisah oleh koma atau newline dari DB)
                const guruStr = item.daftar_guru ? item.daftar_guru.replace(/[,\n]/g, '<br>') : '<i class="text-gray-400 font-normal">--</i>';
                const pnsStr = item.daftar_penasehat ? item.daftar_penasehat.replace(/[,\n]/g, '<br>') : '<i class="text-gray-400 font-normal">--</i>';

                // 1. DESKTOP ROW
                const tr = document.createElement('tr');
                tr.className = "hover:bg-gray-50 transition";
                tr.innerHTML = `
                <td class="px-4 py-3 align-top text-center text-sm font-semibold text-gray-700">${no++}</td>
                <td class="px-4 py-3 align-top whitespace-nowrap text-left">
                    <div class="font-bold text-gray-800 text-[13px]">${formatHariTanggal(item.tanggal)}</div>
                    <div class="text-xs font-medium text-gray-500 mt-0.5">${item.jam_mulai.substring(0,5)} - ${item.jam_selesai.substring(0,5)}</div>
                </td>
                <td class="px-4 py-3 align-top text-[13px] text-blue-900 font-medium leading-relaxed text-left">${guruStr}</td>
                <td class="px-4 py-3 align-top text-[13px] text-green-900 font-medium leading-relaxed text-left">${pnsStr}</td>
                <td class="px-4 py-3 align-top text-center">${statusJurnal}</td>
                `;
                if (tbody) tbody.appendChild(tr);

                // 2. MOBILE CARD
                const card = document.createElement('div');
                card.className = "bg-white border border-gray-200 rounded-xl p-3 shadow-sm";
                card.innerHTML = `
                <div class="flex justify-between items-center border-b border-gray-100 pb-2 mb-2">
                    <div class="font-bold text-indigo-700 text-[13px]">${formatHariTanggal(item.tanggal)}</div>
                    <div class="text-[10px] font-bold bg-gray-100 text-gray-600 px-2 py-0.5 rounded border border-gray-200">
                        <i class="fa-regular fa-clock"></i> ${item.jam_mulai.substring(0,5)} - ${item.jam_selesai.substring(0,5)}
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <div class="bg-blue-50/50 p-2 rounded-lg border border-blue-100">
                        <div class="text-[9px] text-blue-500 font-bold uppercase tracking-wider mb-1">👨‍🏫 Guru</div>
                        <div class="text-gray-800 text-[12px] font-medium leading-tight">
                            ${guruStr === '<i class="text-gray-400 font-normal">--</i>' ? '<i class="text-gray-400 font-normal">Kosong</i>' : guruStr}
                        </div>
                    </div>
                    <div class="bg-green-50/50 p-2 rounded-lg border border-green-100">
                        <div class="text-[9px] text-green-500 font-bold uppercase tracking-wider mb-1">👳‍♂️ Penasehat</div>
                        <div class="text-gray-800 text-[12px] font-medium leading-tight">
                            ${pnsStr === '<i class="text-gray-400 font-normal">--</i>' ? '<i class="text-gray-400 font-normal">Kosong</i>' : pnsStr}
                        </div>
                    </div>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-gray-50">
                    <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wide">Status Jurnal:</span>
                    ${statusJurnalMobile}
                </div>
                `;
                if (mobileContainer) mobileContainer.appendChild(card);
            });
        }

        // --- FUNGSI HELPER JS ---
        function formatTanggalIndo(dateStr) {
            const d = new Date(dateStr);
            const months = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            return `${d.getDate().toString().padStart(2, '0')} ${months[d.getMonth()]} ${d.getFullYear()}`;
        }

        function formatHariTanggal(dateStr) {
            const d = new Date(dateStr);
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            return `${days[d.getDay()]}, ${formatTanggalIndo(dateStr)}`;
        }

        // --- MANAJEMEN MODAL ---
        const modals = {
            tambahJadwal: document.getElementById('modalTambahJadwal'),
            editJadwal: document.getElementById('modalEditJadwal'),
            aturGuru: document.getElementById('modalAturGuru'),
            aturPenasehat: document.getElementById('modalAturPenasehat'),
        };

        function openModal(id) {
            modals[id].classList.remove('hidden');
        }

        function closeModal(id) {
            modals[id].classList.add('hidden');
        }

        // Close Modals via Button/Backdrop
        document.querySelectorAll('.btn-tutup-modal, .modal-backdrop').forEach(el => {
            el.addEventListener('click', () => {
                Object.keys(modals).forEach(k => closeModal(k));
            });
        });

        // SET MIN/MAX DATE INPUT
        const masterPeriodeSelect = document.getElementById('filter_periode_id');
        const setMinMaxDate = (inputEl) => {
            const opt = masterPeriodeSelect.options[masterPeriodeSelect.selectedIndex];
            if (opt && opt.dataset.mulai && opt.dataset.selesai) {
                inputEl.min = opt.dataset.mulai;
                inputEl.max = opt.dataset.selesai;
            }
        };

        // --- EVENT DELEGATION (KLIK TOMBOL DINAMIS) ---
        document.body.addEventListener('click', function(e) {
            // Tombol Tambah Jadwal
            if (e.target.closest('#btnBukaModalTambahJadwal')) {
                document.getElementById('formTambahJadwal').reset();
                setMinMaxDate(document.getElementById('inputTambahTanggal'));
                openModal('tambahJadwal');
            }

            // Tombol Edit Jadwal
            if (e.target.closest('.btn-edit-jadwal')) {
                const btn = e.target.closest('.btn-edit-jadwal');
                document.getElementById('edit_jadwal_id').value = btn.dataset.id;
                document.getElementById('edit_tanggal').value = btn.dataset.tanggal;
                document.getElementById('edit_jam_mulai').value = btn.dataset.mulai;
                document.getElementById('edit_jam_selesai').value = btn.dataset.selesai;
                openModal('editJadwal');
            }

            // Tombol Atur Guru
            if (e.target.closest('.btn-atur-guru')) {
                const btn = e.target.closest('.btn-atur-guru');
                const jamMulai = btn.dataset.mulai;
                const jamSelesai = btn.dataset.selesai;

                document.getElementById('formAturGuru').reset();
                document.getElementById('guru_jadwal_id').value = btn.dataset.id;
                document.getElementById('guru_jam_mulai').value = jamMulai;
                document.getElementById('guru_jam_selesai').value = jamSelesai;

                // Hitung jam WA dikirim dan tampilkan di modal
                const jamKirimWa = subtractHours(jamMulai, jamPengingatWa);
                document.getElementById('guru_info_waktu').textContent = `${jamKirimWa} WIB`;

                openModal('aturGuru');
            }

            // Tombol Atur Penasehat
            if (e.target.closest('.btn-atur-penasehat')) {
                const btn = e.target.closest('.btn-atur-penasehat');
                const jamMulai = btn.dataset.mulai;

                document.getElementById('formAturPenasehat').reset();
                document.getElementById('penasehat_jadwal_id').value = btn.dataset.id;
                document.getElementById('penasehat_jam_mulai').value = jamMulai;

                // Hitung jam WA dikirim dan tampilkan di modal
                const jamKirimWa = subtractHours(jamMulai, jamPengingatWa);
                document.getElementById('penasehat_info_waktu').textContent = `${jamKirimWa} WIB`;

                openModal('aturPenasehat');
            }

            // Tombol Hapus Jadwal
            if (e.target.closest('.btn-hapus-jadwal')) {
                const id = e.target.closest('.btn-hapus-jadwal').dataset.id;
                Swal.fire({
                    title: 'Hapus Jadwal?',
                    text: "Semua data presensi terkait jadwal ini akan ikut terhapus!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Ya, Hapus!'
                }).then((result) => {
                    if (result.isConfirmed) handleAjaxForm(null, {
                        action: 'hapus_jadwal',
                        jadwal_id: id
                    });
                });
            }

            // Tombol Hapus Petugas (Guru/Penasehat)
            if (e.target.closest('.btn-hapus-petugas')) {
                const btn = e.target.closest('.btn-hapus-petugas');
                const data = {
                    action: btn.dataset.tipe === 'guru' ? 'hapus_guru_jadwal' : 'hapus_penasehat_jadwal',
                    jadwal_id: btn.dataset.jadwal,
                    petugas_id: btn.dataset.petugas
                };
                handleAjaxForm(null, data);
            }
        });

        // --- HANDLE SUBMIT FORM AJAX ---
        function handleAjaxForm(e, manualData = null) {
            if (e) e.preventDefault();

            let formData;
            let actionForm = '';

            if (manualData) {
                formData = new URLSearchParams(manualData);
                actionForm = manualData.action;
            } else {
                formData = new URLSearchParams(new FormData(e.target));
                actionForm = formData.get('action');
            }

            // --- VALIDASI JAM MULAI & SELESAI (KHUSUS TAMBAH & EDIT JADWAL) ---
            if (actionForm === 'tambah_jadwal' || actionForm === 'edit_jadwal') {
                const jamMulai = formData.get('jam_mulai');
                const jamSelesai = formData.get('jam_selesai');

                if (jamMulai && jamSelesai) {
                    if (jamMulai >= jamSelesai) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Waktu Tidak Valid',
                            text: 'Jam Selesai harus lebih besar dari Jam Mulai.',
                        });
                        return; // Hentikan proses fetch
                    }
                }
            }

            Swal.fire({
                title: 'Menyimpan...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(API_URL, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            showConfirmButton: false,
                            timer: 1500
                        });
                        Object.keys(modals).forEach(k => closeModal(k)); // Tutup semua modal
                        loadDataJadwal(); // Refresh Tabel
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Terjadi kesalahan komunikasi dengan server.', 'error');
                });
        }

        // Attach submit event ke semua form modal
        document.getElementById('formTambahJadwal').addEventListener('submit', handleAjaxForm);
        document.getElementById('formEditJadwal').addEventListener('submit', handleAjaxForm);
        document.getElementById('formAturGuru').addEventListener('submit', handleAjaxForm);
        document.getElementById('formAturPenasehat').addEventListener('submit', handleAjaxForm);

        // --- LISTENER EXPORT PDF (FETCH BLOB) ---
        const formExport = document.getElementById('formExportJadwal');
        if (formExport) {
            formExport.addEventListener('submit', function(e) {
                e.preventDefault();
                hideGlobalOverlay();
                // Tampilkan Loading
                Swal.fire({
                    title: 'Memproses PDF...',
                    text: 'Mohon tunggu sebentar.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData(this);

                // Fetch ke file export (pastikan pathnya benar relatif dari halaman ini)
                fetch('pages/export/export_jadwal.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Gagal memproses file.');
                        }
                        const contentDisposition = response.headers.get('content-disposition');
                        let filename = 'Jadwal_Mengajar.pdf';
                        if (contentDisposition) {
                            const match = contentDisposition.match(/filename="?([^"]+)"?/);
                            if (match && match[1]) filename = match[1];
                        }
                        return response.blob().then(blob => ({
                            blob,
                            filename
                        }));
                    })
                    .then(({
                        blob,
                        filename
                    }) => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'File PDF berhasil diunduh.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        setTimeout(() => location.reload(), 1500);
                    })
                    .catch(error => {
                        console.error(error);
                        Swal.fire({
                            title: 'Error',
                            text: 'Gagal mengunduh file.',
                            icon: 'error'
                        });
                    });
            });
        }

        // Initial Load
        loadDataJadwal();
    });
</script>