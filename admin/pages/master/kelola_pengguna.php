<?php
// === FILE FRONTEND: kelola_pengguna.php ===
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
?>

<div class="container mx-auto relative">
    <!-- Header Halaman -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Kelola Admin</h3>
        <button id="tambahAdminBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2 shadow transition transform hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Tambah Admin
        </button>
    </div>

    <!-- Tabel Data Admin -->
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
                    <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                    <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama & Wilayah</th>
                    <?php if ($_SESSION['user_role'] == 'superadmin'): ?>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <?php endif; ?>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">QR Code</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody id="adminTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Data dimuat via AJAX -->
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Modal Tambah Admin -->
<div id="tambahAdminModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75 modal-backdrop"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full z-50">
            <form id="formTambahAdmin">
                <input type="hidden" name="action" value="tambah_admin">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Tambah Admin</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="nama" class="block text-sm font-medium text-gray-700">Nama Lengkap*</label>
                            <input type="text" name="nama" class="mt-1 w-full border border-gray-300 p-2 rounded-md focus:ring-2 focus:ring-green-500 outline-none" required>
                        </div>
                        <div>
                            <label for="nama_panggilan" class="block text-sm font-medium text-gray-700">Nama Panggilan*</label>
                            <input type="text" name="nama_panggilan" class="mt-1 w-full border border-gray-300 p-2 rounded-md focus:ring-2 focus:ring-green-500 outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nomor WA* <span class="text-xs text-red-500 font-normal">(Wajib Tes Cek WA sebelum simpan)</span></label>
                            <div class="mt-1 flex rounded-md shadow-sm">
                                <input type="text" name="nomor_wa" id="tambah_nomor_wa" placeholder="Misal: 08123456789" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none sm:text-sm" required>
                                <button type="button" id="btnCekWaTambah" class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-gray-600 text-sm font-medium hover:bg-gray-100 focus:outline-none transition-colors">
                                    Tes Cek WA
                                </button>
                            </div>
                            <p id="msgCekWaTambah" class="mt-1 text-xs hidden"></p>
                        </div>
                        <!-- Info Login Hidden -->
                        <div class="text-xs text-gray-500 italic bg-gray-50 p-2 rounded">
                            <span class="font-semibold text-gray-700">Catatan:</span> Akun login dan barcode akan digenerate otomatis oleh sistem. WA Notifikasi akan dikirimkan ke Admin KBM.
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="tingkat" class="block text-sm font-medium text-gray-700">Tingkat</label>
                                <select name="tingkat" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                    <option value="desa">Desa</option>
                                    <option value="kelompok">Kelompok</option>
                                </select>
                            </div>
                            <div>
                                <label for="kelompok" class="block text-sm font-medium text-gray-700">Kelompok</label>
                                <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                    <option value="bintaran">Bintaran</option>
                                    <option value="gedongkuning">Gedongkuning</option>
                                    <option value="jombor">Jombor</option>
                                    <option value="sunten">Sunten</option>
                                </select>
                            </div>
                        </div>
                        <?php if ($_SESSION['user_role'] == 'superadmin'): ?>
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                                <select name="role" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                    <option value="admin">Admin</option>
                                    <option value="superadmin">Developer</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="role" value="admin">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Admin -->
