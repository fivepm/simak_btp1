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
    /* .loading-overlay { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.7); z-index: 10; justify-content: center; align-items: center; } */
    .table-container {
        position: relative;
    }
</style>

<div class="container mx-auto space-y-6">
    <!-- FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Pengaturan Jadwal & Petugas</h3>
        <form method="GET" action="" id="filterForm">
            <input type="hidden" name="page" value="presensi/jadwal">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium">Periode</label>
                    <select id="filter_periode_id" name="periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
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
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                        <input id="filter_kelompok" type="hidden" name="kelompok" value="<?php echo $admin_kelompok; ?>">
                    <?php else: ?>
                        <select id="filter_kelompok" name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
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
                    <select id="filter_kelas" name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                        <option value="semua" <?php echo ($selected_kelas == 'semua') ? 'selected' : ''; ?>>-- Pilih Kelas --</option>
                        <?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                        foreach ($kelas_opts as $k): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Tampilkan</button></div>
            </div>
        </form>
    </div>

    <?php if ($selected_periode_id && $selected_kelompok !== 'semua' && $selected_kelas !== 'semua'): ?>

        <!-- ========================================== -->
        <!-- TABEL 1: PENGATURAN GURU & PENASEHAT (CRUD) -->
        <!-- ========================================== -->
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto table-container">
            <!-- <div id="loading1" class="loading-overlay">Memuat data...</div> -->
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-xl font-medium text-gray-800">Atur Guru & Penasehat</h3>
                </div>
                <button id="btnBukaModalTambahJadwal" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg shadow transition">+ Tambah Jadwal</button>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 w-1/4">Waktu</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 w-1/4">Guru</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 w-1/4">Penasehat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Opsi</th>
                    </tr>
                </thead>
                <tbody id="tbody_crud_jadwal" class="divide-y divide-gray-200">
                    <!-- Data akan dirender via AJAX -->
                </tbody>
            </table>
        </div>

        <!-- ========================================== -->
        <!-- TABEL 2: REKAPITULASI & STATUS JURNAL (VIEW) -->
        <!-- ========================================== -->
        <div class="border border-black bg-white p-6 rounded-lg shadow-md overflow-x-auto table-container">
            <!-- <div id="loading2" class="loading-overlay">Memuat data...</div> -->
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-xl font-medium text-gray-800">Jadwal Guru dan Penasehat</h3>
                    <p class="text-sm text-gray-600">
                        Periode: <span class="font-semibold"><?php echo htmlspecialchars($selected_periode_nama); ?></span> |
                        <span class="capitalize"><?php echo htmlspecialchars($selected_kelompok); ?> - <?php echo htmlspecialchars($selected_kelas); ?></span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <form id="formExportJadwal">
                        <input type="hidden" name="periode_id" value="<?php echo $selected_periode_id; ?>">
                        <input type="hidden" name="kelompok" value="<?php echo $selected_kelompok; ?>">
                        <input type="hidden" name="kelas" value="<?php echo $selected_kelas; ?>">
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow flex items-center gap-2">
                            <i class="fa-solid fa-file-pdf"></i> Export PDF
                        </button>
                    </form>
                </div>
            </div>

            <table class="border min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">No</th>
                        <th class="border px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-1/4">Tanggal</th>
                        <th class="border px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-1/4">Guru</th>
                        <th class="border px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-1/4">Penasehat</th>
                        <th class="border px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status Jurnal</th>
                    </tr>
                </thead>
                <tbody id="tbody_rekap_jadwal" class="bg-white divide-y divide-gray-200 text-center">
                    <!-- Data akan dirender via AJAX -->
                </tbody>
            </table>
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
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-10">
            <form id="formTambahJadwal">
                <input type="hidden" name="action" value="tambah_jadwal">
                <input type="hidden" name="periode_id" value="<?php echo $selected_periode_id; ?>">
                <input type="hidden" name="kelompok" value="<?php echo $selected_kelompok; ?>">
                <input type="hidden" name="kelas" value="<?php echo $selected_kelas; ?>">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tambah Jadwal Baru</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Tanggal*</label>
                            <input type="date" id="inputTambahTanggal" name="tanggal" class="mt-1 block w-full border-gray-300 rounded-md" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium">Jam Mulai*</label><input type="time" name="jam_mulai" class="mt-1 block w-full border-gray-300 rounded-md" required></div>
                            <div><label class="block text-sm font-medium">Jam Selesai*</label><input type="time" name="jam_selesai" class="mt-1 block w-full border-gray-300 rounded-md" required></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm btn-tutup-modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Jadwal -->
