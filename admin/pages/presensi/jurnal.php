<?php
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

// === PERIODE DEFAULT ===
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

// === FILTER DARI URL ===
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : $default_periode_id;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
}

// === AMBIL DATA JURNAL ===
$jurnal_list = [];
if ($selected_periode_id) {
    $sql_jurnal = "SELECT * FROM jadwal_presensi WHERE periode_id = ? AND pengajar IS NOT NULL AND pengajar != ''";
    $params = [$selected_periode_id];
    $types = "i";

    if ($selected_kelompok !== 'semua') {
        $sql_jurnal .= " AND kelompok = ?";
        $params[] = $selected_kelompok;
        $types .= "s";
    }
    if ($selected_kelas !== 'semua') {
        $sql_jurnal .= " AND kelas = ?";
        $params[] = $selected_kelas;
        $types .= "s";
    }
    $sql_jurnal .= " ORDER BY tanggal DESC, kelompok, kelas";

    $stmt_jurnal = $conn->prepare($sql_jurnal);
    if (!empty($params)) {
        $stmt_jurnal->bind_param($types, ...$params);
    }
    $stmt_jurnal->execute();
    $result_jurnal = $stmt_jurnal->get_result();
    if ($result_jurnal) {
        while ($row = $result_jurnal->fetch_assoc()) {
            $jurnal_list[] = $row;
        }
    }
}
?>

<div class="container mx-auto space-y-6">
    <!-- BAGIAN 1: FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-medium text-gray-800">Filter Jurnal Harian</h3>
            <!-- Tombol Buka Modal Export -->
            <?php if ($selected_periode_id): ?>
                <button type="button" id="btn-buka-export" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center transition duration-300">
                    <i class="fa-solid fa-file-pdf mr-2"></i>
                    Export
                </button>
            <?php endif; ?>
        </div>

        <form method="GET" action="" id="filterForm">
            <input type="hidden" name="page" value="presensi/jurnal">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div><label class="block text-sm font-medium">Periode</label>
                    <select name="periode_id" id="filter_periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required onchange="this.form.submit()">
                        <option value="">-- Pilih Periode --</option>
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_periode']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md border border-gray-300 py-2 px-3 text-gray-500" disabled>
                        <input type="hidden" name="kelompok" id="filter_kelompok" value="<?php echo $admin_kelompok; ?>">
                    <?php else: ?>
                        <select name="kelompok" id="filter_kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="semua">Semua Kelompok</option>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div><label class="block text-sm font-medium">Kelas</label>
                    <select name="kelas" id="filter_kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="semua">Semua Kelas</option><?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                                                                    foreach ($kelas_opts as $k): ?><option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">Tampilkan</button></div>
            </div>
        </form>
    </div>

    <!-- BAGIAN 2: DAFTAR JURNAL -->
    <?php if ($selected_periode_id): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-medium text-gray-800 mb-4">Daftar Jurnal yang Telah Diisi</h3>
            <div class="space-y-4">
                <?php if (empty($jurnal_list)): ?>
                    <p class="text-center text-gray-500 py-8">Tidak ada jurnal yang cocok dengan filter yang dipilih.</p>
                    <?php else: foreach ($jurnal_list as $jurnal): ?>
                        <div class="border rounded-lg p-4 bg-gray-50 hover:bg-gray-100 transition duration-150">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                                <div class="mb-2 md:mb-0">
                                    <p class="font-bold text-gray-800 text-lg"><?php echo format_hari_tanggal($jurnal['tanggal']); ?></p>
                                    <p class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($jurnal['kelompok'] . ' - ' . $jurnal['kelas']); ?></p>
                                </div>
                                <div class="text-left md:text-right">
                                    <p class="text-sm text-gray-600">Pengajar: <span class="font-semibold text-indigo-700"><?php echo htmlspecialchars($jurnal['pengajar']); ?></span></p>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <h4 class="font-semibold text-gray-700 text-sm mb-2">Materi yang Disampaikan:</h4>
                                <ul class="list-disc list-inside text-gray-600 text-sm space-y-1 pl-2">
                                    <?php if (!empty($jurnal['materi1'])): ?><li><?php echo htmlspecialchars($jurnal['materi1']); ?></li><?php endif; ?>
                                    <?php if (!empty($jurnal['materi2'])): ?><li><?php echo htmlspecialchars($jurnal['materi2']); ?></li><?php endif; ?>
                                    <?php if (!empty($jurnal['materi3'])): ?><li><?php echo htmlspecialchars($jurnal['materi3']); ?></li><?php endif; ?>
                                    <?php if (empty($jurnal['materi1']) && empty($jurnal['materi2']) && empty($jurnal['materi3'])): ?>
                                        <li class="italic text-gray-400">Tidak ada detail materi yang diisi.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- === MODAL EXPORT JURNAL (LENGKAP DENGAN LOADER) === -->
