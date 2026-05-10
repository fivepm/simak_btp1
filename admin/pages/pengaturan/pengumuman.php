<?php
// ===================================================================
// HANYA PENGAMBILAN DATA UNTUK TAMPILAN (GET)
// Semua aksi POST ditangani oleh pages/ajax_pengumuman.php
// ===================================================================
if (!isset($conn)) die("Koneksi database gagal.");

$grup_pengurus    = [];
$grup_kelas       = [];
$individu_kontak  = [];
$templates        = [];

$result_grup = $conn->query("SELECT group_id, nama_grup, kelas FROM grup_whatsapp ORDER BY nama_grup ASC");
if ($result_grup) {
    while ($row = $result_grup->fetch_assoc()) {
        if ($row['kelas'] == "semua" || $row['kelas'] == '') {
            $grup_pengurus[] = $row;
        } else {
            $grup_kelas[] = $row;
        }
    }
}

$sql_individu_unik = "
    SELECT MIN(nama) as nama, nomor_wa
    FROM (
        SELECT nama COLLATE utf8mb4_unicode_ci as nama, nomor_wa COLLATE utf8mb4_unicode_ci as nomor_wa FROM guru WHERE nomor_wa IS NOT NULL AND nomor_wa != ''
        UNION ALL
        SELECT nama COLLATE utf8mb4_unicode_ci as nama, nomor_wa COLLATE utf8mb4_unicode_ci as nomor_wa FROM penasehat WHERE nomor_wa IS NOT NULL AND nomor_wa != ''
    ) as semua_kontak
    GROUP BY nomor_wa
    ORDER BY nama ASC
";
$result_individu = $conn->query($sql_individu_unik);
if ($result_individu) {
    while ($row = $result_individu->fetch_assoc()) $individu_kontak[] = $row;
}

$result_templates = $conn->query("SELECT id, judul_template, isi_template FROM pengumuman_template ORDER BY judul_template ASC");
if ($result_templates) {
    while ($row = $result_templates->fetch_assoc()) $templates[] = $row;
}
?>

<style>
    /* ===== PROGRESS BAR FILL ===== */
    #progress-bar-fill {
        transition: width 0.4s ease-in-out;
    }

    /* ===== LOG ITEM ANIMATION ===== */
    @keyframes slideInLog {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .log-item {
        animation: slideInLog 0.25s ease forwards;
    }

    /* ===== SUCCESS CHECKMARK ===== */
    @keyframes scaleIn {
        0%   { transform: scale(0) rotate(-15deg); opacity: 0; }
        70%  { transform: scale(1.15) rotate(3deg); }
        100% { transform: scale(1) rotate(0deg); opacity: 1; }
    }
    .anim-scale-in { animation: scaleIn 0.5s ease forwards; }

    /* ===== PULSE RING ===== */
    @keyframes ping-slow {
        0%   { transform: scale(1); opacity: 0.6; }
        100% { transform: scale(1.7); opacity: 0; }
    }
    .ping-slow { animation: ping-slow 1.4s ease-out infinite; }

    /* ===== SENDING DOTS ===== */
    @keyframes dot-bounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
        40%            { transform: translateY(-5px); opacity: 1; }
    }
    .dot-1 { animation: dot-bounce 1.2s infinite 0s; }
    .dot-2 { animation: dot-bounce 1.2s infinite 0.2s; }
    .dot-3 { animation: dot-bounce 1.2s infinite 0.4s; }