<div id="modalEditJadwal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-10">
            <form id="formEditJadwal">
                <input type="hidden" name="action" value="edit_jadwal">
                <input type="hidden" name="jadwal_id" id="edit_jadwal_id">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Jadwal</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Tanggal</label>
                            <input type="date" name="tanggal" id="edit_tanggal" class="mt-1 block w-full border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" readonly>
                            <p class="text-xs text-red-500 mt-1">*Tanggal tidak dapat diubah untuk menjaga integritas data.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium">Jam Mulai*</label><input type="time" name="jam_mulai" id="edit_jam_mulai" class="mt-1 block w-full border-gray-300 rounded-md" required></div>
                            <div><label class="block text-sm font-medium">Jam Selesai*</label><input type="time" name="jam_selesai" id="edit_jam_selesai" class="mt-1 block w-full border-gray-300 rounded-md" required></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Update</button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm btn-tutup-modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Atur Guru -->
<div id="modalAturGuru" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-10">
            <form id="formAturGuru">
                <input type="hidden" name="action" value="tambah_guru_jadwal">
                <input type="hidden" name="jadwal_id" id="guru_jadwal_id">
                <input type="hidden" name="jam_mulai_pengingat" id="guru_jam_mulai">
                <input type="hidden" name="jam_selesai_pengingat" id="guru_jam_selesai">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tugaskan Guru</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Pilih Guru*</label>
                            <select name="guru_id" class="mt-1 block w-full border-gray-300 rounded-md" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($guru_options as $g): ?>
                                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bg-cyan-50 p-3 rounded-md border border-cyan-100 mt-4">
                            <p class="text-xs text-gray-600">Pengingat WA akan dikirim ke guru tersebut pada <strong id="guru_info_waktu"></strong>.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Tugaskan</button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm btn-tutup-modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Atur Penasehat -->
