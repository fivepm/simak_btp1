<?php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
$jadwal_id = isset($_GET['jadwal_id']) ? (int)$_GET['jadwal_id'] : 0;

if ($jadwal_id === 0) {
    echo '<div class="bg-red-100 border-red-400 text-red-700 p-4 rounded-lg">ID Jadwal tidak valid.</div>';
    return;
}

$jadwal = $conn->query("SELECT * FROM jadwal_presensi WHERE id = $jadwal_id")->fetch_assoc();
$back_url = '?page=jadwal&periode_id=' . ($jadwal['periode_id'] ?? '') . '&kelompok=' . ($jadwal['kelompok'] ?? '') . '&kelas=' . ($jadwal['kelas'] ?? '');

$peserta_presensi = [];
$sql_presensi = "SELECT rp.id, rp.status_kehadiran, rp.keterangan, p.nama_lengkap, p.nomor_hp_orang_tua, rp.kirim_wa 
                 FROM rekap_presensi rp JOIN peserta p ON rp.peserta_id = p.id 
                 WHERE rp.jadwal_id = ? ORDER BY p.nama_lengkap ASC";
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

<div id="loadingOverlay" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 hidden backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white p-8 rounded-2xl shadow-2xl text-center max-w-sm w-full transform scale-100 transition-transform duration-300">
        <div class="relative w-20 h-20 mx-auto mb-6">
            <div class="absolute inset-0 border-4 border-indigo-200 rounded-full animate-pulse"></div>
            <div class="absolute inset-0 border-t-4 border-indigo-600 rounded-full animate-spin"></div>
            <div class="absolute inset-4 bg-indigo-50 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-cloud-arrow-up text-2xl text-indigo-600"></i>
            </div>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Memproses Data...</h3>
        <p class="text-gray-500 text-sm">Mohon tunggu sebentar.</p>
    </div>
</div>

