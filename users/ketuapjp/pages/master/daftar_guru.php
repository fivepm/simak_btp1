<?php
// === FILE FRONTEND: kelola_guru.php ===
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL (sebagai default awal)
$filter_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

if ($ketuapjp_tingkat === 'kelompok') {
    $filter_kelompok = $ketuapjp_kelompok;
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
                    <?php if ($ketuapjp_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?= ucfirst($ketuapjp_kelompok) ?>" class="mt-1 w-full bg-gray-100 p-2 rounded border border-gray-200" disabled>
                        <input type="hidden" id="filter_kelompok" value="<?= $ketuapjp_kelompok ?>">
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase w-1/4">Nama</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Kelompok</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase w-1/4">Kelas Diampu</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody id="guruTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Data akan dimuat via AJAX -->
            </tbody>
        </table>
    </div>
</div>

<script>
    const userRoleSession = '<?= $_SESSION['user_role'] ?? 'ketuapjp' ?>';
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const API_URL = 'pages/master/ajax_daftar_guru.php'
        // --- LOAD DATA (GET) ---
        function loadData() {
            const kelompok = document.getElementById('filter_kelompok').value;
            const kelas = document.getElementById('filter_kelas').value;
            const loader = document.getElementById('tableLoader');
            const tbody = document.getElementById('guruTableBody');

            loader.classList.remove('hidden');

            fetch(`${API_URL}?action=get_data&kelompok=${kelompok}&kelas=${kelas}`)
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                    return res.text(); // Parse sebagai teks dulu untuk menangani kemungkinan error PHP HTML
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
                            text: 'Terjadi kesalahan sistem (Respon bukan JSON). Silakan cek Console (F12).',
                            icon: 'error',
                            customClass: {
                                container: 'z-[99999]'
                            } // Paksa tampil di atas segalanya
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({
                        title: 'Error Jaringan',
                        text: 'Gagal mengambil data dari server. Periksa koneksi internet Anda.',
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
                    </td>
                <td class="px-6 py-4 text-center align-top flex-wrap justify-center uppercase">${g.kelompok}</td>
                <td class="px-6 py-4 text-center align-top max-w-[200px] flex-wrap justify-center">${kelasHtml}</td>
                <td class="px-6 py-4 text-center align-top">${statusBadge}</td>
            `;
                tbody.appendChild(tr);
            });
        }

        // --- FILTER ---
        document.getElementById('btnTerapkanFilter').addEventListener('click', loadData);

        loadData();
    });
</script>