<div id="modalAturPenasehat" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-10">
            <form id="formAturPenasehat">
                <input type="hidden" name="action" value="tambah_penasehat_jadwal">
                <input type="hidden" name="jadwal_id" id="penasehat_jadwal_id">
                <input type="hidden" name="jam_mulai_pengingat" id="penasehat_jam_mulai">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tugaskan Penasehat</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Pilih Penasehat*</label>
                            <select name="penasehat_id" class="mt-1 block w-full border-gray-300 rounded-md" required>
                                <option value="">-- Pilih Penasehat --</option>
                                <?php foreach ($penasehat_options as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bg-yellow-50 p-3 rounded-md border border-yellow-100 mt-4">
                            <p class="text-xs text-gray-600">Pengingat WA akan dikirim ke penasehat pada <strong id="penasehat_info_waktu"></strong>.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 sm:ml-3 sm:w-auto sm:text-sm">Tugaskan</button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm btn-tutup-modal">Batal</button>
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

            // document.getElementById('loading1').style.display = 'flex';
            // document.getElementById('loading2').style.display = 'flex';

            fetch(`${API_URL}?action=get_data&periode_id=${filterData.periode_id}&kelompok=${filterData.kelompok}&kelas=${filterData.kelas}`)
                .then(res => {
                    // Cek apakah response HTTP sukses (status 200-299)
                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    // Coba parse JSON, jika gagal akan masuk ke block catch
                    return res.json();
                })
                .then(res => {
                    if (res.status === 'success') {
                        renderTabelCRUD(res.data.jadwal_crud);
                        renderTabelRekap(res.data.jadwal_rekap);
                    } else {
                        // Tampilkan pesan error dari backend
                        Swal.fire('Error', res.message || 'Gagal memuat data dari server.', 'error');
                    }
                })
                .catch(err => {
                    // Tampilkan pesan error asli di console untuk debugging
                    console.error("Fetch Error:", err);

                    // Tampilkan pesan error ke user
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Memuat Data',
                        text: 'Terjadi kesalahan sistem: ' + err.message,
                        footer: '<a href="#">Silakan cek console browser (F12) untuk detail error.</a>'
                    });
                })
                .finally(() => {
                    // document.getElementById('loading1').style.display = 'none';
                    // document.getElementById('loading2').style.display = 'none';
                    hideGlobalOverlay();
                });
        }

        // --- RENDER TABEL 1 (CRUD) ---
        function renderTabelCRUD(data) {
            const tbody = document.getElementById('tbody_crud_jadwal');
            tbody.innerHTML = '';

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">Belum ada jadwal.</td></tr>';
                return;
            }

            data.forEach(item => {
                // Render Guru HTML
                let guruHtml = '';
                if (item.guru.length === 0) {
                    guruHtml = `<span class="text-gray-400 italic text-sm">Belum ada guru</span>`;
                } else {
                    item.guru.forEach(g => {
                        guruHtml += `
                        <div class="flex items-center justify-between group text-sm border-b border-gray-100 py-1">
                            <span>${g.nama}</span>
                            <button class="text-red-500 hover:text-red-700 text-xs opacity-0 group-hover:opacity-100 btn-hapus-petugas" 
                                data-tipe="guru" data-jadwal="${item.id}" data-petugas="${g.id}">[Hapus]</button>
                        </div>`;
                    });
                }

                // Render Penasehat HTML
                let penasehatHtml = '';
                if (item.penasehat.length === 0) {
                    penasehatHtml = `<span class="text-gray-400 italic text-sm">Belum ada penasehat</span>`;
                } else {
                    item.penasehat.forEach(p => {
                        penasehatHtml += `
                        <div class="flex items-center justify-between group text-sm border-b border-gray-100 py-1">
                            <span>${p.nama}</span>
                            <button class="text-red-500 hover:text-red-700 text-xs opacity-0 group-hover:opacity-100 btn-hapus-petugas" 
                                data-tipe="penasehat" data-jadwal="${item.id}" data-petugas="${p.id}">[Hapus]</button>
                        </div>`;
                    });
                }

                // Row HTML
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td class="px-6 py-4">
                    <div class="font-medium">${formatTanggalIndo(item.tanggal)}</div>
                    <div class="text-sm text-gray-500">${item.jam_mulai.substring(0,5)} - ${item.jam_selesai.substring(0,5)}</div>
                </td>
                <td class="px-6 py-4 align-top">${guruHtml}</td>
                <td class="px-6 py-4 align-top">${penasehatHtml}</td>
                <td class="px-6 py-4 text-sm font-medium align-top">
                    <div class="flex flex-col gap-2 w-max">
                        <button class="text-left text-indigo-600 hover:text-indigo-900 btn-atur-guru" 
                            data-id="${item.id}" data-mulai="${item.jam_mulai.substring(0,5)}" data-selesai="${item.jam_selesai.substring(0,5)}">
                            <i class="fa-solid fa-user-plus"></i> Atur Guru
                        </button>
                        <button class="text-left text-yellow-600 hover:text-yellow-900 btn-atur-penasehat" 
                            data-id="${item.id}" data-mulai="${item.jam_mulai.substring(0,5)}" data-selesai="${item.jam_selesai.substring(0,5)}">
                            <i class="fa-solid fa-user-plus"></i> Atur Penasehat
                        </button>
                        <hr class="my-1 border-gray-200">
                        <a href="?page=presensi/input_presensi&jadwal_id=${item.id}" class="text-blue-600 hover:text-blue-900"><i class="fa-solid fa-clipboard-user"></i> Presensi</a>
                        <button class="text-left text-gray-600 hover:text-gray-900 btn-edit-jadwal" 
                            data-id="${item.id}" data-tanggal="${item.tanggal}" data-mulai="${item.jam_mulai}" data-selesai="${item.jam_selesai}">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>
                        <button class="text-left text-red-600 hover:text-red-900 btn-hapus-jadwal" data-id="${item.id}">
                            <i class="fa-solid fa-trash"></i> Hapus
                        </button>
                    </div>
                </td>
            `;
                tbody.appendChild(tr);
            });
        }

        // --- RENDER TABEL 2 (REKAP) ---
        function renderTabelRekap(data) {
            const tbody = document.getElementById('tbody_rekap_jadwal');
            tbody.innerHTML = '';

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Belum ada data.</td></tr>';
                return;
            }

            let no = 1;
            data.forEach(item => {
                const statusJurnal = item.pengajar ?
                    `<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Terisi</span>` :
                    `<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Kosong</span>`;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td class="border px-4 py-3 align-top">${no++}</td>
                <td class="border px-4 py-3 align-top">
                    <div class="font-medium">${formatHariTanggal(item.tanggal)}</div>
                    <div class="text-sm text-gray-500">${item.jam_mulai.substring(0,5)} - ${item.jam_selesai.substring(0,5)}</div>
                </td>
                <td class="border px-4 py-3 align-top text-sm whitespace-pre-line">${item.daftar_guru ? item.daftar_guru.replace(/,/g, '<br>') : '<i class="text-gray-400">--</i>'}</td>
                <td class="border px-4 py-3 align-top text-sm whitespace-pre-line">${item.daftar_penasehat ? item.daftar_penasehat.replace(/,/g, '<br>') : '<i class="text-gray-400">--</i>'}</td>
                <td class="border px-4 py-3 align-top">${statusJurnal}</td>
            `;
                tbody.appendChild(tr);
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

        // --- EVENT DELEGATION (KLIK TOMBOL) ---
        document.body.addEventListener('click', function(e) {
            // Tombol Tambah Jadwal
            if (e.target.id === 'btnBukaModalTambahJadwal') {
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
                // setMinMaxDate(document.getElementById('edit_tanggal')); // Dihapus karena input sekarang readonly
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
                document.getElementById('guru_info_waktu').textContent = `${jamKirimWa} WIB (${jamPengingatWa} jam sebelumnya)`;

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
                document.getElementById('penasehat_info_waktu').textContent = `${jamKirimWa} WIB (${jamPengingatWa} jam sebelumnya)`;

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
                fetch('pages/export/export_jadwal.php', { // <-- Sesuaikan folder path-nya jika berbeda
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Gagal memproses file.');
                        }
                        // Ambil nama file dari header jika ada, atau default
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
                        // Buat URL Blob dan trigger download
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);

                        // Tutup Swal Loading
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