<div class="container mx-auto">
    <div class="mb-6"><a href="<?php echo $back_url; ?>" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-2 transition"><i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Jadwal</a></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- KOLOM KIRI: JURNAL -->
        <div class="lg:col-span-1">
            <div class="bg-white p-5 md:p-6 rounded-lg shadow-md border-t-4 border-indigo-500 sticky top-4">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-5 pb-4 border-b border-gray-100 gap-3">
                    <h3 class="text-lg md:text-xl font-bold text-gray-800">Jurnal Harian</h3>

                    <!-- DUA TOMBOL AKSI -->
                    <div class="flex gap-2 w-full md:w-auto">
                        <button type="button" id="btn-tambah-tambahan" class="flex-1 md:flex-none text-xs bg-yellow-500 text-white hover:bg-yellow-600 px-3 py-2.5 md:py-2 rounded-lg font-bold transition shadow flex items-center justify-center gap-1" title="Materi Tambahan (Nasehat/Tamu)">
                            <i class="fa-solid fa-star"></i> Tambahan
                        </button>
                        <button type="button" id="btn-tambah-materi" class="flex-1 md:flex-none text-xs bg-indigo-600 text-white hover:bg-indigo-700 px-3 py-2.5 md:py-2 rounded-lg font-bold transition shadow flex items-center justify-center gap-1">
                            <i class="fa-solid fa-plus"></i> Materi
                        </button>
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Pengajar*</label>
                        <input type="text" id="input-pengajar" value="<?php echo htmlspecialchars($jadwal['pengajar'] ?? ''); ?>"
                            class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm"
                            placeholder="Nama Anda...">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Materi Tersampaikan:</label>
                        <div id="list-materi-container" class="space-y-3 min-h-[50px]">
                            <div class="flex flex-col items-center justify-center py-6 text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                                <i class="fa-solid fa-circle-notch fa-spin mb-2 text-xl"></i>
                                <span class="text-xs font-medium">Memuat materi...</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <button type="button" id="btn-simpan-jurnal"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 md:py-2.5 px-6 rounded-xl md:rounded-lg shadow-lg transition transform hover:scale-[1.02] active:scale-95 w-full flex justify-center items-center gap-2">
                            <i class="fa-brands fa-whatsapp text-xl md:text-lg"></i> Simpan & Kirim WA
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: PRESENSI -->
        <div class="lg:col-span-2 bg-white p-5 md:p-6 rounded-lg shadow-md min-w-0 h-fit border-t-4 border-indigo-500 sticky top-4">
            <h3 class="text-lg md:text-xl font-bold text-gray-800 mb-4 border-b pb-3 flex justify-between items-center">
                <span>Presensi Peserta</span>
                <span class="text-xs md:text-sm font-bold text-gray-600 bg-gray-100 px-3 py-1 rounded-full border border-gray-200">Total: <?php echo count($peserta_presensi); ?> Siswa</span>
            </h3>

            <form id="form-presensi">
                <input type="hidden" name="action" value="simpan_kehadiran">
                <input type="hidden" name="jadwal_id" value="<?php echo $jadwal_id; ?>">

                <!-- BASE HIDDEN INPUTS (Hanya diload 1x agar tidak ganda saat dikirim) -->
                <div id="base-hidden-inputs">
                    <?php foreach ($peserta_presensi as $peserta): $rekap_id = $peserta['id']; ?>
                        <input type="hidden" name="nomor_hp_ortu[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['nomor_hp_orang_tua'] ?? ''); ?>">
                        <input type="hidden" name="nama_peserta[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['nama_lengkap']); ?>">
                        <input type="hidden" name="kirim_wa[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['kirim_wa'] ?? ''); ?>">
                    <?php endforeach; ?>
                </div>

                <!-- ============================================================== -->
                <!-- 1. DESKTOP VIEW (Format Tabel Klasik) -->
                <!-- ============================================================== -->
                <div id="desktop-view" class="hidden md:block overflow-x-auto overflow-y-auto max-h-[70vh] border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Nama Siswa</th>
                                <th class="py-3 px-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Status Kehadiran</th>
                                <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider w-1/3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="presensiTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($peserta_presensi as $peserta): $rekap_id = $peserta['id']; ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900 text-[13px]"><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-center gap-1.5">
                                            <?php
                                            $statuses = ['Hadir', 'Izin', 'Sakit', 'Alpa'];
                                            $colors = ['Hadir' => 'green', 'Izin' => 'blue', 'Sakit' => 'yellow', 'Alpa' => 'red'];
                                            foreach ($statuses as $status):
                                                $color = $colors[$status];
                                                $checked = ($peserta['status_kehadiran'] === $status) ? 'checked' : '';
                                                $sync_class = "sync-" . strtolower($status) . "-" . $rekap_id;
                                            ?>
                                                <label class="cursor-pointer">
                                                    <!-- PERBAIKAN: Gunakan name kehadiran_desktop agar tidak bentrok dengan mobile -->
                                                    <input type="radio" name="kehadiran_desktop[<?php echo $rekap_id; ?>]" value="<?php echo $status; ?>"
                                                        class="sr-only peer status-radio <?php echo $sync_class; ?>"
                                                        data-sync-class="<?php echo $sync_class; ?>"
                                                        data-keterangan-target=".ket-<?php echo $rekap_id; ?>"
                                                        <?php echo $checked; ?>>
                                                    <span class="px-3 py-1.5 rounded-full text-xs font-bold border transition-all duration-200 hover:shadow-md bg-<?php echo $color; ?>-50 text-<?php echo $color; ?>-700 border-<?php echo $color; ?>-200 peer-checked:bg-<?php echo $color; ?>-600 peer-checked:text-white peer-checked:border-<?php echo $color; ?>-600">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" name="keterangan_desktop[<?php echo $rekap_id; ?>]"
                                            class="ket-input ket-<?php echo $rekap_id; ?> block w-full text-xs border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 p-2 transition disabled:bg-gray-100 disabled:text-gray-400"
                                            data-target-class="ket-<?php echo $rekap_id; ?>"
                                            value="<?php echo htmlspecialchars($peserta['keterangan'] ?? ''); ?>" placeholder="Catatan...">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ============================================================== -->
                <!-- 2. MOBILE VIEW (Format Grid Card Compact) -->
                <!-- ============================================================== -->
                <div id="mobile-view" class="block md:hidden space-y-3 overflow-y-auto max-h-[70vh] pb-4 custom-scrollbar">
                    <?php foreach ($peserta_presensi as $peserta): $rekap_id = $peserta['id']; ?>
                        <div class="bg-white border border-gray-200 p-3.5 rounded-xl shadow-sm relative">
                            <!-- Nama Siswa -->
                            <div class="font-bold text-gray-900 text-[14px] mb-2.5 pb-2 border-b border-gray-100 flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                <?php echo htmlspecialchars($peserta['nama_lengkap']); ?>
                            </div>

                            <!-- Kotak Pilihan Kehadiran -->
                            <div class="text-[10px] text-gray-500 font-bold uppercase mb-1.5 tracking-wider">Status:</div>
                            <div class="grid grid-cols-4 gap-2 mb-3">
                                <?php
                                $statuses = ['Hadir', 'Izin', 'Sakit', 'Alpa'];
                                $colors = ['Hadir' => 'green', 'Izin' => 'blue', 'Sakit' => 'yellow', 'Alpa' => 'red'];
                                foreach ($statuses as $status):
                                    $color = $colors[$status];
                                    $checked = ($peserta['status_kehadiran'] === $status) ? 'checked' : '';
                                    $sync_class = "sync-" . strtolower($status) . "-" . $rekap_id;
                                ?>
                                    <label class="cursor-pointer">
                                        <!-- PERBAIKAN: Gunakan name kehadiran_mobile agar tidak bentrok dengan desktop -->
                                        <input type="radio" name="kehadiran_mobile[<?php echo $rekap_id; ?>]" value="<?php echo $status; ?>"
                                            class="sr-only peer status-radio <?php echo $sync_class; ?>"
                                            data-sync-class="<?php echo $sync_class; ?>"
                                            data-keterangan-target=".ket-<?php echo $rekap_id; ?>"
                                            <?php echo $checked; ?>>
                                        <div class="text-center py-2 rounded-lg text-[11px] font-bold border transition-all duration-200 bg-<?php echo $color; ?>-50 text-<?php echo $color; ?>-700 border-<?php echo $color; ?>-200 peer-checked:bg-<?php echo $color; ?>-600 peer-checked:text-white peer-checked:shadow-md peer-checked:border-<?php echo $color; ?>-600 active:scale-95">
                                            <?php echo $status; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <!-- Input Keterangan -->
                            <div class="text-[10px] text-gray-500 font-bold uppercase mb-1 tracking-wider">Keterangan:</div>
                            <input type="text" name="keterangan_mobile[<?php echo $rekap_id; ?>]"
                                class="ket-input ket-<?php echo $rekap_id; ?> w-full text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 p-2.5 transition disabled:bg-gray-100 disabled:text-gray-400 outline-none"
                                data-target-class="ket-<?php echo $rekap_id; ?>"
                                value="<?php echo htmlspecialchars($peserta['keterangan'] ?? ''); ?>" placeholder="Tulis alasan disini...">
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tombol Submit -->
                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end">
                    <button type="submit" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 md:py-2.5 px-8 rounded-xl md:rounded-lg shadow-lg transition transform hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-check-circle"></i> Simpan Kehadiran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH MATERI KURIKULUM -->