<div id="exportModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 text-center sm:block sm:p-0">
        <!-- Background Overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="overlay-export-modal"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Export Data Jurnal
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 mb-4">
                                Data yang akan di-export sesuai dengan filter yang Anda pilih saat ini.
                            </p>

                            <!-- FORM EXPORT (ID ditambahkan) -->
                            <form id="form-ekspor-jurnal" method="POST">
                                <!-- Input Hidden untuk Filter (diisi via JS saat modal dibuka) -->
                                <input type="hidden" name="periode_id" id="export_periode_id">
                                <input type="hidden" name="kelompok" id="export_kelompok">
                                <input type="hidden" name="kelas" id="export_kelas">

                                <div class="mb-4">
                                    <label for="format" class="block text-sm font-medium text-gray-700 mb-2">Pilih Format</label>
                                    <select name="format" id="format" class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="pdf">PDF (Dokumen Cetak)</option>
                                        <option value="csv">CSV (Excel)</option>
                                    </select>
                                </div>

                                <!-- BUTTON CONTAINER (Default Visible) -->
                                <div id="button-container-export" class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse -mx-6 -mb-6 mt-4">
                                    <button type="submit" id="btn-submit-export" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                        Download
                                    </button>
                                    <button type="button" id="btn-cancel-export" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                        Batal
                                    </button>
                                </div>

                                <!-- LOADER CONTAINER (Default Hidden) -->
                                <div id="loader-export" class="hidden bg-gray-50 px-4 py-3 sm:px-6 -mx-6 -mb-6 mt-4 text-center">
                                    <!-- Simple CSS Spinner -->
                                    <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="text-sm font-medium text-gray-600 mt-2">Sedang memproses file...</p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT PENGENDALI MODAL & FETCH -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnBuka = document.getElementById('btn-buka-export');
        const modal = document.getElementById('exportModal');
        const overlay = document.getElementById('overlay-export-modal');
        const btnBatal = document.getElementById('btn-cancel-export');
        const formExport = document.getElementById('form-ekspor-jurnal');
        const btnSubmit = document.getElementById('btn-submit-export');
        const btnContainer = document.getElementById('button-container-export');
        const loader = document.getElementById('loader-export');

        function openExportModal() {
            if (!modal) return;

            // 1. Ambil nilai filter dari halaman utama
            const periodeVal = document.getElementById('filter_periode_id').value;
            const kelompokVal = document.getElementById('filter_kelompok').value;
            const kelasVal = document.getElementById('filter_kelas').value;

            // 2. Isi ke input hidden
            document.getElementById('export_periode_id').value = periodeVal;
            document.getElementById('export_kelompok').value = kelompokVal;
            document.getElementById('export_kelas').value = kelasVal;

            // 3. Reset tampilan modal (sembunyikan loader, tampilkan tombol)
            loader.classList.add('hidden');
            btnContainer.classList.remove('hidden');
            btnSubmit.disabled = false;

            // 4. Tampilkan Modal
            modal.classList.remove('hidden');
        }

        function closeExportModal() {
            if (modal) modal.classList.add('hidden');
        }

        if (btnBuka) btnBuka.addEventListener('click', openExportModal);
        if (btnBatal) btnBatal.addEventListener('click', closeExportModal);
        if (overlay) overlay.addEventListener('click', closeExportModal);

        // --- LOGIKA FETCH & AUTO RELOAD ---
        if (formExport) {
            formExport.addEventListener('submit', function(e) {
                e.preventDefault(); // Mencegah submit browser biasa

                // UI Loading
                btnContainer.classList.add('hidden');
                loader.classList.remove('hidden');
                btnSubmit.disabled = true;

                const formData = new FormData(formExport);
                const targetUrl = 'pages/export/export_jurnal.php';

                fetch(targetUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Gagal mengambil data dari server.');
                        }

                        // Ambil nama file dari header Content-Disposition
                        const contentDisposition = response.headers.get('content-disposition');
                        let filename = 'Jurnal_KBM.pdf';
                        if (contentDisposition) {
                            const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
                            if (filenameMatch && filenameMatch[1]) {
                                filename = filenameMatch[1];
                            }
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
                        // Buat link download virtual
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();

                        // Bersihkan
                        window.URL.revokeObjectURL(url);
                        a.remove();

                        // Tutup Modal
                        closeExportModal();

                        // Refresh halaman setelah delay (Auto Reload)
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan saat export data. Coba lagi.');

                        // Reset UI jika error
                        loader.classList.add('hidden');
                        btnContainer.classList.remove('hidden');
                        btnSubmit.disabled = false;
                    });
            });
        }
    });
</script>