<div id="editAdminModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75 modal-backdrop"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full z-50">
            <form id="formEditAdmin">
                <input type="hidden" name="action" value="edit_admin">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Edit Admin</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="edit_nama" class="block text-sm font-medium text-gray-700">Nama Lengkap*</label>
                            <input type="text" name="edit_nama" id="edit_nama" class="mt-1 w-full border border-gray-300 p-2 rounded-md focus:ring-2 focus:ring-indigo-500 outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Username (System Generated)</label>
                            <input type="text" id="view_username" class="mt-1 w-full border border-gray-200 bg-gray-100 text-gray-500 p-2 rounded-md cursor-not-allowed font-mono sm:text-sm" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nomor WA* <span class="text-xs text-red-500 font-normal">(Jika diubah, wajib Tes Cek WA)</span></label>
                            <div class="mt-1 flex rounded-md shadow-sm">
                                <input type="text" name="edit_nomor_wa" id="edit_nomor_wa" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none sm:text-sm" required>
                                <button type="button" id="btnCekWaEdit" class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-gray-600 text-sm font-medium hover:bg-gray-100 focus:outline-none transition-colors">
                                    Tes Cek WA
                                </button>
                            </div>
                            <p id="msgCekWaEdit" class="mt-1 text-xs hidden"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="edit_tingkat" class="block text-sm font-medium text-gray-700">Tingkat</label>
                                <select name="edit_tingkat" id="edit_tingkat" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                    <option value="desa">Desa</option>
                                    <option value="kelompok">Kelompok</option>
                                </select>
                            </div>
                            <div>
                                <label for="edit_kelompok" class="block text-sm font-medium text-gray-700">Kelompok</label>
                                <select name="edit_kelompok" id="edit_kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                    <option value="bintaran">Bintaran</option>
                                    <option value="gedongkuning">Gedongkuning</option>
                                    <option value="jombor">Jombor</option>
                                    <option value="sunten">Sunten</option>
                                </select>
                            </div>
                        </div>
                        <?php if ($_SESSION['user_role'] == 'superadmin'): ?>
                            <div>
                                <label for="edit_role" class="block text-sm font-medium text-gray-700">Role</label>
                                <select name="edit_role" id="edit_role" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                    <option value="admin">Admin</option>
                                    <option value="superadmin">Developer</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="edit_role" id="edit_role">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Update</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div id="hapusAdminModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75 modal-backdrop"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-50">
            <form id="formHapusAdmin">
                <input type="hidden" name="action" value="hapus_admin">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Hapus Admin</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Anda yakin ingin menghapus pengguna <strong id="hapus_nama" class="text-gray-900"></strong>?<br>Tindakan ini hanya akan diarsipkan (Soft Delete).</p>
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

<!-- Modal QR Code -->
<div id="qrCodeModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75 modal-backdrop"></div>
        <div class="bg-white rounded-lg text-center p-6 overflow-hidden shadow-xl transform transition-all sm:max-w-sm sm:w-full z-50">
            <h3 class="text-lg font-medium text-gray-900">QR Code untuk <span id="qr_nama" class="font-bold"></span></h3>
            <div id="qrcode-container" class="my-4 flex justify-center"></div>
            <a id="download-qr-link" href="#" download="qrcode.png" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">Download</a>
            <button type="button" class="modal-close-btn ml-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Lihat Foto -->
<div id="imageModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black bg-opacity-75 transition-opacity modal-backdrop"></div>
    <div class="fixed inset-0 z-10 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-lg shadow-xl overflow-hidden max-w-3xl w-full max-h-[90vh] flex flex-col z-50">
            <div class="absolute top-2 right-2 z-20">
                <button type="button" class="modal-close-btn bg-gray-200 hover:bg-gray-300 text-gray-600 rounded-full p-2 focus:outline-none">
                    <svg class="h-6 w-6 pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="flex items-center justify-center bg-gray-100 h-full w-full p-2">
                <img id="modalImageDisplay" class="max-w-full max-h-[85vh] object-contain rounded" src="" alt="Preview Foto">
            </div>
        </div>
    </div>
</div>