</style>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">

    <!-- ===== KARTU UTAMA: KIRIM PENGUMUMAN ===== -->
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <h1 class="text-3xl font-bold text-gray-800 mb-4 border-b pb-3">Kirim Pengumuman via WhatsApp</h1>

        <form id="formPengumuman">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Kolom Kiri: Tulis Pesan -->
                <div class="lg:col-span-2">
                    <h2 class="text-xl font-semibold text-gray-700 mb-2">1. Tulis Pesan Anda</h2>
                    <textarea id="pesan-textarea" name="pesan" rows="12"
                        class="w-full border border-gray-300 p-2 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500"
                        placeholder="Ketik pengumuman di sini..." required></textarea>
                </div>

                <!-- Kolom Kanan: Pilih Penerima -->
                <div class="lg:col-span-1">
                    <h2 class="text-xl font-semibold text-gray-700 mb-2">2. Pilih Penerima</h2>

                    <!-- Dropdown Checklist -->
                    <div class="relative" id="penerima-dropdown-container">
                        <button type="button" id="penerima-dropdown-button"
                            class="w-full bg-white border border-gray-300 rounded-md shadow-sm py-2 px-4 text-left flex justify-between items-center">
                            <span id="penerima-dropdown-label" class="text-gray-700">Pilih Penerima...</span>
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </button>

                        <div id="penerima-dropdown-panel" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg hidden">
                            <div class="space-y-4 max-h-60 overflow-y-auto p-4">

                                <!-- Grup Pengurus & Staf -->
                                <div class="kategori-grup">
                                    <h3 class="font-bold text-gray-600 mb-2 border-b">Grup Pengurus & Staf</h3>
                                    <label class="flex items-center space-x-2 text-sm font-semibold text-blue-600 cursor-pointer">
                                        <input type="checkbox" class="rounded pilih-semua">
                                        <span>Pilih Semua di Kategori Ini</span>
                                    </label>
                                    <div class="mt-2 space-y-1 pl-4">
                                        <?php foreach ($grup_pengurus as $grup): ?>
                                            <label class="flex items-center space-x-2 text-sm text-gray-700 cursor-pointer">
                                                <input type="checkbox"
                                                    class="rounded penerima-checkbox"
                                                    data-target="<?php echo htmlspecialchars($grup['group_id']); ?>"
                                                    data-label="<?php echo htmlspecialchars(substr($grup['nama_grup'], 5)); ?>"
                                                    data-type="grup">
                                                <span><?php echo htmlspecialchars(substr($grup['nama_grup'], 5)); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Grup Kelas -->
                                <div class="kategori-grup">
                                    <h3 class="font-bold text-gray-600 mb-2 border-b">Grup Kelas</h3>
                                    <label class="flex items-center space-x-2 text-sm font-semibold text-blue-600 cursor-pointer">
                                        <input type="checkbox" class="rounded pilih-semua">
                                        <span>Pilih Semua di Kategori Ini</span>
                                    </label>
                                    <div class="mt-2 space-y-1 pl-4">
                                        <?php foreach ($grup_kelas as $grup): ?>
                                            <label class="flex items-center space-x-2 text-sm text-gray-700 cursor-pointer">
                                                <input type="checkbox"
                                                    class="rounded penerima-checkbox"
                                                    data-target="<?php echo htmlspecialchars($grup['group_id']); ?>"
                                                    data-label="<?php echo htmlspecialchars(substr($grup['nama_grup'], 5)); ?>"
                                                    data-type="grup">
                                                <span><?php echo htmlspecialchars(substr($grup['nama_grup'], 5)); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Kontak Individu -->
                                <div class="kategori-grup">
                                    <h3 class="font-bold text-gray-600 mb-2 border-b">Kontak Individu</h3>
                                    <label class="flex items-center space-x-2 text-sm font-semibold text-blue-600 cursor-pointer">
                                        <input type="checkbox" class="rounded pilih-semua">
                                        <span>Pilih Semua di Kategori Ini</span>
                                    </label>
                                    <div class="mt-2 space-y-1 pl-4">
                                        <?php foreach ($individu_kontak as $kontak): ?>
                                            <label class="flex items-center space-x-2 text-sm text-gray-700 cursor-pointer">
                                                <input type="checkbox"
                                                    class="rounded penerima-checkbox"
                                                    data-target="<?php echo htmlspecialchars($kontak['nomor_wa']); ?>"
                                                    data-label="<?php echo htmlspecialchars($kontak['nama']); ?>"
                                                    data-type="individu">
                                                <span><?php echo htmlspecialchars($kontak['nama']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Input Manual -->
                    <div class="mt-4">
                        <h3 class="font-bold text-gray-600 mb-2">Tambahkan Manual</h3>
                        <textarea id="manual-nomor" rows="3"
                            class="p-2 w-full border border-gray-300 rounded-md shadow-sm text-sm"
                            placeholder="Masukkan nomor WA atau ID Grup, pisahkan dengan koma."></textarea>
                    </div>
                </div>
            </div>

            <!-- Tombol Kirim -->
            <div class="mt-6 border-t pt-4 flex justify-end gap-3">
                <button type="button" onclick="bukaModalJadwal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
                    <i class="far fa-clock mr-2"></i> Jadwalkan
                </button>
                <button type="button" id="btn-kirim-sekarang"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
                    <i class="fas fa-paper-plane mr-2"></i> Kirim Sekarang
                </button>
            </div>
        </form>
    </div>

    <!-- ===== KARTU TEMPLATE ===== -->
    <div class="bg-white p-6 rounded-2xl shadow-lg mt-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Gunakan Template</h2>
        <div class="flex flex-col sm:flex-row gap-4 items-center">
            <select id="template-select" class="flex-grow w-full border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500">
                <option value="">-- Pilih Template untuk Digunakan --</option>
                <?php foreach ($templates as $t): ?>
                    <option value="<?php echo $t['id']; ?>" data-template="<?php echo htmlspecialchars($t['isi_template']); ?>">
                        <?php echo htmlspecialchars($t['judul_template']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="bukaModalKelola()"
                class="w-full sm:w-auto bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg flex-shrink-0">
                <i class="fas fa-cog mr-2"></i> Kelola
            </button>
            <button type="button" onclick="bukaModalTambah()"
                class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex-shrink-0 transition-colors">
                <i class="fas fa-plus mr-2"></i> Tambah Template Baru
            </button>
        </div>
    </div>
</div>

<!-- =================================================================
     MODAL PROGRESS PENGIRIMAN
     ================================================================= -->
<div id="modalProgress" class="fixed inset-0 bg-black bg-opacity-70 flex justify-center items-center z-50 hidden backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

        <!-- Header -->
        <div id="progress-header" class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4 flex items-center gap-3">
            <!-- Animasi mengirim (tampil saat loading) -->
            <div id="progress-icon-sending" class="flex gap-1 items-end">
                <span class="w-2 h-2 bg-white rounded-full dot-1 inline-block"></span>
                <span class="w-2 h-2 bg-white rounded-full dot-2 inline-block"></span>
                <span class="w-2 h-2 bg-white rounded-full dot-3 inline-block"></span>
            </div>
            <h3 id="progress-title" class="text-white font-bold text-lg">Mengirim Pengumuman...</h3>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-4">

            <!-- Counter -->
            <div class="flex justify-between items-center text-sm font-semibold text-gray-600">
                <span id="progress-counter-text">Mempersiapkan...</span>
                <span id="progress-percent" class="text-green-600 font-bold text-base">0%</span>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden shadow-inner">
                <div id="progress-bar-fill"
                    class="h-4 bg-gradient-to-r from-green-400 to-emerald-500 rounded-full shadow-sm"
                    style="width: 0%">
                </div>
            </div>

            <!-- Log Scroll Area -->
            <div id="progress-log"
                class="bg-gray-50 border border-gray-200 rounded-xl h-44 overflow-y-auto p-3 space-y-1.5 text-xs font-mono">
                <p class="text-gray-400 italic">Log pengiriman akan muncul di sini...</p>
            </div>

            <!-- Summary (hidden until done) -->
            <div id="progress-summary" class="hidden">
                <!-- diisi oleh JS -->
            </div>

            <!-- Tombol tutup (hidden until done) -->
            <div id="progress-close-wrap" class="hidden flex justify-end">
                <button onclick="tutupModalProgress()"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-5 rounded-lg transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =================================================================
     MODAL PENJADWALAN
     ================================================================= -->
<div id="modalJadwal" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Jadwalkan Pengiriman</h3>
        <div>
            <label for="waktu_kirim_input" class="block text-sm font-medium text-gray-700">Pilih Tanggal & Waktu Kirim</label>
            <input type="datetime-local" id="waktu_kirim_input" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
        </div>
        <div class="flex justify-end pt-6 mt-4 border-t gap-2">
            <button type="button" onclick="tutupModalJadwal()"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg">Batal</button>
            <button type="button" id="btn-submit-jadwal"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Simpan Jadwal</button>
        </div>
    </div>
</div>

<!-- =================================================================
     MODAL KELOLA TEMPLATE
     ================================================================= -->
<div id="modalKelola" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl p-6">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Kelola Template Pengumuman</h3>
            <button onclick="tutupModalKelola()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <div class="max-h-[60vh] overflow-y-auto">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-2 px-3 text-left text-sm font-semibold text-gray-600">Judul Template</th>
                        <th class="py-2 px-3 text-left text-sm font-semibold text-gray-600">Isi Cuplikan</th>
                        <th class="py-2 px-3 text-center text-sm font-semibold text-gray-600">Aksi</th>
                    </tr>
                </thead>
                <tbody id="template-list-body">
                    <?php foreach ($templates as $t): ?>
                        <tr id="template-row-<?php echo $t['id']; ?>" class="border-b">
                            <td class="py-2 px-3 font-medium"><?php echo htmlspecialchars($t['judul_template']); ?></td>
                            <td class="py-2 px-3 text-sm text-gray-500 italic">
                                <?php echo htmlspecialchars(substr($t['isi_template'], 0, 70)) . '...'; ?>
                            </td>
                            <td class="py-2 px-3 text-center whitespace-nowrap">
                                <button onclick='bukaModalEdit(<?php echo json_encode($t); ?>)'
                                    class="text-blue-500 hover:text-blue-700 mr-3" title="Edit">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button onclick="hapusTemplate(<?php echo $t['id']; ?>, this)"
                                    class="text-red-500 hover:text-red-700" title="Hapus">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =================================================================
     MODAL FORM TAMBAH / EDIT TEMPLATE
     ================================================================= -->
<div id="modalFormTemplate" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6">
        <h3 id="modalTemplateTitle" class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Tambah Template Baru</h3>
        <form id="formTemplate">
            <input type="hidden" id="templateAction" value="simpan_template">
            <input type="hidden" id="templateId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Judul Template</label>
                    <input type="text" id="judulTemplateInput"
                        class="p-2 mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-cyan-500"
                        placeholder="Contoh: Pengumuman Libur Nasional" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Isi Template</label>
                    <textarea id="isiTemplateInput" rows="8"
                        class="p-2 mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-cyan-500"
                        placeholder="Ketik isi template di sini..." required></textarea>
                </div>
            </div>
            <div class="flex justify-end pt-6 mt-4 border-t gap-2">
                <button type="button" onclick="tutupModalFormTemplate()"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg">Batal</button>
                <button type="submit" id="templateSubmitButton"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Simpan Template</button>
            </div>
        </form>
    </div>
</div>

<!-- =================================================================
     SCRIPT UTAMA
     ================================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const AJAX_URL       = 'pages/pengaturan//ajax_pengumuman.php';
    const pesanTextarea  = document.getElementById('pesan-textarea');
    const templateSelect = document.getElementById('template-select');

    // ====================================================
    // DROPDOWN CHECKLIST PENERIMA
    // ====================================================
    const dropdownButton    = document.getElementById('penerima-dropdown-button');
    const dropdownPanel     = document.getElementById('penerima-dropdown-panel');
    const dropdownLabel     = document.getElementById('penerima-dropdown-label');
    const dropdownContainer = document.getElementById('penerima-dropdown-container');

    dropdownButton.addEventListener('click', () => dropdownPanel.classList.toggle('hidden'));
    window.addEventListener('click', e => {
        if (!dropdownContainer.contains(e.target)) dropdownPanel.classList.add('hidden');
    });

    function updateDropdownLabel() {
        const count = document.querySelectorAll('.penerima-checkbox:checked').length;
        dropdownLabel.textContent = count === 0 ? 'Pilih Penerima...' : `${count} Penerima Terpilih`;
    }

    document.querySelectorAll('.pilih-semua').forEach(el => {
        el.addEventListener('change', function () {
            const grup = this.closest('.kategori-grup');
            grup.querySelectorAll('input[type="checkbox"]:not(.pilih-semua)')
                .forEach(cb => cb.checked = this.checked);
            updateDropdownLabel();
        });
    });
    document.querySelectorAll('.penerima-checkbox').forEach(cb =>
        cb.addEventListener('change', updateDropdownLabel)
    );

    // Template select → isi textarea
    templateSelect.addEventListener('change', function () {
        const content = this.options[this.selectedIndex].dataset.template || '';
        pesanTextarea.value = content;
    });

    // ====================================================
    // HELPER: KUMPULKAN SEMUA PENERIMA
    // ====================================================
    function kumpulkanPenerima() {
        const targets = [];
        const seen    = new Set();

        // Checkbox terpilih
        document.querySelectorAll('.penerima-checkbox:checked').forEach(cb => {
            const t = cb.dataset.target;
            const l = cb.dataset.label;
            if (t && !seen.has(t)) { seen.add(t); targets.push({ target: t, label: l }); }
        });

        // Manual input
        const manualRaw = document.getElementById('manual-nomor').value.trim();
        if (manualRaw) {
            manualRaw.split(',').forEach(n => {
                const bersih = n.replace(/[^0-9]/g, '').trim();
                if (bersih && !seen.has(bersih)) {
                    seen.add(bersih);
                    targets.push({ target: bersih, label: bersih });
                }
            });
        }

        return targets;
    }

    // ====================================================
    // MODAL PROGRESS: HELPER
    // ====================================================
    const modalProgress   = document.getElementById('modalProgress');
    const progressHeader  = document.getElementById('progress-header');
    const progressTitle   = document.getElementById('progress-title');
    const progressIcon    = document.getElementById('progress-icon-sending');
    const progressCounter = document.getElementById('progress-counter-text');
    const progressPercent = document.getElementById('progress-percent');
    const progressBar     = document.getElementById('progress-bar-fill');
    const progressLog     = document.getElementById('progress-log');
    const progressSummary = document.getElementById('progress-summary');
    const progressClose   = document.getElementById('progress-close-wrap');

    function bukaModalProgress() {
        progressLog.innerHTML     = '<p class="text-gray-400 italic">Log pengiriman akan muncul di sini...</p>';
        progressSummary.innerHTML = '';
        progressSummary.classList.add('hidden');
        progressClose.classList.add('hidden');
        progressTitle.textContent = 'Mengirim Pengumuman...';
        progressIcon.classList.remove('hidden');
        progressBar.style.width   = '0%';
        progressPercent.textContent = '0%';
        progressCounter.textContent = 'Mempersiapkan...';
        progressHeader.className  = 'bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4 flex items-center gap-3';
        modalProgress.classList.remove('hidden');
    }

    window.tutupModalProgress = function () {
        modalProgress.classList.add('hidden');
    };

    function updateProgress(current, total) {
        const pct = Math.round((current / total) * 100);
        progressBar.style.width     = pct + '%';
        progressPercent.textContent = pct + '%';
        progressCounter.textContent = `Mengirim ${current} dari ${total}...`;
    }

    function appendLog(label, success) {
        // Hapus placeholder
        const placeholder = progressLog.querySelector('.italic');
        if (placeholder) placeholder.remove();

        const item = document.createElement('div');
        item.className = 'log-item flex items-center gap-2 ' + (success ? 'text-green-700' : 'text-red-600');
        item.innerHTML = success
            ? `<i class="fas fa-check-circle text-green-500 flex-shrink-0"></i><span class="truncate">${label}</span>`
            : `<i class="fas fa-times-circle text-red-500 flex-shrink-0"></i><span class="truncate">${label}</span>`;
        progressLog.appendChild(item);
        progressLog.scrollTop = progressLog.scrollHeight;
    }

    function selesaiProgress(berhasil, gagal, total) {
        const allOk = gagal === 0;

        // Ubah header
        progressHeader.className = allOk
            ? 'bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4 flex items-center gap-3'
            : 'bg-gradient-to-r from-orange-500 to-amber-500 px-6 py-4 flex items-center gap-3';
        progressTitle.textContent = allOk ? 'Semua Berhasil Dikirim!' : 'Pengiriman Selesai';
        progressIcon.classList.add('hidden');
        progressCounter.textContent = `Selesai: ${total} penerima diproses.`;
        progressPercent.textContent = '100%';
        progressBar.style.width = '100%';

        // Summary card
        progressSummary.innerHTML = `
            <div class="flex items-center gap-4 bg-gray-50 border border-gray-200 rounded-xl p-4">
                <div class="relative flex-shrink-0">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center ${allOk ? 'bg-green-100' : 'bg-orange-100'}">
                        <i class="fas ${allOk ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-orange-500'} text-3xl anim-scale-in"></i>
                    </div>
                    ${allOk ? `<span class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full ping-slow"></span>` : ''}
                </div>
                <div class="flex-grow">
                    <p class="font-bold text-gray-800 text-sm">${allOk ? 'Semua pesan terkirim!' : 'Sebagian pesan gagal dikirim.'}</p>
                    <div class="flex gap-4 mt-1.5">
                        <span class="flex items-center gap-1 text-green-600 font-bold text-sm">
                            <i class="fas fa-check-circle"></i> ${berhasil} Berhasil
                        </span>
                        ${gagal > 0 ? `<span class="flex items-center gap-1 text-red-500 font-bold text-sm"><i class="fas fa-times-circle"></i> ${gagal} Gagal</span>` : ''}
                    </div>
                </div>
            </div>
        `;
        progressSummary.classList.remove('hidden');
        progressClose.classList.remove('hidden');
    }

    // ====================================================
    // KIRIM SEKARANG — loop per penerima
    // ====================================================
    document.getElementById('btn-kirim-sekarang').addEventListener('click', async function () {
        const pesan   = pesanTextarea.value.trim();
        const targets = kumpulkanPenerima();

        if (!pesan) { alert('Pesan tidak boleh kosong.'); return; }
        if (targets.length === 0) { alert('Pilih minimal satu penerima.'); return; }

        bukaModalProgress();

        let berhasil = 0;
        let gagal    = 0;
        const total  = targets.length;

        for (let i = 0; i < targets.length; i++) {
            const { target, label } = targets[i];

            updateProgress(i, total);

            try {
                const fd = new FormData();
                fd.append('action', 'kirim_satu');
                fd.append('target', target);
                fd.append('label',  label);
                fd.append('pesan',  pesan);

                const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.status === 'success') { berhasil++; appendLog(label, true); }
                else                           { gagal++;    appendLog(label, false); }
            } catch (e) {
                gagal++;
                appendLog(label + ' (network error)', false);
            }

            // Jeda kecil supaya server tidak kewalahan
            await new Promise(r => setTimeout(r, 300));
        }

        updateProgress(total, total);
        selesaiProgress(berhasil, gagal, total);
    });

    // ====================================================
    // MODAL JADWAL
    // ====================================================
    window.bukaModalJadwal  = () => document.getElementById('modalJadwal').classList.remove('hidden');
    window.tutupModalJadwal = () => document.getElementById('modalJadwal').classList.add('hidden');

    document.getElementById('btn-submit-jadwal').addEventListener('click', async function () {
        const pesan       = pesanTextarea.value.trim();
        const waktu_kirim = document.getElementById('waktu_kirim_input').value;
        const targets     = kumpulkanPenerima();

        if (!pesan)        { alert('Pesan tidak boleh kosong.'); return; }
        if (!waktu_kirim)  { alert('Pilih tanggal & waktu pengiriman.'); return; }
        if (targets.length === 0) { alert('Pilih minimal satu penerima.'); return; }

        this.disabled = true;
        this.textContent = 'Menyimpan...';

        try {
            const fd = new FormData();
            fd.append('action',      'jadwalkan');
            fd.append('pesan',       pesan);
            fd.append('waktu_kirim', waktu_kirim);
            targets.forEach((t, i) => {
                fd.append(`targets[${i}][target]`, t.target);
                fd.append(`targets[${i}][label]`,  t.label);
            });

            const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
            const data = await res.json();

            tutupModalJadwal();
            if (data.status === 'success') {
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal!', text: data.message });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan koneksi.' });
        } finally {
            this.disabled = false;
            this.textContent = 'Simpan Jadwal';
        }
    });

    // ====================================================
    // MODAL KELOLA TEMPLATE
    // ====================================================
    window.bukaModalKelola  = () => document.getElementById('modalKelola').classList.remove('hidden');
    window.tutupModalKelola = () => document.getElementById('modalKelola').classList.add('hidden');

    // ====================================================
    // HAPUS TEMPLATE (AJAX)
    // ====================================================
    window.hapusTemplate = async function (id, btn) {
        const row = document.getElementById('template-row-' + id);

        const result = await Swal.fire({
            title: 'Hapus Template?',
            text: 'Tindakan ini tidak bisa dibatalkan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        });

        if (!result.isConfirmed) return;

        btn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action',      'hapus_template');
            fd.append('template_id', id);

            const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.status === 'success') {
                // Hapus baris dari tabel tanpa reload
                if (row) row.style.transition = 'opacity 0.3s';
                if (row) row.style.opacity    = '0';
                setTimeout(() => { if (row) row.remove(); }, 300);

                // Hapus dari dropdown template
                const opt = templateSelect.querySelector(`option[value="${id}"]`);
                if (opt) opt.remove();

                Swal.fire({ icon: 'success', title: 'Terhapus!', text: data.message, timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal!', text: data.message });
                btn.disabled = false;
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan koneksi.' });
            btn.disabled = false;
        }
    };

    // ====================================================
    // MODAL FORM TAMBAH / EDIT TEMPLATE
    // ====================================================
    const modalFormTemplate    = document.getElementById('modalFormTemplate');
    const modalTemplateTitle   = document.getElementById('modalTemplateTitle');
    const formTemplate         = document.getElementById('formTemplate');
    const templateActionInput  = document.getElementById('templateAction');
    const templateIdInput      = document.getElementById('templateId');
    const judulTemplateInput   = document.getElementById('judulTemplateInput');
    const isiTemplateInput     = document.getElementById('isiTemplateInput');
    const templateSubmitButton = document.getElementById('templateSubmitButton');

    window.tutupModalFormTemplate = () => modalFormTemplate.classList.add('hidden');

    window.bukaModalTambah = function () {
        formTemplate.reset();
        modalTemplateTitle.textContent   = 'Tambah Template Baru';
        templateSubmitButton.textContent = 'Simpan Template';
        templateSubmitButton.className   = 'bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg';
        templateActionInput.value        = 'simpan_template';
        templateIdInput.value            = '';
        modalFormTemplate.classList.remove('hidden');
    };

    window.bukaModalEdit = function (template) {
        tutupModalKelola();
        formTemplate.reset();
        modalTemplateTitle.textContent   = 'Edit Template';
        templateSubmitButton.textContent = 'Update Template';
        templateSubmitButton.className   = 'bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg';
        templateActionInput.value        = 'update_template';
        templateIdInput.value            = template.id;
        judulTemplateInput.value         = template.judul_template;
        isiTemplateInput.value           = template.isi_template;
        modalFormTemplate.classList.remove('hidden');
    };

    // Submit form template (tambah / edit) via AJAX
    formTemplate.addEventListener('submit', async function (e) {
        e.preventDefault();

        const action = templateActionInput.value;
        const judul  = judulTemplateInput.value.trim();
        const isi    = isiTemplateInput.value.trim();
        const id     = templateIdInput.value;

        if (!judul || !isi) { alert('Judul dan isi template wajib diisi.'); return; }

        templateSubmitButton.disabled    = true;
        templateSubmitButton.textContent = 'Menyimpan...';

        try {
            const fd = new FormData();
            fd.append('action',          action);
            fd.append('judul_template',  judul);
            fd.append('isi_template',    isi);
            if (id) fd.append('template_id', id);

            const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.status === 'success') {
                tutupModalFormTemplate();

                if (action === 'simpan_template') {
                    // Tambah ke dropdown & tabel
                    const opt       = document.createElement('option');
                    opt.value       = data.id;
                    opt.dataset.template = data.isi;
                    opt.textContent = data.judul;
                    templateSelect.appendChild(opt);

                    const tbody = document.getElementById('template-list-body');
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr id="template-row-${data.id}" class="border-b">
                            <td class="py-2 px-3 font-medium">${escHtml(data.judul)}</td>
                            <td class="py-2 px-3 text-sm text-gray-500 italic">${escHtml(data.isi.substring(0, 70))}...</td>
                            <td class="py-2 px-3 text-center whitespace-nowrap">
                                <button onclick='bukaModalEdit(${JSON.stringify({id:data.id,judul_template:data.judul,isi_template:data.isi})})'
                                    class="text-blue-500 hover:text-blue-700 mr-3" title="Edit">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button onclick="hapusTemplate(${data.id}, this)"
                                    class="text-red-500 hover:text-red-700" title="Hapus">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    `);

                } else {
                    // Update baris & dropdown
                    const row = document.getElementById('template-row-' + data.id);
                    if (row) {
                        row.children[0].textContent = data.judul;
                        row.children[1].textContent = data.isi.substring(0, 70) + '...';
                        // Update inline onclick
                        row.querySelector('[title="Edit"]').setAttribute('onclick',
                            `bukaModalEdit(${JSON.stringify({id:data.id,judul_template:data.judul,isi_template:data.isi})})`
                        );
                    }
                    const opt = templateSelect.querySelector(`option[value="${data.id}"]`);
                    if (opt) { opt.textContent = data.judul; opt.dataset.template = data.isi; }
                }

                Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 1500, showConfirmButton: false });

            } else {
                Swal.fire({ icon: 'error', title: 'Gagal!', text: data.message });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan koneksi.' });
        } finally {
            templateSubmitButton.disabled    = false;
            templateSubmitButton.textContent = action === 'simpan_template' ? 'Simpan Template' : 'Update Template';
        }
    });

    // Utility: escape HTML untuk innerHTML
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

});
</script>