<div id="modalMateri" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 hidden backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl md:rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-bold text-white">Input Materi Kurikulum</h3>
            <button onclick="closeMateriModal()" class="text-white hover:text-gray-200 focus:outline-none">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <form id="form-tambah-materi" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            <input type="hidden" name="action" value="simpan_materi_detail">
            <input type="hidden" name="jadwal_id" value="<?php echo $jadwal_id; ?>">
            <input type="hidden" name="tipe_input" id="input_tipe_target">

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Pilih Kategori Materi</label>
                <select id="select_kategori" class="w-full border border-gray-300 rounded-lg p-3 md:p-2.5 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="">-- Memuat Kategori... --</option>
                </select>
            </div>

            <div id="area_target_selection" class="hidden space-y-4">
                <div id="block_pilih_target" class="hidden">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Pilih Materi / Surat / Kitab</label>
                    <select id="select_target_range" name="target_id" class="w-full border border-gray-300 rounded-lg p-3 md:p-2.5 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">-- Pilih Materi --</option>
                    </select>
                    <p id="info_target_limit" class="text-[11px] font-semibold text-indigo-600 mt-1.5 hidden"></p>
                </div>

                <div id="block_input_range" class="hidden bg-gray-50 p-4 rounded-xl border border-gray-200 shadow-inner">
                    <p class="text-sm font-bold text-gray-800 pb-1">Capaian Hari Ini:</p>
                    <p class="text-[11px] text-gray-500 border-b border-gray-200 pb-2 mb-3 leading-tight" id="hint_halaman">Note: </p>
                    <!-- Hidden actual form values (always submitted) -->
                    <input type="hidden" name="capaian_start" id="input_start">
                    <input type="hidden" name="capaian_end" id="input_end">

                    <div id="form_range_fields" class="hidden">

                        <!-- MODE BIASA (satuan selain Halaman) -->
                        <div id="range_mode_biasa" class="grid grid-cols-2 gap-4 hidden">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase" id="label_start">Dari</label>
                                <input type="number" step="0.01" id="input_start_biasa" class="w-full border border-gray-300 rounded-lg p-2.5 text-center font-bold focus:ring-indigo-500" placeholder="0">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase" id="label_end">Sampai</label>
                                <input type="number" step="0.01" id="input_end_biasa" class="w-full border border-gray-300 rounded-lg p-2.5 text-center font-bold focus:ring-indigo-500" placeholder="0">
                            </div>
                        </div>

                        <!-- MODE HALAMAN + BARIS (satuan = Halaman) -->
                        <div id="range_mode_halaman" class="space-y-3 hidden">
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Dari -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase">Dari</label>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <div>
                                            <span class="block text-[10px] font-semibold text-gray-400 mb-0.5 text-center">Halaman</span>
                                            <input type="number" id="input_start_halaman" min="1" step="1"
                                                class="w-full border border-gray-300 rounded-lg p-2 text-center font-bold focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
                                                placeholder="1">
                                        </div>
                                        <div>
                                            <span class="block text-[10px] font-semibold text-gray-400 mb-0.5 text-center">Baris</span>
                                            <input type="number" id="input_start_baris" min="1" step="1"
                                                class="w-full border border-gray-300 rounded-lg p-2 text-center font-bold focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
                                                placeholder="1">
                                        </div>
                                    </div>
                                </div>
                                <!-- Sampai -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase">Sampai</label>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <div>
                                            <span class="block text-[10px] font-semibold text-gray-400 mb-0.5 text-center">Halaman</span>
                                            <input type="number" id="input_end_halaman" min="1" step="1"
                                                class="w-full border border-gray-300 rounded-lg p-2 text-center font-bold focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
                                                placeholder="1">
                                        </div>
                                        <div>
                                            <span class="block text-[10px] font-semibold text-gray-400 mb-0.5 text-center">Baris</span>
                                            <input type="number" id="input_end_baris" min="1" step="1"
                                                class="w-full border border-gray-300 rounded-lg p-2 text-center font-bold focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
                                                placeholder="1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Preview Capaian -->
                            <div id="preview_capaian_halaman" class="hidden bg-indigo-50 border border-indigo-200 rounded-lg p-2.5 flex items-center justify-center gap-2">
                                <i class="fa-solid fa-calculator text-indigo-400 text-xs"></i>
                                <span class="text-[11px] font-bold text-indigo-600">Jumlah Capaian:</span>
                                <span id="nilai_capaian_halaman" class="text-sm font-bold text-indigo-800">-</span>
                                <span class="text-[11px] font-bold text-indigo-600">Halaman</span>
                            </div>
                        </div>

                    </div>
                    <div id="form_manual_fields" class="hidden">
                        <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase">Volume / Jumlah</label>
                        <div class="flex items-center gap-3">
                            <input type="number" step="0.01" name="volume_manual" class="w-24 border border-gray-300 rounded-lg p-2.5 text-center font-bold focus:ring-indigo-500" placeholder="0">
                            <span id="label_satuan_manual" class="text-sm font-bold text-gray-600">Satuan</span>
                        </div>
                    </div>
                </div>

                <div id="block_checklist_list" class="hidden">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Checklist Materi Tersampaikan:</label>
                    <div id="checklist_container" class="max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-2.5 bg-gray-50 space-y-2"></div>
                </div>

                <div>
                    <label id="label_catatan" class="block text-sm font-bold text-gray-700 mb-1">Catatan Khusus (Opsional)</label>
                    <input type="text" id="input_catatan" name="catatan_tambahan" class="w-full border border-gray-300 rounded-lg p-3 md:p-2.5 text-sm focus:ring-indigo-500" placeholder="Contoh: Perlu diulang...">
                </div>

                <div class="pt-3">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 md:py-2.5 rounded-xl md:rounded-lg shadow-md transition active:scale-95">
                        Simpan Materi Ini
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fix Animasi Index
        const indexOverlay = document.getElementById('loading-overlay');
        if (indexOverlay) {
            indexOverlay.classList.remove('show');
            indexOverlay.style.display = '';
        }
        const hideGlobalOverlay = () => {
            if (indexOverlay) indexOverlay.classList.remove('show');
        };

        const btnKembali = document.querySelector('a[href^="?page="]');
        if (btnKembali && indexOverlay) {
            btnKembali.addEventListener('click', function(e) {
                e.preventDefault();
                indexOverlay.classList.add('show');
                const href = this.getAttribute('href');
                setTimeout(() => {
                    window.location.href = href;
                }, 300);
            });
        }

        const loadingOverlay = document.getElementById('loadingOverlay');
        const showLoading = () => loadingOverlay.classList.remove('hidden');
        const hideLoading = () => loadingOverlay.classList.add('hidden');

        loadJurnalHariIni();

        // ==========================================
        // 1. TAMBAH MATERI TAMBAHAN (3 KOLOM)
        // ==========================================
        document.getElementById('btn-tambah-tambahan').addEventListener('click', function() {
            Swal.fire({
                title: 'Input Materi Tambahan',
                html: `
                    <div class="text-left space-y-3">
                        <div>
                            <label class="block text-sm font-bold text-gray-700">Materi Apa? <span class="text-red-500">*</span></label>
                            <input id="swal-judul" class="w-full border border-gray-300 rounded-lg p-2.5 mt-1 focus:ring-2 outline-none" placeholder="Contoh: Nasehat Kejujuran">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700">Pemateri <span class="text-red-500">*</span></label>
                            <input id="swal-pemateri" class="w-full border border-gray-300 rounded-lg p-2.5 mt-1 focus:ring-2 outline-none" placeholder="Contoh: Bpk. H. Fulan">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700">Keterangan (Opsional)</label>
                            <textarea id="swal-ket" class="w-full border border-gray-300 rounded-lg p-2.5 mt-1 focus:ring-2 outline-none" rows="2" placeholder="Catatan tambahan..."></textarea>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#eab308',
                preConfirm: () => {
                    const judul = document.getElementById('swal-judul').value;
                    const pemateri = document.getElementById('swal-pemateri').value;
                    const ket = document.getElementById('swal-ket').value;

                    if (!judul || !pemateri) {
                        Swal.showValidationMessage('Materi dan Pemateri wajib diisi!');
                        return false;
                    }
                    return {
                        judul: judul,
                        pemateri: pemateri,
                        ket: ket
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    const data = result.value;
                    const formData = new FormData();
                    formData.append('action', 'simpan_materi_tambahan');
                    formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');
                    formData.append('judul_materi', data.judul);
                    formData.append('pemateri', data.pemateri);
                    formData.append('keterangan', data.ket);

                    fetch('pages/ajax_input_presensi.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            hideLoading();
                            hideGlobalOverlay();
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Tersimpan!',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                                loadJurnalHariIni();
                            } else {
                                Swal.fire('Gagal', data.message, 'error');
                            }
                        })
                        .catch(err => {
                            hideLoading();
                            hideGlobalOverlay();
                            Swal.fire('Error', 'Gagal simpan tambahan', 'error');
                        });
                }
            });
        });

        // ==========================================
        // 2. MODAL & TARGET LOGIC
        // ==========================================
        const modalMateri = document.getElementById('modalMateri');
        const selectKategori = document.getElementById('select_kategori');
        const areaTarget = document.getElementById('area_target_selection');
        const inputTipeTarget = document.getElementById('input_tipe_target');
        const blockPilihTarget = document.getElementById('block_pilih_target');
        const selectTargetRange = document.getElementById('select_target_range');
        const blockInputRange = document.getElementById('block_input_range');
        const infoTargetLimit = document.getElementById('info_target_limit');
        const formRangeFields = document.getElementById('form_range_fields');
        const formManualFields = document.getElementById('form_manual_fields');
        const blockChecklist = document.getElementById('block_checklist_list');
        const checklistContainer = document.getElementById('checklist_container');
        let currentTargets = [];

        document.getElementById('btn-tambah-materi').addEventListener('click', function() {
            modalMateri.classList.remove('hidden');
            resetModal();
            fetchKategori();
        });

        window.closeMateriModal = function() {
            modalMateri.classList.add('hidden');
        }

        function resetCatatanLabel() {
            const labelCatatan = document.getElementById('label_catatan');
            const inputCatatan = document.getElementById('input_catatan');
            if (labelCatatan) labelCatatan.textContent = 'Catatan Khusus (Opsional)';
            if (inputCatatan) {
                inputCatatan.required = false;
                inputCatatan.placeholder = 'Contoh: Perlu diulang...';
            }
        }

        function resetModal() {
            document.getElementById('form-tambah-materi').reset();
            areaTarget.classList.add('hidden');
            blockPilihTarget.classList.add('hidden');
            blockInputRange.classList.add('hidden');
            blockChecklist.classList.add('hidden');
            // Reset mode halaman
            document.getElementById('range_mode_halaman').classList.add('hidden');
            document.getElementById('range_mode_biasa').classList.add('hidden');
            document.getElementById('preview_capaian_halaman').classList.add('hidden');
            document.getElementById('input_start').value = '';
            document.getElementById('input_end').value   = '';
            selectKategori.innerHTML = '<option value="">-- Memuat Kategori... --</option>';
            // Reset label catatan ke default
            resetCatatanLabel();
        }

        function fetchKategori() {
            const formData = new FormData();
            formData.append('action', 'get_kategori_list');
            formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');

            fetch('pages/ajax_input_presensi.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    selectKategori.innerHTML = '<option value="">-- Pilih Kategori --</option>';
                    if (data.status === 'success') {
                        data.data.forEach(kat => {
                            const opt = document.createElement('option');
                            opt.value = kat.kategori;
                            opt.dataset.tipe = kat.tipe_input;
                            opt.textContent = kat.kategori;
                            selectKategori.appendChild(opt);
                        });
                    }
                });
        }

        selectKategori.addEventListener('change', function() {
            const kategori = this.value;
            if (!kategori) {
                areaTarget.classList.add('hidden');
                blockPilihTarget.classList.add('hidden');
                blockInputRange.classList.add('hidden');
                blockChecklist.classList.add('hidden');
                resetCatatanLabel();
                return;
            }
            const option = this.options[this.selectedIndex];
            const tipe = option.dataset.tipe;

            inputTipeTarget.value = tipe;
            areaTarget.classList.remove('hidden');
            blockPilihTarget.classList.add('hidden');
            blockInputRange.classList.add('hidden');
            blockChecklist.classList.add('hidden');

            // Reset pilihan target dan label catatan saat kategori diganti
            selectTargetRange.innerHTML = '<option value="">-- Pilih Materi / Target --</option>';
            document.getElementById('input_start').value = '';
            document.getElementById('input_end').value = '';
            resetCatatanLabel();

            fetchTargetsByKategori(kategori, tipe);
        });

        function fetchTargetsByKategori(kategori, tipe) {
            const formData = new FormData();
            formData.append('action', 'get_target_by_kategori');
            formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');
            formData.append('kategori', kategori);

            if (tipe === 'CHECKLIST') {
                blockChecklist.classList.remove('hidden');
                checklistContainer.innerHTML = '<div class="text-center py-2"><i class="fa-solid fa-spinner fa-spin text-gray-400"></i> Memuat...</div>';
            } else {
                blockPilihTarget.classList.remove('hidden');
                selectTargetRange.innerHTML = '<option>Memuat...</option>';
            }

            fetch('pages/ajax_input_presensi.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        currentTargets = data.data;
                        renderTargetUI(tipe, data.data);
                    }
                });
        }

        function renderTargetUI(tipe, targets) {
            if (tipe === 'CHECKLIST') {
                checklistContainer.innerHTML = '';
                if (targets.length === 0) {
                    checklistContainer.innerHTML = '<p class="text-red-500 text-xs font-medium text-center py-2">Tidak ada target.</p>';
                    return;
                }
                targets.forEach(t => {
                    const div = document.createElement('div');
                    div.className = 'flex items-start gap-3 p-2 hover:bg-white rounded-lg border border-transparent hover:border-gray-200 transition';
                    const disabled = t.is_filled_today ? 'disabled' : '';
                    const labelStyle = t.is_filled_today ? 'text-gray-400 line-through' : 'text-gray-700 font-medium';
                    const checked = t.is_filled_today ? 'checked' : '';
                    div.innerHTML = `
                        <input type="checkbox" name="target_id[]" value="${t.id}" id="chk_${t.id}" class="mt-1 w-4 h-4 text-indigo-600 rounded border-gray-300" ${disabled} ${checked}>
                        <label for="chk_${t.id}" class="text-sm ${labelStyle} cursor-pointer w-full">
                            ${t.judul_materi} 
                            ${t.is_filled_today ? '<span class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded ml-1 font-bold">(Sudah)</span>' : ''}
                        </label>
                    `;
                    checklistContainer.appendChild(div);
                });
            } else {
                infoTargetLimit.classList.add('hidden');
                selectTargetRange.innerHTML = '<option value="">-- Pilih Materi / Target --</option>';
                targets.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.judul_materi;
                    selectTargetRange.appendChild(opt);
                });
            }
        }

        selectTargetRange.addEventListener('change', function() {
            const targetId = this.value;
            if (!targetId) {
                blockInputRange.classList.add('hidden');
                return;
            }
            const target = currentTargets.find(t => t.id == targetId);
            if (!target) return;

            blockInputRange.classList.remove('hidden');
            const tipe = inputTipeTarget.value;

            if (tipe === 'RANGE') {
                // Reset label catatan ke default saat RANGE
                resetCatatanLabel();
                formRangeFields.classList.remove('hidden');
                formManualFields.classList.add('hidden');
                let satuan = target.satuan;
                if (satuan === 'Halaman') {
                    document.getElementById('hint_halaman').textContent = `Note: Masukkan halaman dan baris awal serta akhir yang disampaikan. Baris mulai dihitung sebagai bagian capaian. Contoh: Hal. 1 Baris 1 s/d Hal. 3 Baris 2 = 2.2 Halaman`;
                    document.getElementById('hint_halaman').classList.remove('hidden');
                    document.getElementById('range_mode_biasa').classList.add('hidden');
                    document.getElementById('range_mode_halaman').classList.remove('hidden');
                    // Reset preview
                    document.getElementById('preview_capaian_halaman').classList.add('hidden');
                    document.getElementById('input_start_halaman').value = '';
                    document.getElementById('input_start_baris').value = '';
                    document.getElementById('input_end_halaman').value = '';
                    document.getElementById('input_end_baris').value = '';
                } else {
                    document.getElementById('hint_halaman').classList.add('hidden');
                    document.getElementById('range_mode_halaman').classList.add('hidden');
                    document.getElementById('range_mode_biasa').classList.remove('hidden');
                    document.getElementById('label_start').textContent = `Dari ${target.satuan}`;
                    document.getElementById('label_end').textContent = `Sampai ${target.satuan}`;
                    document.getElementById('input_start_biasa').value = '';
                    document.getElementById('input_end_biasa').value = '';
                }

                const tStart = parseFloat(target.target_start);
                const tEnd = parseFloat(target.target_end);

                infoTargetLimit.classList.remove('hidden');
                infoTargetLimit.textContent = `Target Probul: ${target.satuan} ${tStart} - ${tEnd}`;
                document.getElementById('input_start').placeholder = tStart;
                document.getElementById('input_end').placeholder = tStart;
            } else {
                // Tipe MANUAL: sembunyikan seluruh block capaian, auto-set nilai = 1
                blockInputRange.classList.add('hidden');
                formRangeFields.classList.add('hidden');
                formManualFields.classList.add('hidden');
                document.getElementById('input_start').value = 0;
                document.getElementById('input_end').value = 1;
                // Set volume_manual ke 1 secara otomatis
                const volManual = document.querySelector('[name="volume_manual"]');
                if (volManual) volManual.value = 1;
                infoTargetLimit.classList.add('hidden');
                document.getElementById('hint_halaman').classList.add('hidden');
                // Ubah label catatan menjadi wajib
                const labelCatatan = document.getElementById('label_catatan');
                const inputCatatan = document.getElementById('input_catatan');
                if (labelCatatan) labelCatatan.innerHTML = 'Topik/Materi yang disampaikan <span class="text-red-500">*</span>';
                if (inputCatatan) {
                    inputCatatan.required = true;
                    inputCatatan.placeholder = 'Tulis topik atau materi yang disampaikan...';
                }
            }
        });

        // ==========================================
        // EVENT LISTENERS: INPUT HALAMAN + BARIS
        // ==========================================
        function hitungCapaianHalaman() {
            const hStart = parseInt(document.getElementById('input_start_halaman').value) || 0;
            const bStart = parseInt(document.getElementById('input_start_baris').value) || 0;
            const hEnd   = parseInt(document.getElementById('input_end_halaman').value)   || 0;
            const bEnd   = parseInt(document.getElementById('input_end_baris').value)     || 0;

            if (hStart > 0 && bStart > 0 && hEnd > 0 && bEnd > 0) {
                // Formula: capaian = (hEnd + bEnd*0.1) - (hStart + bStart*0.1) + bStart*0.1
                // Disederhanakan: = hEnd - hStart + bEnd*0.1
                // capaian_start dikirim sebagai: hStart + (bStart-1)*0.1 agar backend cukup hitung end-start
                const decimalStart = hStart + (bStart - 1) * 0.1;
                const decimalEnd   = hEnd + bEnd * 0.1;
                const jumlahCapaian = Math.round((decimalEnd - decimalStart) * 10) / 10;

                document.getElementById('input_start').value = decimalStart.toFixed(1);
                document.getElementById('input_end').value   = decimalEnd.toFixed(1);

                const preview = document.getElementById('preview_capaian_halaman');
                if (jumlahCapaian > 0) {
                    document.getElementById('nilai_capaian_halaman').textContent = jumlahCapaian.toFixed(1);
                    preview.classList.remove('hidden');
                } else {
                    preview.classList.add('hidden');
                }
            } else {
                document.getElementById('input_start').value = '';
                document.getElementById('input_end').value   = '';
                document.getElementById('preview_capaian_halaman').classList.add('hidden');
            }
        }

        ['input_start_halaman', 'input_start_baris', 'input_end_halaman', 'input_end_baris'].forEach(id => {
            document.getElementById(id).addEventListener('input', hitungCapaianHalaman);
        });

        // Sync mode biasa → hidden inputs
        ['input_start_biasa', 'input_end_biasa'].forEach(id => {
            document.getElementById(id).addEventListener('input', function() {
                if (id === 'input_start_biasa') {
                    document.getElementById('input_start').value = this.value;
                } else {
                    document.getElementById('input_end').value = this.value;
                }
            });
        });

        document.getElementById('form-tambah-materi').addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            const tipe = inputTipeTarget.value;
            if (tipe === 'CHECKLIST') {
                const checked = document.querySelectorAll('input[name="target_id[]"]:checked');
                let newChecked = 0;
                checked.forEach(c => {
                    if (!c.disabled) newChecked++;
                });
                if (newChecked === 0) {
                    Swal.fire('Peringatan', 'Pilih minimal satu poin materi.', 'warning');
                    return;
                }
            } else {
                if (!selectTargetRange.value) {
                    Swal.fire('Peringatan', 'Pilih materi target terlebih dahulu.', 'warning');
                    return;
                }
                if (tipe === 'RANGE') {
                    // Validasi mode halaman+baris
                    const isHalamanMode = !document.getElementById('range_mode_halaman').classList.contains('hidden');
                    if (isHalamanMode) {
                        const hStart = parseInt(document.getElementById('input_start_halaman').value) || 0;
                        const bStart = parseInt(document.getElementById('input_start_baris').value) || 0;
                        const hEnd   = parseInt(document.getElementById('input_end_halaman').value) || 0;
                        const bEnd   = parseInt(document.getElementById('input_end_baris').value) || 0;
                        if (!hStart || !bStart || !hEnd || !bEnd) {
                            Swal.fire('Peringatan', 'Lengkapi halaman dan baris awal serta akhir.', 'warning');
                            return;
                        }
                        if (!document.getElementById('input_start').value || !document.getElementById('input_end').value) {
                            Swal.fire('Peringatan', 'Capaian tidak valid, periksa kembali input.', 'warning');
                            return;
                        }
                    } else {
                        // Validasi mode biasa
                        const vStart = document.getElementById('input_start_biasa').value;
                        const vEnd   = document.getElementById('input_end_biasa').value;
                        if (vStart === '' || vEnd === '') {
                            Swal.fire('Peringatan', 'Isi nilai awal dan akhir capaian.', 'warning');
                            return;
                        }
                    }
                }
                // Untuk tipe MANUAL: capaian sudah otomatis diisi nilai 1, skip validasi range
            }

            showLoading();
            const formData = new FormData(this);

            fetch('pages/ajax_input_presensi.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    hideLoading();
                    hideGlobalOverlay();
                    if (data.status === 'success') {
                        closeMateriModal();
                        loadJurnalHariIni();
                        Swal.fire({
                            icon: 'success',
                            title: 'Tersimpan',
                            text: 'Materi berhasil ditambahkan',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                })
                .catch(err => {
                    hideLoading();
                    hideGlobalOverlay();
                    console.error(err);
                    Swal.fire('Error', 'Gagal menghubungi server', 'error');
                });
        });

        function loadJurnalHariIni() {
            const formData = new FormData();
            formData.append('action', 'load_jurnal_hari_ini');
            formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');

            fetch('pages/ajax_input_presensi.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') renderMateriList(data.data);
                });
        }

        function renderMateriList(items) {
            const container = document.getElementById('list-materi-container');
            container.innerHTML = '';
            if (items.length === 0) {
                container.innerHTML = '<div class="text-center py-6 border-2 border-dashed border-gray-200 rounded-lg text-gray-400 text-sm font-medium">Belum ada materi yang diinput hari ini.</div>';
                return;
            }
            items.forEach(item => {
                let contentHTML = '';
                let borderClass = 'border-indigo-500';
                let badgeClass = 'bg-indigo-50 text-indigo-700 border-indigo-200';
                let hapusAction = 'hapusMateri';

                if (item.is_tambahan) {
                    borderClass = 'border-yellow-500';
                    badgeClass = 'bg-yellow-50 text-yellow-700 border-yellow-200';
                    hapusAction = 'hapusTambahan';
                    contentHTML = `
                        <div class="mt-1.5 flex flex-col gap-1 text-sm text-gray-700">
                            <span class="font-bold text-gray-900 text-[15px]">${item.judul_materi}</span>
                            <span class="text-xs font-semibold text-gray-500 bg-white inline-block w-fit px-2 py-0.5 rounded border border-gray-100 shadow-sm"><i class="fa-solid fa-user-tie text-yellow-500 mr-1"></i> ${item.teks_capaian}</span>
                            ${item.catatan_tambahan ? `<span class="text-xs italic text-gray-600 bg-yellow-50 border border-yellow-100 p-1.5 rounded mt-1">"${item.catatan_tambahan}"</span>` : ''}
                        </div>`;
                } else {
                    contentHTML = `
                        <h4 class="font-bold text-gray-900 text-[15px] mt-1.5">${item.judul_materi}</h4>
                        <div class="mt-1.5 flex items-center gap-2 text-sm text-gray-600 bg-white w-fit px-2.5 py-1 rounded-lg border border-gray-100 shadow-sm">
                            <i class="fa-solid fa-check-circle text-green-500"></i>
                            <span class="font-bold">${item.teks_capaian}</span>
                        </div>
                        ${item.catatan_tambahan ? `<p class="text-xs text-gray-600 mt-2 italic bg-indigo-50/50 p-1.5 rounded border border-indigo-100">"${item.catatan_tambahan}"</p>` : ''}`;
                }

                const el = document.createElement('div');
                el.className = `bg-gray-50/80 border-l-4 ${borderClass} p-3.5 rounded-r-xl shadow-sm relative group hover:bg-gray-50 hover:shadow transition-all`;
                el.innerHTML = `
                    <div class="pr-8">
                        <div class="flex justify-between items-start">
                            <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded border ${badgeClass}">${item.kategori}</span>
                        </div>
                        ${contentHTML}
                    </div>
                    <button class="absolute top-2 right-2 text-gray-300 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition btn-hapus-item" data-action="${hapusAction}" data-id="${item.id}" title="Hapus Materi">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                `;
                container.appendChild(el);
            });

            document.querySelectorAll('.btn-hapus-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.dataset.action === 'hapusTambahan') hapusMateriTambahan(this.dataset.id);
                    else hapusMateri(this.dataset.id);
                });
            });
        }

        function hapusMateri(id) {
            Swal.fire({
                title: 'Hapus Materi?',
                text: "Data ini akan dihapus dari Jurnal.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    const formData = new FormData();
                    formData.append('action', 'hapus_materi_detail');
                    formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');
                    formData.append('jurnal_materi_id', id);
                    fetch('pages/ajax_input_presensi.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            hideLoading();
                            hideGlobalOverlay();
                            if (data.status === 'success') {
                                loadJurnalHariIni();
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Terhapus!',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            } else Swal.fire('Gagal', data.message, 'error');
                        });
                }
            });
        }

        function hapusMateriTambahan(id) {
            Swal.fire({
                title: 'Hapus Materi Tambahan?',
                text: "Data ini akan dihapus permanen.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    const formData = new FormData();
                    formData.append('action', 'hapus_materi_tambahan');
                    formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');
                    formData.append('id', id);
                    fetch('pages/ajax_input_presensi.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            hideLoading();
                            hideGlobalOverlay();
                            if (data.status === 'success') {
                                loadJurnalHariIni();
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Terhapus!',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            } else Swal.fire('Gagal', data.message, 'error');
                        });
                }
            });
        }

        document.getElementById('btn-simpan-jurnal').addEventListener('click', function() {
            const pengajar = document.getElementById('input-pengajar').value;
            if (!pengajar.trim()) {
                Swal.fire('Peringatan', 'Nama Pengajar wajib diisi!', 'warning');
                return;
            }
            const listContainer = document.getElementById('list-materi-container');
            if (listContainer.innerText.includes('Belum ada materi')) {
                Swal.fire({
                    title: 'Materi Kosong?',
                    text: "Anda belum memasukkan materi ajar. Tetap simpan?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Simpan',
                    cancelButtonText: 'Batal'
                }).then((res) => {
                    if (res.isConfirmed) submitJurnalHeader(pengajar);
                });
            } else {
                submitJurnalHeader(pengajar);
            }
        });

        function submitJurnalHeader(pengajar) {
            showLoading();
            const formData = new FormData();
            formData.append('action', 'simpan_jurnal');
            formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');
            formData.append('pengajar', pengajar);
            fetch('pages/ajax_input_presensi.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    hideLoading();
                    hideGlobalOverlay();
                    if (data.status === 'success') Swal.fire({
                        title: 'Terkirim!',
                        text: data.message,
                        icon: 'success',
                        timer: 2500,
                        showConfirmButton: false
                    });
                    else Swal.fire('Gagal', data.message, 'error');
                })
                .catch(err => {
                    hideLoading();
                    hideGlobalOverlay();
                    Swal.fire('Error', 'Kesalahan koneksi', 'error');
                });
        }

        // ==========================================
        // SINKRONISASI JAVASCRIPT (DESKTOP <-> MOBILE)
        // ==========================================

        // 1. Sync Radio Buttons
        document.querySelectorAll('.status-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                const syncClass = this.dataset.syncClass;
                // Centang radio lain (di tampilan sebelahnya) yang punya class sync yang sama
                document.querySelectorAll('.' + syncClass).forEach(el => el.checked = true);
                updateKeterangan(this);
            });
            // Jalankan sekali saat load untuk set state awal Keterangan
            if (radio.checked) updateKeterangan(radio);
        });

        // 2. Sync Keterangan Input
        document.querySelectorAll('.ket-input').forEach(input => {
            input.addEventListener('input', function() {
                const targetClass = this.dataset.targetClass;
                const val = this.value;
                // Samakan teks input di tampilan sebelahnya
                document.querySelectorAll('.' + targetClass).forEach(other => {
                    if (other !== this) other.value = val;
                });
            });
        });

        // Logika Wajib Isi / Readonly Keterangan
        function updateKeterangan(radio) {
            const targets = document.querySelectorAll(radio.dataset.keteranganTarget);
            const status = radio.value;
            targets.forEach(keteranganInput => {
                keteranganInput.classList.remove('bg-gray-100', 'text-gray-500');
                if (status === 'Hadir' || status === 'Alpa') {
                    keteranganInput.value = (status === 'Hadir') ? 'Hadir' : 'Tanpa Keterangan';
                    keteranganInput.readOnly = true;
                    keteranganInput.required = false;
                    keteranganInput.classList.add('bg-gray-100', 'text-gray-500');
                    keteranganInput.placeholder = '';
                } else {
                    keteranganInput.readOnly = false;
                    keteranganInput.required = true;
                    keteranganInput.placeholder = 'Wajib diisi (Alasan)';
                    if (keteranganInput.value === 'Hadir' || keteranganInput.value === 'Tanpa Keterangan') {
                        keteranganInput.value = '';
                    }
                }
            });
        }

        // ==========================================
        // SUBMIT FORM PRESENSI
        // ==========================================
        const formPresensi = document.getElementById('form-presensi');
        if (formPresensi) {
            formPresensi.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();

                // Cegah data array ganda: Matikan input dari container HTML yang sedang tidak terlihat
                const isMobile = window.innerWidth < 768;
                const desktopContainer = document.getElementById('desktop-view');
                const mobileContainer = document.getElementById('mobile-view');

                if (isMobile) {
                    desktopContainer.querySelectorAll('input').forEach(el => el.disabled = true);
                } else {
                    mobileContainer.querySelectorAll('input').forEach(el => el.disabled = true);
                }

                // Cek validasi pada form yang aktif
                if (!formPresensi.checkValidity()) {
                    formPresensi.reportValidity();
                    // Nyalakan kembali inputnya agar user bisa mengedit
                    desktopContainer.querySelectorAll('input').forEach(el => el.disabled = false);
                    mobileContainer.querySelectorAll('input').forEach(el => el.disabled = false);
                    return;
                }

                showLoading();
                const rawFormData = new FormData(formPresensi);
                const formData = new FormData();

                // PERBAIKAN: Bersihkan akhiran '_desktop' / '_mobile' sebelum dilempar ke backend PHP
                for (let [key, value] of rawFormData.entries()) {
                    key = key.replace('_desktop[', '[').replace('_mobile[', '[');
                    formData.append(key, value);
                }

                // Nyalakan kembali inputnya sebelum fetch (best practice)
                desktopContainer.querySelectorAll('input').forEach(el => el.disabled = false);
                mobileContainer.querySelectorAll('input').forEach(el => el.disabled = false);

                fetch('pages/ajax_input_presensi.php', {
                        method: 'POST',
                        body: formData // Gunakan formData yang sudah dimodifikasi
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        hideGlobalOverlay();
                        if (data.status === 'success') Swal.fire({
                            title: 'Tersimpan!',
                            text: data.message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        else Swal.fire({
                            title: 'Gagal!',
                            text: data.message,
                            icon: 'error'
                        });
                    })
                    .catch(error => {
                        hideLoading();
                        hideGlobalOverlay();
                        Swal.fire('Error', 'Terjadi kesalahan koneksi.', 'error');
                    });
            });
        }
    });
</script>