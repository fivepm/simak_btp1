<?php
// === FILE FRONTEND: kelola_guru.php ===
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL (sebagai default awal)
$filter_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

if ($admin_tingkat === 'kelompok') {
    $filter_kelompok = $admin_kelompok;
}

$list_opsi_kelas = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
?>

<div class="container mx-auto relative">

    <!-- Header & Filter -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Kelola Guru & Akses Kelas</h3>
        <button id="tambahGuruBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2 shadow transition transform hover:scale-105">
            <i class="fa-solid fa-plus"></i> Tambah Guru
        </button>
    </div>

    <!-- Filter Section -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
        <form id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Filter Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?= ucfirst($admin_kelompok) ?>" class="mt-1 w-full bg-gray-100 p-2 rounded border border-gray-200" disabled>
                        <input type="hidden" id="filter_kelompok" value="<?= $admin_kelompok ?>">
                    <?php else: ?>
                        <select id="filter_kelompok" class="mt-1 w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="semua">Semua</option>
                            <option value="bintaran" <?= $filter_kelompok == 'bintaran' ? 'selected' : '' ?>>Bintaran</option>
                            <option value="gedongkuning" <?= $filter_kelompok == 'gedongkuning' ? 'selected' : '' ?>>Gedongkuning</option>
                            <option value="jombor" <?= $filter_kelompok == 'jombor' ? 'selected' : '' ?>>Jombor</option>
                            <option value="sunten" <?= $filter_kelompok == 'sunten' ? 'selected' : '' ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Filter Kelas</label>
                    <select id="filter_kelas" class="mt-1 w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="semua">Semua</option>
                        <?php foreach ($list_opsi_kelas as $k): ?>
                            <option value="<?= $k ?>" <?= $filter_kelas == $k ? 'selected' : '' ?>><?= ucfirst($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end">
                    <button type="button" id="btnTerapkanFilter" class="w-full bg-indigo-600 text-white font-bold py-2 px-4 rounded-md hover:bg-indigo-700 shadow transition">Terapkan Filter</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabel Data -->
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto relative min-h-[300px]">
        <!-- Loader Tabel Internal -->
        <div id="tableLoader" class="absolute inset-0 bg-white bg-opacity-80 z-10 flex justify-center items-center hidden">
            <div class="flex flex-col items-center">
                <svg class="animate-spin h-8 w-8 text-indigo-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm text-gray-500">Memuat data...</span>
            </div>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase w-1/4">Nama / Kelompok</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase w-1/4">Kelas Diampu</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody id="guruTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Data akan dimuat via AJAX -->
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- MODAL TAMBAH -->
<div id="tambahGuruModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
    <div class="fixed inset-0 z-50 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <form id="formTambahGuru">
                    <input type="hidden" name="action" value="tambah_guru">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Tambah Guru Baru</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nama Lengkap*</label>
                                <input type="text" name="nama" placeholder="Contoh: Budi Santoso" class="mt-1 w-full border border-gray-300 p-2 rounded-md focus:ring-2 focus:ring-green-500 outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nama Panggilan*</label>
                                <input type="text" name="nama_panggilan" placeholder="Contoh: Budi" class="mt-1 w-full border border-gray-300 p-2 rounded-md focus:ring-2 focus:ring-green-500 outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nomor WA* <span class="text-xs text-red-500 font-normal">(Wajib klik Tes Cek WA sebelum simpan)</span></label>
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <input type="text" name="nomor_wa" id="tambah_nomor_wa" placeholder="Misal: 08123456789" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none sm:text-sm" required>
                                    <button type="button" id="btnCekWaTambah" class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-gray-600 text-sm font-medium hover:bg-gray-100 focus:outline-none transition-colors">
                                        Tes Cek WA
                                    </button>
                                </div>
                                <p id="msgCekWaTambah" class="mt-1 text-xs hidden"></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Kelompok</label>
                                <?php if ($admin_tingkat === 'kelompok'): ?>
                                    <input type="hidden" name="kelompok" value="<?= $admin_kelompok ?>">
                                    <input type="text" value="<?= ucfirst($admin_kelompok) ?>" class="mt-1 w-full bg-gray-100 border border-gray-200 p-2 rounded-md text-gray-500" disabled>
                                <?php else: ?>
                                    <select name="kelompok" class="mt-1 w-full border border-gray-300 p-2 rounded-md focus:ring-2 focus:ring-green-500 outline-none" required>
                                        <option value="bintaran">Bintaran</option>
                                        <option value="gedongkuning">Gedongkuning</option>
                                        <option value="jombor">Jombor</option>
                                        <option value="sunten">Sunten</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="border border-gray-200 p-3 rounded-md bg-gray-50">
                                <label class="block text-sm font-medium mb-2 text-gray-700">Mengajar Kelas*:</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach ($list_opsi_kelas as $k): ?>
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="kelas_raw[]" value="<?= $k ?>" class="cb-tambah-kelas rounded text-green-600 focus:ring-green-500 h-4 w-4 border-gray-300">
                                            <span class="capitalize text-sm"><?= $k ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 sm:ml-3 sm:w-auto">Simpan</button>
                        <button type="button" class="modal-close-btn mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT -->
<div id="editGuruModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
    <div class="fixed inset-0 z-50 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <form id="formEditGuru">
                    <input type="hidden" name="action" value="edit_guru">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Edit Data Guru</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase">Nama Lengkap</label>
                                <input type="text" name="edit_nama" id="edit_nama" class="w-full border border-gray-300 p-2 rounded-md focus:ring-2 focus:ring-indigo-500 outline-none" required>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase">Username Login</label>
                                <input type="text" id="view_username" class="w-full border border-gray-200 bg-gray-100 text-gray-500 p-2 rounded-md cursor-not-allowed font-mono text-sm" disabled>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase">Nomor WA <span class="text-red-500 lowercase font-normal">(Jika diubah, wajib Tes Cek WA)</span></label>
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <input type="text" name="edit_nomor_wa" id="edit_nomor_wa" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none sm:text-sm" required>
                                    <button type="button" id="btnCekWaEdit" class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-gray-600 text-sm font-medium hover:bg-gray-100 focus:outline-none transition-colors">
                                        Tes Cek WA
                                    </button>
                                </div>
                                <p id="msgCekWaEdit" class="mt-1 text-xs hidden"></p>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase">Kelompok</label>
                                <?php if ($admin_tingkat === 'kelompok'): ?>
                                    <input type="hidden" name="edit_kelompok" id="edit_kelompok" value="<?= $admin_kelompok ?>">
                                    <input type="text" value="<?= ucfirst($admin_kelompok) ?>" class="w-full bg-gray-100 border border-gray-200 p-2 rounded-md text-gray-500" disabled>
                                <?php else: ?>
                                    <select name="edit_kelompok" id="edit_kelompok" class="w-full border border-gray-300 p-2 rounded-md" required>
                                        <option value="bintaran">Bintaran</option>
                                        <option value="gedongkuning">Gedongkuning</option>
                                        <option value="jombor">Jombor</option>
                                        <option value="sunten">Sunten</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="border border-gray-200 p-3 rounded-md bg-gray-50">
                                <label class="block text-sm font-medium mb-2 text-gray-700">Mengajar Kelas:</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach ($list_opsi_kelas as $k): ?>
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="edit_kelas_raw[]" value="<?= $k ?>" class="edit-kelas-checkbox rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300">
                                            <span class="capitalize text-sm"><?= $k ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto">Update</button>
                        <button type="button" class="modal-close-btn mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL HAPUS & RESET PIN SAMA SEPERTI SEBELUMNYA (TIDAK ADA PERUBAHAN) -->
<div id="hapusGuruModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
    <div class="fixed inset-0 z-50 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <form id="formHapusGuru">
                    <input type="hidden" name="action" value="hapus_guru">
                    <input type="hidden" name="hapus_id" id="hapus_id">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Hapus Guru</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">Anda yakin ingin menghapus <strong id="hapus_nama" class="text-gray-900"></strong>?<br>Data ini akan diarsipkan (Soft Delete).</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">Ya, Hapus</button>
                        <button type="button" class="modal-close-btn mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="resetPinModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity modal-backdrop"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-sm z-50">
            <div class="bg-yellow-50 px-4 pt-5 pb-4 sm:p-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fa-solid fa-shield-halved text-yellow-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Reset PIN Pengguna</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 mb-4">
                                Anda akan mereset PIN untuk <strong id="reset_target_nama">User</strong> menjadi default.
                            </p>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Verifikasi PIN Superadmin:</label>
                            <input type="password" id="superadmin_pin" class="shadow-sm focus:ring-yellow-500 focus:border-yellow-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border" placeholder="Masukkan PIN Anda" inputmode="numeric">
                            <p id="reset_error_msg" class="text-red-600 text-xs mt-1 hidden"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="btnConfirmReset" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    Reset PIN
                </button>
                <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<div id="downloadLoader" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-800 bg-opacity-75 hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl text-center">
        <svg class="animate-spin h-10 w-10 text-indigo-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-gray-800">Sedang Mencetak Kartu Akses...</h3>
        <p class="text-sm text-gray-500">Mohon tunggu, Kartu Akses sedang disiapkan.</p>
    </div>
</div>

<script>
    const userRoleSession = '<?= $_SESSION['user_role'] ?? 'admin' ?>';
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const API_URL = 'pages/master/ajax_kelola_guru.php';

        // --- FLAG VALIDASI WA FRONTEND ---
        let isWaValidated_Tambah = false;
        let isWaValidated_Edit = true; // Default true (karena modal dibuka dg nomor WA asli/sensor)

        // Deteksi jika user merubah input WA
        document.getElementById('tambah_nomor_wa').addEventListener('input', function() {
            isWaValidated_Tambah = false;
            document.getElementById('msgCekWaTambah').classList.add('hidden');
        });

        document.getElementById('edit_nomor_wa').addEventListener('input', function() {
            isWaValidated_Edit = false;
            document.getElementById('msgCekWaEdit').classList.add('hidden');
        });

        // --- MANAJEMEN MODAL ---
        const modals = {
            tambah: document.getElementById('tambahGuruModal'),
            edit: document.getElementById('editGuruModal'),
            hapus: document.getElementById('hapusGuruModal'),
            reset: document.getElementById('resetPinModal')
        };
        const openModal = (m) => m && m.classList.remove('hidden');
        const closeModal = (m) => m && m.classList.add('hidden');

        document.body.addEventListener('click', function(e) {
            if (e.target.closest('.modal-close-btn') || e.target.classList.contains('modal-backdrop')) {
                Object.values(modals).forEach(closeModal);
            }
        });

        document.getElementById('tambahGuruBtn').onclick = () => {
            document.getElementById('formTambahGuru').reset();
            document.getElementById('msgCekWaTambah').classList.add('hidden');
            isWaValidated_Tambah = false; // Reset state
            openModal(modals.tambah);
        };

        const hideGlobalOverlay = () => {
            const indexOverlay = document.getElementById('loading-overlay');
            if (indexOverlay) indexOverlay.classList.remove('show');
        };

        // --- LOAD DATA (GET) ---
        function loadData() {
            const kelompok = document.getElementById('filter_kelompok').value;
            const kelas = document.getElementById('filter_kelas').value;
            const loader = document.getElementById('tableLoader');
            const tbody = document.getElementById('guruTableBody');

            loader.classList.remove('hidden');

            fetch(`${API_URL}?action=get_data&kelompok=${kelompok}&kelas=${kelas}`)
                .then(res => res.json())
                .then(res => {
                    hideGlobalOverlay(); // <-- Pindahkan ke sini agar overlay hilang duluan
                    if (res.status === 'success') {
                        renderTable(res.data);
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(err => {
                    hideGlobalOverlay(); // <-- Pindahkan ke sini agar overlay hilang duluan
                    console.error(err);
                    Swal.fire({
                        title: 'Error Server',
                        text: 'Gagal mengambil data. Cek console (F12) untuk detail.',
                        icon: 'error',
                        customClass: {
                            container: 'z-[99999]' // Memastikan Swal selalu di atas segalanya
                        }
                    });
                })
                .finally(() => {
                    loader.classList.add('hidden');
                });
        }

        // --- RENDER TABEL ---
        function renderTable(data) {
            const tbody = document.getElementById('guruTableBody');
            tbody.innerHTML = '';

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">Tidak ada data.</td></tr>';
                return;
            }

            let i = 1;
            data.forEach(g => {
                let kelasHtml = '';
                const arrK = g.raw_kelas ? g.raw_kelas.split(',') : [];
                arrK.forEach(kls => {
                    if (kls.trim()) kelasHtml += `<span class="inline-block bg-blue-50 text-blue-700 text-xs px-2 py-1 rounded-full mr-1 mb-1 border border-blue-200 capitalize">${kls.trim()}</span>`;
                });
                if (!kelasHtml) kelasHtml = '<span class="text-red-400 italic text-xs">Belum ada kelas</span>';

                // Menampilkan indikator Online/Offline (TANPA menampilkan Nomor WA)
                let statusBadge = g.status_login === 'online' ?
                    `<span class="inline-flex items-center gap-1.5 py-1 px-2 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 inline-block bg-emerald-500 rounded-full"></span>Online</span>` :
                    `<span class="inline-flex flex-col py-1 px-2 rounded-md text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200"><span class="flex items-center justify-center gap-1"><span class="w-1.5 h-1.5 inline-block bg-gray-400 rounded-full"></span>Offline</span><span class="text-[10px] text-gray-400 font-normal mt-0.5" title="Terakhir Login">Terakhir: ${g.terakhir_login}</span></span>`;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td class="px-6 py-4 text-sm align-top">${i++}</td>
                <td class="px-6 py-4 align-top">
                    <div class="font-bold text-gray-900">${g.nama}</div>
                    <div class="text-xs text-gray-500 uppercase">${g.kelompok}</div>
                </td>
                <td class="px-6 py-4 text-center align-top max-w-[200px] flex-wrap justify-center">${kelasHtml}</td>
                <td class="px-6 py-4 text-center align-top">${statusBadge}</td>
                <td class="px-6 py-4 text-center whitespace-nowrap text-sm font-medium align-top">
                    <div class="flex flex-col gap-2 w-max mx-auto">
                        <button class="edit-btn text-left text-indigo-600 hover:text-indigo-900"
                            data-id="${g.id}" data-nama="${g.nama}" data-username="${g.username}" 
                            data-kelompok="${g.kelompok}" data-nomor_wa="${g.nomor_wa || ''}" data-kelas-list="${g.raw_kelas || ''}">
                            <i class="fa-solid fa-pen mr-1"></i> Edit
                        </button>
                        <a href="actions/cetak_kartu.php?guru_id=${g.id}" class="cetak-kartu-btn text-left text-blue-600 hover:text-blue-900" data-nama="${g.nama}">
                            <i class="fa-solid fa-id-card mr-1"></i> Cetak Kartu
                        </a>
                        <button class="hapus-btn text-left text-red-600 hover:text-red-900" data-id="${g.id}" data-nama="${g.nama}">
                            <i class="fa-solid fa-trash mr-1"></i> Hapus
                        </button>
                        ${userRoleSession === 'superadmin' ? `
                            <button class="reset-pin-btn text-left text-yellow-600 hover:text-yellow-800"
                                data-id="${g.id}" data-nama="${g.nama}" data-barcode="${g.barcode}" data-role="guru">
                                <i class="fa-solid fa-key mr-1"></i> Reset PIN
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
                tbody.appendChild(tr);
            });
        }

        // --- FILTER ---
        document.getElementById('btnTerapkanFilter').addEventListener('click', loadData);

        // --- EVENT DELEGATION: TABLE BUTTONS ---
        document.body.addEventListener('click', function(e) {
            if (e.target.closest('.hapus-btn')) {
                const btn = e.target.closest('.hapus-btn');
                document.getElementById('hapus_id').value = btn.dataset.id;
                document.getElementById('hapus_nama').textContent = btn.dataset.nama;
                openModal(modals.hapus);
            }

            if (e.target.closest('.edit-btn')) {
                const btn = e.target.closest('.edit-btn');
                document.getElementById('msgCekWaEdit').classList.add('hidden');
                document.getElementById('edit_id').value = btn.dataset.id;
                document.getElementById('edit_nama').value = btn.dataset.nama;
                document.getElementById('edit_nomor_wa').value = btn.dataset.nomor_wa;

                if (document.getElementById('view_username')) document.getElementById('view_username').value = btn.dataset.username;
                if (document.getElementById('edit_kelompok')) document.getElementById('edit_kelompok').value = btn.dataset.kelompok;

                const rawKelas = btn.dataset.kelasList || "";
                const kelasArr = rawKelas.split(',');
                document.querySelectorAll('.edit-kelas-checkbox').forEach(cb => {
                    cb.checked = kelasArr.some(k => k.trim() === cb.value);
                });

                isWaValidated_Edit = true; // Flag aman karena diisi dengan nomor (tersensor) asli dari DB
                openModal(modals.edit);
            }

            if (e.target.closest('.cetak-kartu-btn')) {
                e.preventDefault();
                const btn = e.target.closest('.cetak-kartu-btn');
                const loader = document.getElementById('downloadLoader');
                loader.classList.remove('hidden');

                fetch(btn.getAttribute('href'))
                    .then(response => {
                        if (!response.ok) throw new Error('Gagal mengambil data kartu akses.');
                        return response.blob();
                    })
                    .then(blob => {
                        const downloadUrl = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = downloadUrl;
                        a.download = `Kartu_Akses_${btn.dataset.nama.replace(/\s+/g, '_')}.png`;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(downloadUrl);
                    })
                    .catch(err => Swal.fire('Error', err.message, 'error'))
                    .finally(() => loader.classList.add('hidden'));
            }

            if (e.target.closest('.reset-pin-btn')) {
                const btn = e.target.closest('.reset-pin-btn');
                targetUserId = btn.dataset.id;
                targetUserBarcode = btn.dataset.barcode;
                targetUserRole = btn.dataset.role;
                document.getElementById('reset_target_nama').textContent = btn.dataset.nama;

                document.getElementById('superadmin_pin').value = '';
                document.getElementById('reset_error_msg').classList.add('hidden');
                openModal(modals.reset);
                setTimeout(() => document.getElementById('superadmin_pin').focus(), 100);
            }
        });

        // --- CEK WA API (Manual Trigger) ---
        function setupCekWa(inputId, btnId, msgId) {
            const btn = document.getElementById(btnId);
            if (!btn) return;
            btn.addEventListener('click', function() {
                const noHp = document.getElementById(inputId).value;
                const msgEl = document.getElementById(msgId);
                if (!noHp) return Swal.fire('Peringatan', 'Masukkan nomor WA terlebih dahulu!', 'warning');

                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Cek...';
                msgEl.classList.add('hidden');

                const fd = new FormData();
                fd.append('nomor_hp', noHp);

                fetch('pages/master/ajax_cek_wa.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(res => res.json())
                    .then(data => {
                        msgEl.classList.remove('hidden');
                        if (data.status) {
                            msgEl.className = "mt-1 text-xs font-medium text-green-600";
                            msgEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + data.pesan;
                            // Sukses! Loloskan verifikasi form lokal
                            if (inputId === 'tambah_nomor_wa') isWaValidated_Tambah = true;
                            if (inputId === 'edit_nomor_wa') isWaValidated_Edit = true;
                        } else {
                            msgEl.className = "mt-1 text-xs font-medium text-red-600";
                            msgEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + data.pesan;
                            // Gagal! Tetap tolak
                            if (inputId === 'tambah_nomor_wa') isWaValidated_Tambah = false;
                            if (inputId === 'edit_nomor_wa') isWaValidated_Edit = false;
                        }
                    })
                    .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan sistem saat mengecek nomor WA.', 'error'))
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            });
        }
        setupCekWa('tambah_nomor_wa', 'btnCekWaTambah', 'msgCekWaTambah');
        setupCekWa('edit_nomor_wa', 'btnCekWaEdit', 'msgCekWaEdit');

        // --- FORM SUBMIT AJAX ---
        function submitAjaxForm(e, manualData = null) {
            if (e) e.preventDefault();

            let formData = new FormData();
            let actionType = '';

            if (manualData) {
                for (const key in manualData) formData.append(key, manualData[key]);
                actionType = manualData.action;
            } else {
                formData = new FormData(e.target);
                actionType = formData.get('action');

                // Logika Gabungan Checkbox Kelas & Validasi Wajib
                if (actionType === 'tambah_guru' || actionType === 'edit_guru') {
                    const checkboxes = e.target.querySelectorAll('input[type="checkbox"]:checked');
                    let kelasArr = [];
                    checkboxes.forEach((cb) => kelasArr.push(cb.value));

                    if (kelasArr.length > 0) {
                        if (actionType === 'tambah_guru') formData.append('kelas', kelasArr.join(','));
                        if (actionType === 'edit_guru') formData.append('edit_kelas', kelasArr.join(','));
                    } else {
                        Swal.fire('Peringatan', 'Pilih minimal 1 kelas yang diampu!', 'warning');
                        return;
                    }

                    // BLOKIR JIKA BELUM DICEK WA
                    if (actionType === 'tambah_guru' && !isWaValidated_Tambah) {
                        Swal.fire('Belum divalidasi', 'Anda wajib menekan tombol "Tes Cek WA" untuk memastikan nomor WhatsApp benar-benar terdaftar sebelum menyimpan!', 'warning');
                        return;
                    }
                    if (actionType === 'edit_guru') {
                        const editWaVal = document.getElementById('edit_nomor_wa').value;
                        // Jika teks tidak memuat bintang (artinya nomor diubah) dan status validasinya false -> Tolak
                        if (!editWaVal.includes('*') && !isWaValidated_Edit) {
                            Swal.fire('Belum divalidasi', 'Nomor WA telah diubah. Anda wajib menekan tombol "Tes Cek WA" untuk memvalidasi nomor baru sebelum mengupdate!', 'warning');
                            return;
                        }
                    }
                }
            }

            Swal.fire({
                title: 'Memproses...',
                text: 'Tunggu sebentar, data sedang disimpan.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(API_URL, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message,
                            showConfirmButton: false,
                            timer: 1500
                        });
                        Object.values(modals).forEach(closeModal);
                        loadData();
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Terjadi kesalahan jaringan.', 'error');
                });
        }

        document.getElementById('formTambahGuru').addEventListener('submit', submitAjaxForm);
        document.getElementById('formEditGuru').addEventListener('submit', submitAjaxForm);
        document.getElementById('formHapusGuru').addEventListener('submit', submitAjaxForm);

        // Fitur Khusus Reset PIN
        const btnConfirmReset = document.getElementById('btnConfirmReset');
        const errorMsg = document.getElementById('reset_error_msg');
        let targetUserId = null,
            targetUserBarcode = null,
            targetUserRole = 'guru';

        if (btnConfirmReset) {
            btnConfirmReset.addEventListener('click', function() {
                const adminPin = document.getElementById('superadmin_pin').value;
                if (!adminPin) {
                    errorMsg.textContent = "PIN Superadmin wajib diisi.";
                    errorMsg.classList.remove('hidden');
                    return;
                }

                const originalBtnText = btnConfirmReset.innerText;
                btnConfirmReset.disabled = true;
                btnConfirmReset.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

                fetch('pages/master/ajax_reset_pin.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            'target_id': targetUserId,
                            'target_barcode': targetUserBarcode,
                            'target_role': targetUserRole,
                            'admin_pin': adminPin
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Berhasil!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            closeModal(modals.reset);
                        } else {
                            Swal.fire({
                                title: 'Gagal!',
                                text: data.message,
                                icon: 'error',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            errorMsg.classList.remove('hidden');
                        }
                    })
                    .catch(err => {
                        errorMsg.textContent = "Terjadi kesalahan server.";
                        errorMsg.classList.remove('hidden');
                        console.error(err);
                    })
                    .finally(() => {
                        btnConfirmReset.disabled = false;
                        btnConfirmReset.innerText = originalBtnText;
                    });
            });
        }

        loadData();
    });
</script>