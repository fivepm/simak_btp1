<?php
// Pastikan koneksi database tersedia
if (!isset($conn)) die("Koneksi database gagal.");

// === 1. LOGIC FILTER ===
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// --- A. DATA PERIODE ---
$periode_list = [];
$sql_periode = "SELECT id, nama_periode, tanggal_mulai, tanggal_selesai FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

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

// --- B. DATA KELOMPOK ---
$list_kelompok = ['Bintaran', 'Gedongkuning', 'Jombor', 'Sunten'];

// --- C. DATA MAPEL ---
$list_mapel = [];
$q_mapel = $conn->query("SELECT * FROM master_materi ORDER BY nama_kategori ASC");
while ($r = $q_mapel->fetch_assoc()) $list_mapel[] = $r;

// --- D. GET FILTER VALUES ---
$filter_periode = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : $default_periode_id;
$filter_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : '';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

if ($admin_tingkat == 'kelompok') {
    $filter_kelompok = $admin_kelompok;
}

// === 2. QUERY DATA TARGET ===
$target_list = [];
if (!empty($filter_periode) && !empty($filter_kelompok) && !empty($filter_kelas)) {
    $sql = "SELECT * FROM target_pembelajaran 
            WHERE periode_id = ? AND kelompok = ? AND kelas = ? 
            ORDER BY kategori ASC, id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $filter_periode, $filter_kelompok, $filter_kelas);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $target_list[] = $row;
    }
}
?>

<style>
    /* CSS Khusus agar text di dropdown tidak terpotong (...) di mobile */
    .select-wrap-text {
        white-space: normal !important;
        word-wrap: break-word !important;
        text-overflow: clip !important;
    }

    .select-wrap-text option {
        white-space: normal !important;
        padding: 8px 4px;
        border-bottom: 1px solid #f3f4f6;
    }
</style>