<!-- MODAL RESET PIN -->
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

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<script>
    const userRoleSession = '<?= $_SESSION['user_role'] ?? 'admin' ?>';
    const currentUserId = '<?= $_SESSION['user_id'] ?? '' ?>';
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const API_URL = 'pages/master/ajax_kelola_pengguna.php';

        // --- FLAG VALIDASI WA FRONTEND ---
        let isWaValidated_Tambah = false;
        let isWaValidated_Edit = true;

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
            tambah: document.getElementById('tambahAdminModal'),
            edit: document.getElementById('editAdminModal'),
            hapus: document.getElementById('hapusAdminModal'),
            reset: document.getElementById('resetPinModal'),
            qr: document.getElementById('qrCodeModal'),
            image: document.getElementById('imageModal')
        };
        const openModal = (m) => m && m.classList.remove('hidden');
        const closeModal = (m) => m && m.classList.add('hidden');

        document.body.addEventListener('click', function(e) {
            if (e.target.closest('.modal-close-btn') || e.target.classList.contains('modal-backdrop')) {
                Object.values(modals).forEach(closeModal);
                if (modals.image) document.getElementById('modalImageDisplay').src = '';
            }
        });

        document.getElementById('tambahAdminBtn').onclick = () => {
            document.getElementById('formTambahAdmin').reset();
            document.getElementById('msgCekWaTambah').classList.add('hidden');
            isWaValidated_Tambah = false;
            openModal(modals.tambah);
        };

        // --- HELPER UNTUK MENGHILANGKAN OVERLAY GLOBAL ---
        const hideGlobalOverlay = () => {
            const indexOverlay = document.getElementById('loading-overlay');
            if (indexOverlay) {
                indexOverlay.classList.remove('show');
                indexOverlay.classList.add('hidden');
                indexOverlay.style.display = 'none';
                indexOverlay.style.opacity = '0';
                indexOverlay.style.zIndex = '-1';
            }
        };

        // --- KEMBALIKAN OVERLAY SAAT PINDAH HALAMAN (KLIK LINK) ---
        document.body.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link && link.href && !link.hasAttribute('download') && !link.href.includes('javascript:')) {
                const indexOverlay = document.getElementById('loading-overlay');
                if (indexOverlay) {
                    indexOverlay.classList.remove('hidden');
                    indexOverlay.style.display = '';
                    indexOverlay.style.opacity = '';
                    indexOverlay.style.zIndex = '';
                }
            }
        });

        // --- LOAD DATA (GET) ---
        function loadData() {
            const loader = document.getElementById('tableLoader');
            loader.classList.remove('hidden');

            fetch(`${API_URL}?action=get_data`)
                .then(res => {
                    hideGlobalOverlay();
                    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                    return res.text();
                })
                .then(text => {
                    try {
                        const res = JSON.parse(text);
                        if (res.status === 'success') {
                            renderTable(res.data);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    } catch (e) {
                        console.error("JSON Parse Error:", e, "Response Text:", text);
                        Swal.fire({
                            title: 'Error Server',
                            text: 'Terjadi kesalahan sistem. Silakan cek Console (F12).',
                            icon: 'error',
                            customClass: {
                                container: 'z-[99999]'
                            }
                        });
                    }
                })
                .catch(err => {
                    hideGlobalOverlay();
                    console.error(err);
                    Swal.fire({
                        title: 'Error Jaringan',
                        text: 'Gagal mengambil data. Periksa koneksi internet Anda.',
                        icon: 'error',
                        customClass: {
                            container: 'z-[99999]'
                        }
                    });
                })
                .finally(() => {
                    loader.classList.add('hidden');
                });
        }

        // --- RENDER TABEL ---
        function renderTable(data) {
            const tbody = document.getElementById('adminTableBody');
            tbody.innerHTML = '';

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-gray-500">Tidak ada data.</td></tr>';
                return;
            }

            data.forEach(p => {
                let statusBadge = p.status_login === 'online' ?
                    `<span class="inline-flex items-center gap-1.5 py-1 px-2 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 inline-block bg-emerald-500 rounded-full"></span>Online</span>` :
                    `<span class="inline-flex flex-col py-1 px-2 rounded-md text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200"><span class="flex items-center justify-center gap-1"><span class="w-1.5 h-1.5 inline-block bg-gray-400 rounded-full"></span>Offline</span><span class="text-[10px] text-gray-400 font-normal mt-0.5" title="Terakhir Login">Terakhir: ${p.terakhir_login}</span></span>`;

                let isSelf = (currentUserId === p.id);
                let roleBadge = userRoleSession === 'superadmin' ? `<td class="px-6 py-4 text-center whitespace-nowrap capitalize align-middle">${p.role === 'superadmin' ? 'Developer' : p.role}</td>` : '';

                const fotoUrl = `../uploads/profiles/${p.foto_profil || 'default.png'}`;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td class="py-4 whitespace-nowrap flex justify-center items-center align-middle">
                    <img class="w-8 h-8 rounded-full object-cover border-2 border-gray-300 cursor-pointer hover:opacity-80 transition photo-btn" 
                        src="${fotoUrl}" onerror="this.src='../uploads/profiles/default.png';">
                </td>
                <td class="py-4 whitespace-nowrap align-middle">
                    <div class="font-medium text-gray-900">${p.nama}</div>
                    <div class="text-xs text-gray-500 capitalize">${p.tingkat} - ${p.kelompok}</div>
                </td>
                ${roleBadge}
                <td class="px-6 py-4 text-center align-middle">${statusBadge}</td>
                <td class="px-6 py-4 text-center whitespace-nowrap align-middle">
                    <button class="qr-code-btn text-blue-500 hover:text-blue-700 font-medium"
                        data-barcode="${p.barcode}" data-nama="${p.nama}" data-tingkat="${p.tingkat}" data-kelompok="${p.kelompok}" data-role="${p.role}">
                        <i class="fa-solid fa-qrcode mr-1"></i> Lihat
                    </button>
                </td>
                <td class="px-6 py-4 text-center whitespace-nowrap text-sm font-medium align-middle">
                    <div class="flex flex-col gap-2 w-max mx-auto">
                        <button class="edit-btn text-left text-indigo-600 hover:text-indigo-900"
                            data-id="${p.id}" data-nama="${p.nama}" data-username="${p.username}" 
                            data-kelompok="${p.kelompok}" data-tingkat="${p.tingkat}" data-role="${p.role}" data-nomor_wa="${p.nomor_wa || ''}">
                            <i class="fa-solid fa-pen mr-1"></i> Edit
                        </button>
                        <button class="hapus-btn text-left text-red-600 hover:text-red-900" data-id="${p.id}" data-nama="${p.nama}">
                            <i class="fa-solid fa-trash mr-1"></i> Hapus
                        </button>
                        ${userRoleSession === 'superadmin' ? `
                            <button class="reset-pin-btn text-left ${isSelf ? 'text-gray-400 cursor-not-allowed' : 'text-yellow-600 hover:text-yellow-800'}"
                                data-id="${p.id}" data-nama="${p.nama}" data-barcode="${p.barcode}" data-role="admin" ${isSelf ? 'disabled title="Tidak bisa reset PIN sendiri"' : ''}>
                                <i class="fa-solid fa-key mr-1"></i> Reset PIN
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
                tbody.appendChild(tr);
            });
        }

        // --- EVENT DELEGATION: TABLE BUTTONS ---
        document.body.addEventListener('click', function(e) {

            // Lihat Foto
            if (e.target.closest('.photo-btn')) {
                const img = e.target.closest('.photo-btn');
                document.getElementById('modalImageDisplay').src = img.src;
                openModal(modals.image);
            }

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
                if (document.getElementById('edit_tingkat')) document.getElementById('edit_tingkat').value = btn.dataset.tingkat;
                if (document.getElementById('edit_role')) document.getElementById('edit_role').value = btn.dataset.role;

                isWaValidated_Edit = true; // Flag aman
                openModal(modals.edit);
            }

            if (e.target.closest('.qr-code-btn')) {
                const btn = e.target.closest('.qr-code-btn');
                openModal(modals.qr);
                const container = document.getElementById('qrcode-container');
                const downloadLink = document.getElementById('download-qr-link');
                container.innerHTML = '';
                document.getElementById('qr_nama').textContent = btn.dataset.nama;

                const namaPemilik = btn.dataset.nama;
                const tingkatPemilik = btn.dataset.tingkat;
                const kelompokPemilik = btn.dataset.kelompok;
                const rolePemilik = btn.dataset.role;

                const newDownloadLink = downloadLink.cloneNode(true);
                downloadLink.parentNode.replaceChild(newDownloadLink, downloadLink);

                newDownloadLink.addEventListener('click', function() {
                    const formData = new FormData();
                    formData.append('log_type', 'EXPORT');
                    let pesanDinamis = rolePemilik === 'superadmin' ? `Mendownload file QR Code *${namaPemilik}* - Developer.` :
                        tingkatPemilik === 'desa' ? `Mendownload file QR Code *${namaPemilik}* - Admin Desa.` :
                        `Mendownload file QR Code *${namaPemilik}* - Admin Kelompok ${kelompokPemilik}.`;

                    formData.append('message', pesanDinamis);
                    fetch('helpers/ajax_writeLog.php', {
                            method: 'POST',
                            body: formData
                        })
                        .catch(err => console.error("Error fetch log:", err));
                });

                new QRCode(container, {
                    text: btn.dataset.barcode,
                    width: 200,
                    height: 200
                });
                setTimeout(() => {
                    const canvas = container.querySelector('canvas');
                    if (canvas) {
                        newDownloadLink.href = canvas.toDataURL("image/png");
                        newDownloadLink.download = `qrcode-${namaPemilik.replace(/\s+/g, '-')}.png`;
                    }
                }, 100);
            }

            if (e.target.closest('.reset-pin-btn')) {
                const btn = e.target.closest('.reset-pin-btn');
                if (btn.disabled) return;

                targetUserId = btn.dataset.id;
                targetUserBarcode = btn.dataset.barcode;
                targetUserRole = 'admin'; // Cukup kirim admin ke ajax_reset_pin karena tabel users
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
                            if (inputId === 'tambah_nomor_wa') isWaValidated_Tambah = true;
                            if (inputId === 'edit_nomor_wa') isWaValidated_Edit = true;
                        } else {
                            msgEl.className = "mt-1 text-xs font-medium text-red-600";
                            msgEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + data.pesan;
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

                // BLOKIR JIKA BELUM DICEK WA
                if (actionType === 'tambah_admin' && !isWaValidated_Tambah) {
                    Swal.fire('Belum divalidasi', 'Anda wajib menekan tombol "Tes Cek WA" untuk memastikan nomor WhatsApp benar-benar terdaftar sebelum menyimpan!', 'warning');
                    return;
                }
                if (actionType === 'edit_admin') {
                    const editWaVal = document.getElementById('edit_nomor_wa').value;
                    if (!editWaVal.includes('*') && !isWaValidated_Edit) {
                        Swal.fire('Belum divalidasi', 'Nomor WA telah diubah. Anda wajib menekan tombol "Tes Cek WA" untuk memvalidasi nomor baru sebelum mengupdate!', 'warning');
                        return;
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
                        if (modals.image) document.getElementById('modalImageDisplay').src = '';
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

        document.getElementById('formTambahAdmin').addEventListener('submit', submitAjaxForm);
        document.getElementById('formEditAdmin').addEventListener('submit', submitAjaxForm);
        document.getElementById('formHapusAdmin').addEventListener('submit', submitAjaxForm);

        // Fitur Khusus Reset PIN
        const btnConfirmReset = document.getElementById('btnConfirmReset');
        const errorMsg = document.getElementById('reset_error_msg');
        let targetUserId = null,
            targetUserBarcode = null,
            targetUserRole = 'admin';

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