<div class="container mx-auto space-y-6">

    <!-- CARD 1: FILTER -->
    <div class="bg-white p-5 md:p-6 rounded-lg shadow-md border-t-4 border-indigo-600">
        <h1 class="text-lg md:text-xl font-bold text-gray-800 mb-4">Atur Target Pembelajaran (Probul)</h1>

        <form method="GET" action="">
            <input type="hidden" name="page" value="presensi/atur_probul">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                    <select name="periode_id" class="block w-full py-2.5 px-3 border border-gray-300 rounded-lg sm:text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($filter_periode == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nama_periode']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kelompok</label>
                    <?php if ($admin_tingkat == 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="block w-full bg-gray-100 border border-gray-300 rounded-lg py-2.5 px-3 sm:text-sm text-gray-500 cursor-not-allowed" readonly>
                        <input type="hidden" name="kelompok" value="<?php echo htmlspecialchars($admin_kelompok); ?>">
                    <?php else: ?>
                        <select name="kelompok" class="block w-full py-2.5 px-3 border border-gray-300 rounded-lg sm:text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">-- Pilih Kelompok --</option>
                            <?php foreach ($list_kelompok as $kel): ?>
                                <option value="<?php echo $kel; ?>" <?php echo ($filter_kelompok == $kel) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kelas</label>
                    <select name="kelas" class="block w-full py-2.5 px-3 border border-gray-300 rounded-lg sm:text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Pilih Kelas --</option>
                        <?php
                        $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                        foreach ($kelas_opts as $k): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($filter_kelas == $k) ? 'selected' : ''; ?>>
                                <?php echo ucwords($k); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end mt-2 md:mt-0">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 md:py-2 px-4 rounded-lg shadow transition">
                        Tampilkan Target
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- CARD 2: LIST TARGET -->
    <?php if (!empty($filter_periode) && !empty($filter_kelompok) && !empty($filter_kelas)): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-5 md:px-6 py-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center bg-gray-50 gap-3">
                <div>
                    <h3 class="font-bold text-gray-800 text-lg">Daftar Target Pembelajaran</h3>
                    <p class="text-xs md:text-sm text-gray-500 mt-1">
                        Kelas: <span class="font-semibold text-indigo-700"><?php echo ucwords($filter_kelas); ?></span> |
                        Kelompok: <span class="font-semibold text-indigo-700"><?php echo htmlspecialchars($filter_kelompok); ?></span>
                    </p>
                </div>
                <button id="btnTambahTarget" class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white font-bold px-4 py-2.5 md:py-2 rounded-lg shadow text-sm flex items-center justify-center gap-2 transition active:scale-95">
                    <i class="fa-solid fa-plus"></i> Tambah Target Baru
                </button>
            </div>

            <!-- ========================================================= -->
            <!-- 1. DESKTOP VIEW (Tabel Standar) -->
            <!-- ========================================================= -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-white border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 w-10 text-center">No</th>
                            <th class="px-6 py-3">Kategori</th>
                            <th class="px-6 py-3">Judul Materi</th>
                            <th class="px-6 py-3 text-center">Target</th>
                            <th class="px-6 py-3 text-center">Volume Target</th>
                            <th class="px-6 py-3 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($target_list)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-400 border-b border-dashed">
                                    <i class="fa-solid fa-bullseye text-4xl mb-3 text-gray-300"></i><br>
                                    Belum ada target yang diatur untuk kelas ini.<br>
                                    Silakan klik tombol <b>Tambah Target Baru</b>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1;
                            foreach ($target_list as $t):
                                $json = htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8');
                            ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-center font-medium"><?php echo $no++; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded text-[11px] font-bold border border-indigo-100 uppercase tracking-wider">
                                            <?php echo htmlspecialchars($t['kategori']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-900 text-sm">
                                        <?php echo htmlspecialchars($t['judul_materi']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-center font-medium text-gray-900">
                                        <?php if ($t['tipe_input'] == 'CHECKLIST'): ?>
                                            <span class="text-gray-400">-</span>
                                        <?php else:
                                            $target_start = (float)$t['target_start'];
                                            $target_end = (float)$t['target_end'];
                                        ?>
                                            <span class="font-bold text-gray-800 bg-gray-100 px-2 py-1 rounded"><?php echo $target_start; ?> - <?php echo $target_end; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($t['tipe_input'] == 'CHECKLIST'): ?>
                                            <span class="bg-green-50 text-green-700 px-2 py-1 rounded font-bold text-[11px] border border-green-200"><i class="fa-solid fa-check-square"></i> Poin</span>
                                        <?php else:
                                            $vol = (float)$t['total_volume'];
                                        ?>
                                            <span class="font-bold text-gray-800"><?php echo $vol; ?></span>
                                            <span class="text-xs text-gray-500"><?php echo $t['satuan']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center gap-1.5">
                                            <button class="text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white border border-blue-200 btn-edit p-1.5 rounded transition" data-json="<?php echo $json; ?>" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button class="text-red-600 bg-red-50 hover:bg-red-600 hover:text-white border border-red-200 btn-hapus p-1.5 rounded transition" data-id="<?php echo $t['id']; ?>" data-judul="<?php echo htmlspecialchars($t['judul_materi']); ?>" title="Hapus">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ========================================================= -->
            <!-- 2. MOBILE VIEW (Format Grid Card Compact) -->
            <!-- ========================================================= -->
            <div class="block md:hidden p-4 space-y-3 bg-gray-50/50">
                <?php if (empty($target_list)): ?>
                    <div class="py-8 text-center text-gray-400 bg-white border border-gray-200 rounded-xl shadow-sm">
                        <i class="fa-solid fa-bullseye text-4xl mb-3 text-gray-300"></i><br>
                        <span class="text-sm">Belum ada target yang diatur.</span>
                    </div>
                <?php else: ?>
                    <?php $no = 1;
                    foreach ($target_list as $t):
                        $json = htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-3.5 relative overflow-hidden">
                            <!-- Header: Kategori & Tipe -->
                            <div class="flex justify-between items-start mb-2 border-b border-gray-100 pb-2">
                                <span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded text-[9px] font-bold border border-indigo-100 uppercase tracking-wider">
                                    <?php echo htmlspecialchars($t['kategori']); ?>
                                </span>
                                <?php if ($t['tipe_input'] == 'CHECKLIST'): ?>
                                    <span class="bg-green-50 text-green-700 px-1.5 py-0.5 rounded font-bold text-[9px] border border-green-200"><i class="fa-solid fa-check"></i> Poin Checklist</span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-bold text-[9px] border border-gray-200">Volume: <?php echo (float)$t['total_volume'] . ' ' . $t['satuan']; ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Body: Judul Materi & Range -->
                            <div class="mb-3">
                                <h4 class="font-bold text-gray-900 text-[14px] leading-tight mb-1.5"><?php echo htmlspecialchars($t['judul_materi']); ?></h4>
                                <?php if ($t['tipe_input'] == 'RANGE'): ?>
                                    <div class="text-[11px] text-gray-600 bg-gray-50 px-2 py-1 rounded inline-block border border-gray-100">
                                        Target: <span class="font-bold text-gray-800"><?php echo (float)$t['target_start']; ?> - <?php echo (float)$t['target_end']; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Footer: Aksi Edit & Hapus -->
                            <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-gray-50">
                                <button class="col-span-1 text-center bg-blue-50 hover:bg-blue-100 text-blue-700 text-[11px] font-bold py-1.5 rounded-lg flex justify-center items-center gap-1.5 transition btn-edit" data-json="<?php echo $json; ?>">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </button>
                                <button class="col-span-1 text-center bg-red-50 hover:bg-red-100 text-red-600 text-[11px] font-bold py-1.5 rounded-lg flex justify-center items-center gap-1.5 transition btn-hapus" data-id="<?php echo $t['id']; ?>" data-judul="<?php echo htmlspecialchars($t['judul_materi']); ?>">
                                    <i class="fa-solid fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ============================================= -->
<!-- MODAL TAMBAH TARGET -->
<!-- ============================================= -->
<div id="modalTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 hidden backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl md:rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
        <div class="bg-indigo-600 px-5 py-4 flex justify-between items-center">
            <h3 class="text-lg font-bold text-white">Tambah Target Baru</h3>
            <button type="button" onclick="closeModal('modalTarget')" class="text-indigo-100 hover:text-white">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <form id="formTarget" class="p-5 md:p-6 space-y-4 max-h-[85vh] overflow-y-auto custom-scrollbar">
            <input type="hidden" name="action" value="simpan_target">
            <!-- Hidden Filter Data -->
            <input type="hidden" name="periode_id" value="<?php echo $filter_periode; ?>">
            <input type="hidden" name="kelompok" value="<?php echo $filter_kelompok; ?>">
            <input type="hidden" name="kelas" value="<?php echo $filter_kelas; ?>">
            <!-- Hidden Selection Data -->
            <input type="hidden" name="tipe_input_hidden" id="tipe_input_hidden">
            <input type="hidden" name="satuan_hidden" id="satuan_hidden">

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Materi Induk (Kategori)</label>
                <select name="master_materi_id" id="select_mapel" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs md:text-sm focus:ring-indigo-500 outline-none select-wrap-text" required>
                    <option value="">-- Pilih Materi Induk --</option>
                    <?php foreach ($list_mapel as $m): ?>
                        <option value="<?php echo $m['id']; ?>" data-tipe="<?php echo $m['tipe_input']; ?>" data-satuan="<?php echo $m['satuan_default']; ?>"><?php echo htmlspecialchars($m['nama_kategori']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Detail Materi</label>
                <!-- Tambahan class select-wrap-text dan text-[11px] khusus mobile agar tidak terpotong -->
                <select name="detail_materi_id" id="select_detail" class="w-full border border-gray-300 rounded-lg p-2.5 text-[11px] md:text-sm focus:ring-indigo-500 outline-none select-wrap-text disabled:bg-gray-100" disabled required>
                    <option value="">-- Pilih Materi Induk Terlebih Dahulu --</option>
                </select>
                <p id="info_detail_materi" class="text-[11px] font-semibold text-indigo-600 mt-1.5 hidden"></p>
            </div>

            <div id="dynamic_form_area" class="bg-gray-50/80 p-4 rounded-xl border border-gray-200 hidden shadow-inner">
                <div id="form_range" class="hidden space-y-3">
                    <p class="text-sm font-bold text-gray-800 border-b border-gray-200 pb-1.5">Rentang Target:</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11px] font-bold text-gray-500 block uppercase tracking-wide mb-1">Mulai</label>
                            <input type="number" step="0.01" name="target_start" class="w-full border border-gray-300 p-2.5 rounded-lg text-center font-bold focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-500 block uppercase tracking-wide mb-1">Sampai</label>
                            <input type="number" step="0.01" name="target_end" class="w-full border border-gray-300 p-2.5 rounded-lg text-center font-bold focus:ring-indigo-500">
                        </div>
                    </div>
                </div>
                <div id="form_manual" class="hidden space-y-3">
                    <label class="text-[11px] font-bold text-gray-500 block uppercase tracking-wide mb-1">Total Volume:</label>
                    <div class="flex items-center gap-3">
                        <input type="number" step="0.01" name="target_volume" class="w-24 border border-gray-300 p-2.5 rounded-lg text-center font-bold focus:ring-indigo-500">
                        <span class="label-satuan text-sm font-bold text-gray-600"></span>
                    </div>
                </div>
                <div id="form_checklist" class="hidden">
                    <p class="text-xs font-semibold text-gray-600"><i class="fa-solid fa-check-circle text-green-500 mr-1"></i> Target ini berupa Poin Checklist berkelanjutan.</p>
                </div>
            </div>

            <div class="pt-3 flex flex-col md:flex-row-reverse gap-2">
                <button type="submit" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 md:py-2 px-6 rounded-lg shadow transition active:scale-95">Simpan Target</button>
                <button type="button" onclick="closeModal('modalTarget')" class="w-full md:w-auto bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold py-2.5 md:py-2 px-4 rounded-lg transition">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL EDIT TARGET -->
<!-- ============================================= -->
<div id="modalEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 hidden backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl md:rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
        <div class="bg-blue-600 px-5 py-4 flex justify-between items-center">
            <h3 class="text-lg font-bold text-white">Edit Target</h3>
            <button type="button" onclick="closeModal('modalEdit')" class="text-blue-100 hover:text-white">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <form id="formEdit" class="p-5 md:p-6 space-y-4 max-h-[85vh] overflow-y-auto custom-scrollbar">
            <input type="hidden" name="action" value="edit_target">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="tipe_input" id="edit_tipe_input">

            <!-- Judul (Bisa diedit manual) -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Judul Materi / Target</label>
                <!-- Perkecil teks di HP agar mudah diedit jika panjang -->
                <input type="text" name="judul_materi" id="edit_judul" class="w-full border border-gray-300 rounded-lg p-2.5 text-[12px] md:text-sm font-semibold focus:ring-blue-500 outline-none" required>
            </div>

            <!-- Area Dinamis Edit -->
            <div id="edit_dynamic_area" class="bg-gray-50/80 p-4 rounded-xl border border-gray-200 shadow-inner">
                <!-- Range -->
                <div id="edit_range" class="hidden space-y-3">
                    <p class="text-sm font-bold text-gray-800 border-b border-gray-200 pb-1.5">Edit Rentang:</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11px] font-bold text-gray-500 block uppercase tracking-wide mb-1">Mulai</label>
                            <input type="number" step="0.01" name="target_start" id="edit_start" class="w-full border border-gray-300 p-2.5 rounded-lg text-center font-bold focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-500 block uppercase tracking-wide mb-1">Sampai</label>
                            <input type="number" step="0.01" name="target_end" id="edit_end" class="w-full border border-gray-300 p-2.5 rounded-lg text-center font-bold focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                <!-- Manual -->
                <div id="edit_manual" class="hidden space-y-3">
                    <label class="text-[11px] font-bold text-gray-500 block uppercase tracking-wide mb-1">Total Volume:</label>
                    <input type="number" step="0.01" name="target_volume" id="edit_volume" class="w-32 border border-gray-300 p-2.5 rounded-lg text-center font-bold focus:ring-blue-500">
                </div>
                <!-- Checklist -->
                <div id="edit_checklist" class="hidden">
                    <p class="text-xs font-medium text-gray-500 italic">Target bertipe Checklist tidak memiliki besaran angka / nilai yang bisa diubah.</p>
                </div>
            </div>

            <div class="pt-3 flex flex-col md:flex-row-reverse gap-2">
                <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 md:py-2 px-6 rounded-lg shadow transition active:scale-95">Update Target</button>
                <button type="button" onclick="closeModal('modalEdit')" class="w-full md:w-auto bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold py-2.5 md:py-2 px-4 rounded-lg transition">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- SCRIPT ANTI OVERLAY INDEX (VERSI "SOFT") ---
    document.addEventListener("DOMContentLoaded", function() {
        const indexOverlay = document.getElementById('loading-overlay');
        if (indexOverlay) {
            indexOverlay.classList.remove('show');
            indexOverlay.style.display = ''; // Reset display agar animasi pindah halaman tetap jalan
        }
    });

    // Helper: Sembunyikan Overlay Global setelah AJAX Submit selesai
    const hideGlobalOverlay = () => {
        const indexOverlay = document.getElementById('loading-overlay');
        if (indexOverlay) {
            indexOverlay.classList.remove('show');
        }
    };

    // Helper Modal
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    // ============================================
    // LOGIKA TAMBAH TARGET
    // ============================================
    const selectMapel = document.getElementById('select_mapel');
    const selectDetail = document.getElementById('select_detail');
    const formTarget = document.getElementById('formTarget');
    const btnTambah = document.getElementById('btnTambahTarget');

    if (btnTambah) {
        btnTambah.addEventListener('click', () => {
            formTarget.reset();
            resetDynamicForm();
            openModal('modalTarget');
        });
    }

    function resetDynamicForm() {
        document.getElementById('dynamic_form_area').classList.add('hidden');
        document.getElementById('form_range').classList.add('hidden');
        document.getElementById('form_manual').classList.add('hidden');
        document.getElementById('form_checklist').classList.add('hidden');
        selectDetail.innerHTML = '<option value="">-- Pilih Materi Induk Terlebih Dahulu --</option>';
        selectDetail.disabled = true;
        document.getElementById('info_detail_materi').classList.add('hidden');
    }

    selectMapel.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const masterId = this.value;
        const tipe = option.dataset.tipe;
        const satuan = option.dataset.satuan;

        selectDetail.innerHTML = '<option value="">Memuat...</option>';
        selectDetail.disabled = true;
        if (!masterId) return;

        document.getElementById('tipe_input_hidden').value = tipe;
        document.getElementById('satuan_hidden').value = satuan;

        const formData = new FormData();
        formData.append('action', 'get_detail_option');
        formData.append('master_id', masterId);

        fetch('pages/presensi/proses_atur_probul.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                selectDetail.innerHTML = '<option value="">-- Pilih Materi Detail --</option>';
                selectDetail.disabled = false;
                data.data.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.judul_detail;
                    opt.dataset.total = d.total_isi;
                    selectDetail.appendChild(opt);
                });
            });
    });

    selectDetail.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const detailId = this.value;
        const totalRef = option.dataset.total;
        const tipe = document.getElementById('tipe_input_hidden').value;
        const satuan = document.getElementById('satuan_hidden').value;

        if (!detailId) {
            document.getElementById('dynamic_form_area').classList.add('hidden');
            return;
        }

        if (tipe !== 'CHECKLIST') {
            const info = document.getElementById('info_detail_materi');
            info.textContent = `Note: ${option.textContent} memiliki total ${totalRef} ${satuan}.`;
            info.classList.remove('hidden');
        }

        document.getElementById('dynamic_form_area').classList.remove('hidden');
        document.getElementById('form_range').classList.add('hidden');
        document.getElementById('form_manual').classList.add('hidden');
        document.getElementById('form_checklist').classList.add('hidden');

        document.querySelectorAll('.label-satuan').forEach(el => el.textContent = satuan);

        if (tipe === 'RANGE') document.getElementById('form_range').classList.remove('hidden');
        else if (tipe === 'MANUAL') document.getElementById('form_manual').classList.remove('hidden');
        else if (tipe === 'CHECKLIST') document.getElementById('form_checklist').classList.remove('hidden');
    });

    formTarget.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // Mencegah event listener global index.php

        Swal.fire({
            title: 'Menyimpan...',
            didOpen: () => Swal.showLoading()
        });

        const formData = new FormData(formTarget);
        fetch('pages/presensi/proses_atur_probul.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                hideGlobalOverlay(); // Pastikan overlay global hilang
                if (data.status === 'success') {
                    closeModal('modalTarget');
                    Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            })
            .catch(err => {
                hideGlobalOverlay();
                Swal.fire('Error', 'Sistem error', 'error');
            });
    });

    // ============================================
    // LOGIKA EDIT TARGET
    // ============================================
    const formEdit = document.getElementById('formEdit');

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-edit');
        if (btn) {
            const data = JSON.parse(btn.dataset.json);

            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_judul').value = data.judul_materi;
            document.getElementById('edit_tipe_input').value = data.tipe_input;

            // Reset Display
            document.getElementById('edit_range').classList.add('hidden');
            document.getElementById('edit_manual').classList.add('hidden');
            document.getElementById('edit_checklist').classList.add('hidden');

            if (data.tipe_input === 'RANGE') {
                document.getElementById('edit_range').classList.remove('hidden');
                document.getElementById('edit_start').value = parseFloat(data.target_start);
                document.getElementById('edit_end').value = parseFloat(data.target_end);
            } else if (data.tipe_input === 'MANUAL') {
                document.getElementById('edit_manual').classList.remove('hidden');
                document.getElementById('edit_volume').value = parseFloat(data.total_volume);
            } else {
                document.getElementById('edit_checklist').classList.remove('hidden');
            }

            openModal('modalEdit');
        }
    });

    formEdit.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // Mencegah event listener global index.php

        Swal.fire({
            title: 'Mengupdate...',
            didOpen: () => Swal.showLoading()
        });

        const formData = new FormData(formEdit);
        fetch('pages/presensi/proses_atur_probul.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                hideGlobalOverlay(); // Pastikan overlay global hilang
                if (data.status === 'success') {
                    closeModal('modalEdit');
                    Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            })
            .catch(err => {
                hideGlobalOverlay();
                Swal.fire('Error', 'Sistem error', 'error');
            });
    });

    // Hapus Logic
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-hapus');
        if (btn) {
            Swal.fire({
                title: 'Hapus Target?',
                text: `Hapus target "${btn.dataset.judul}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        didOpen: () => Swal.showLoading()
                    });
                    const formData = new FormData();
                    formData.append('action', 'hapus_target');
                    formData.append('id', btn.dataset.id);
                    fetch('pages/presensi/proses_atur_probul.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            hideGlobalOverlay();
                            if (data.status === 'success') {
                                Swal.fire({
                                        icon: 'success',
                                        title: 'Terhapus!',
                                        text: data.message,
                                        timer: 1500,
                                        showConfirmButton: false
                                    })
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Gagal', data.message, 'error');
                            }
                        });
                }
            });
        }
    });
</script>