<?php
$redirect_url = '';

// ===================================================================
// BAGIAN 1: PEMROSESAN FORM SAAT DIKIRIM (POST)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $aksi_kirim = $_POST['aksi_kirim'] ?? null;

    // Menentukan aksi utama berdasarkan tombol yang diklik atau action dari form lain
    $main_action = $aksi_kirim ?: $action;

    // Logika untuk mengirim atau menjadwalkan
    if ($main_action === 'sekarang' || $main_action === 'jadwalkan') {
        $pesan = trim($_POST['pesan'] ?? '');
        $grup_ids = $_POST['grup_ids'] ?? [];
        $individu_nomor = $_POST['individu_nomor'] ?? [];
        $manual_nomor_string = trim($_POST['manual_nomor'] ?? '');

        // ... (kode untuk memproses $manual_nomor_string tetap sama) ...
        $nomor_manual_bersih = [];
        if (!empty($manual_nomor_string)) {
            $nomor_manual_arr = explode(',', $manual_nomor_string);
            foreach ($nomor_manual_arr as $nomor) {
                $nomor_bersih = preg_replace('/[^0-9]/', '', trim($nomor));
                if (!empty($nomor_bersih)) {
                    $nomor_manual_bersih[] = $nomor_bersih;
                }
            }
        }
        $semua_target = array_unique(array_merge($grup_ids, $individu_nomor, $nomor_manual_bersih));

        if (empty($pesan) || empty($semua_target)) {
            $redirect_url = "?page=pengaturan/pengumuman&status=gagal&pesan=" . urlencode("Pesan dan minimal satu penerima wajib diisi.");
        } else {
            // --- LOGIKA BARU DIMULAI DI SINI ---

            if ($main_action === 'sekarang') {
                // AKSI: KIRIM LANGSUNG
                $berhasil_kirim = 0;
                $gagal_kirim = 0;

                // Pastikan fungsi kirimPesanFonnte() sudah di-include/require di atas file
                foreach ($semua_target as $target) {
                    if (function_exists('kirimPesanFonnte') && kirimPesanFonnte($target, $pesan, 10)) {
                        $berhasil_kirim++;
                    } else {
                        $gagal_kirim++;
                    }
                    // Beri jeda sedikit antar pesan agar tidak overload API
                    sleep(1);
                }

                $pesan_hasil = "Proses pengiriman langsung selesai. Berhasil: $berhasil_kirim, Gagal: $gagal_kirim.";
                $redirect_url = "?page=pengaturan/pengumuman&status=sukses&pesan=" . urlencode($pesan_hasil);
            } elseif ($main_action === 'jadwalkan') {
                // AKSI: JADWALKAN (Simpan ke Database)
                $waktu_kirim = $_POST['waktu_kirim'] ?? null;
                $stmt = $conn->prepare("INSERT INTO pesan_terjadwal (nomor_tujuan, isi_pesan, status, waktu_kirim) VALUES (?, ?, 'pending', ?)");

                foreach ($semua_target as $target) {
                    $stmt->bind_param("sss", $target, $pesan, $waktu_kirim);
                    $stmt->execute();
                }
                $stmt->close();

                $jumlah_penerima = count($semua_target);
                $redirect_url = "?page=pengaturan/pengumuman&status=sukses&pesan=" . urlencode("Pengumuman berhasil dijadwalkan untuk $jumlah_penerima penerima.");
            }
            // --- AKHIR LOGIKA BARU ---
        }
    } elseif ($main_action === 'simpan_template') {
        $judul = trim($_POST['judul_template'] ?? '');
        $isi = trim($_POST['isi_template'] ?? '');

        if (empty($judul) || empty($isi)) {
            $redirect_url = "?page=pengaturan/pengumuman&status=gagal&pesan=" . urlencode("Judul dan Isi Template wajib diisi.");
        } else {
            $stmt = $conn->prepare("INSERT INTO pengumuman_template (judul_template, isi_template) VALUES (?, ?)");
            $stmt->bind_param("ss", $judul, $isi);
            if ($stmt->execute()) {
                $redirect_url = "?page=pengaturan/pengumuman&status=sukses&pesan=" . urlencode("Template baru berhasil disimpan.");
            } else {
                $redirect_url = "?page=pengaturan/pengumuman&status=gagal&pesan=" . urlencode("Gagal menyimpan template.");
            }
            $stmt->close();
        }
    } // --- AKSI BARU: Update Template ---
    elseif ($action === 'update_template') {
        $id = $_POST['template_id'];
        $judul = trim($_POST['judul_template'] ?? '');
        $isi = trim($_POST['isi_template'] ?? '');

        if (empty($id) || empty($judul) || empty($isi)) {
            $redirect_url = "?page=pengaturan/pengumuman&status=gagal&pesan=" . urlencode("Data update tidak lengkap.");
        } else {
            $stmt = $conn->prepare("UPDATE pengumuman_template SET judul_template = ?, isi_template = ? WHERE id = ?");
            $stmt->bind_param("ssi", $judul, $isi, $id);
            if ($stmt->execute()) {
                $redirect_url = "?page=pengaturan/pengumuman&status=sukses&pesan=" . urlencode("Template berhasil diperbarui.");
            } else {
                $redirect_url = "?page=pengaturan/pengumuman&status=gagal&pesan=" . urlencode("Gagal memperbarui template.");
            }
            $stmt->close();
        }
    }
    // --- AKSI BARU: Hapus Template (Versi Non-AJAX) ---
    elseif ($action === 'hapus_template') {
        $id = $_POST['template_id'] ?? null;

        if ($id) {
            $stmt = $conn->prepare("DELETE FROM pengumuman_template WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url = "?page=pengaturan/pengumuman&status=sukses&pesan=" . urlencode("Template berhasil dihapus.");
            } else {
                $redirect_url = "?page=pengaturan/pengumuman&status=gagal&pesan=" . urlencode("Gagal menghapus template dari database.");
            }
            $stmt->close();
        } else {
            $redirect_url = "?page=pengaturan/pengumuman&status=gagal&pesan=" . urlencode("ID template tidak valid.");
        }
    }
}

// ===================================================================
// BAGIAN 2: PENGAMBILAN DATA UNTUK TAMPILAN (GET)
// ===================================================================
$grup_pengurus = [];
$grup_kelas = [];
$individu_kontak = [];

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
        SELECT nama, nomor_wa FROM guru WHERE nomor_wa IS NOT NULL AND nomor_wa != ''
        UNION ALL
        SELECT nama, nomor_wa FROM penasehat WHERE nomor_wa IS NOT NULL AND nomor_wa != ''
        -- Tambahkan tabel lain di sini jika perlu dengan UNION ALL --
    ) as semua_kontak
    GROUP BY nomor_wa
    ORDER BY nama ASC;
";

$result_individu = $conn->query($sql_individu_unik);
if ($result_individu) {
    while ($row = $result_individu->fetch_assoc()) {
        $individu_kontak[] = $row;
    }
}

$templates = [];
$result_templates = $conn->query("SELECT id, judul_template, isi_template FROM pengumuman_template ORDER BY judul_template ASC");
if ($result_templates) {
    while ($row = $result_templates->fetch_assoc()) {
        $templates[] = $row;
    }
}
?>

<!-- Di sini Anda bisa menyertakan header/layout utama -->
<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <!-- Kartu Utama: Kirim Pengumuman -->
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <h1 class="text-3xl font-bold text-gray-800 mb-4 border-b pb-3">Kirim Pengumuman via WhatsApp</h1>
        <!-- Notifikasi -->
        <?php if (isset($_GET['status'], $_GET['pesan'])): ?>
            <div id="<?php echo ($_GET['status'] === 'sukses') ? 'success-alert' : 'error-alert'; ?>" class="bg-<?php echo $_GET['status'] === 'gagal' ? 'red' : 'green'; ?>-100 border-l-4 border-<?php echo $_GET['status'] === 'gagal' ? 'red' : 'green'; ?>-500 text-<?php echo $_GET['status'] === 'gagal' ? 'red' : 'green'; ?>-700 p-4 mb-4 rounded-lg" role="alert">
                <p><?php echo htmlspecialchars(urldecode($_GET['pesan'])); ?></p>
            </div>
        <?php endif; ?>
        <form id="formPengumuman" method="POST" action="">
            <input type="hidden" name="action" value="kirim_pengumuman">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Kolom Kiri: Tulis Pesan -->
                <div class="lg:col-span-2">
                    <h2 class="text-xl font-semibold text-gray-700 mb-2">1. Tulis Pesan Anda</h2>
                    <textarea id="pesan-textarea" name="pesan" rows="12" class="w-full border border-black-300 p-2 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500" placeholder="Ketik pengumuman di sini..." required></textarea>
                </div>

                <!-- Kolom Kanan: Pilih Penerima (Layout Baru) -->
                <div class="lg:col-span-1">
                    <h2 class="text-xl font-semibold text-gray-700 mb-2">2. Pilih Penerima</h2>

                    <!-- Dropdown Checklist Container -->
                    <div class="relative" id="penerima-dropdown-container">
                        <!-- Tombol untuk membuka dropdown -->
                        <button type="button" id="penerima-dropdown-button" class="w-full bg-white border border-gray-300 rounded-md shadow-sm py-2 px-4 text-left flex justify-between items-center">
                            <span id="penerima-dropdown-label" class="text-gray-700">Pilih Penerima...</span>
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </button>

                        <!-- Panel Dropdown Checklist (tersembunyi secara default) -->
                        <div id="penerima-dropdown-panel" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg hidden">
                            <div class="space-y-4 max-h-60 overflow-y-auto p-4">
                                <!-- Kategori: Grup Pengurus -->
                                <div class="kategori-grup">
                                    <h3 class="font-bold text-gray-600 mb-2 border-b">Grup Pengurus & Staf</h3>
                                    <label class="flex items-center space-x-2 text-sm font-semibold text-blue-600 cursor-pointer">
                                        <input type="checkbox" class="rounded pilih-semua">
                                        <span>Pilih Semua di Kategori Ini</span>
                                    </label>
                                    <div class="mt-2 space-y-1 pl-4">
                                        <?php foreach ($grup_pengurus as $grup): ?>
                                            <label class="flex items-center space-x-2 text-sm text-gray-700">
                                                <input type="checkbox" name="grup_ids[]" value="<?php echo htmlspecialchars($grup['group_id']); ?>" class="rounded penerima-checkbox">
                                                <span><?php echo substr($grup['nama_grup'], 5); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- Kategori lain (Grup Kelas, Kontak Individu) mengikuti pola yang sama -->
                                <!-- Kategori: Grup Kelas -->
                                <div class="kategori-grup">
                                    <h3 class="font-bold text-gray-600 mb-2 border-b">Grup Kelas</h3>
                                    <label class="flex items-center space-x-2 text-sm font-semibold text-blue-600 cursor-pointer">
                                        <input type="checkbox" class="rounded pilih-semua">
                                        <span>Pilih Semua di Kategori Ini</span>
                                    </label>
                                    <div class="mt-2 space-y-1 pl-4">
                                        <?php foreach ($grup_kelas as $grup): ?>
                                            <label class="flex items-center space-x-2 text-sm text-gray-700">
                                                <input type="checkbox" name="grup_ids[]" value="<?php echo htmlspecialchars($grup['group_id']); ?>" class="rounded penerima-checkbox">
                                                <span><?php echo substr($grup['nama_grup'], 5); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- Kategori: Individu -->
                                <div class="kategori-grup">
                                    <h3 class="font-bold text-gray-600 mb-2 border-b">Kontak Individu</h3>
                                    <label class="flex items-center space-x-2 text-sm font-semibold text-blue-600 cursor-pointer">
                                        <input type="checkbox" class="rounded pilih-semua">
                                        <span>Pilih Semua di Kategori Ini</span>
                                    </label>
                                    <div class="mt-2 space-y-1 pl-4">
                                        <?php foreach ($individu_kontak as $kontak): ?>
                                            <label class="flex items-center space-x-2 text-sm text-gray-700">
                                                <input type="checkbox" name="individu_nomor[]" value="<?php echo htmlspecialchars($kontak['nomor_wa']); ?>" class="rounded penerima-checkbox">
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
                        <textarea name="manual_nomor" rows="3" class="p-2 w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Masukkan nomor WA atau ID Grup, pisahkan dengan koma."></textarea>
                    </div>
                </div>
            </div>
            <!-- Tombol Kirim -->
            <div class="mt-6 border-t pt-4 flex justify-end gap-3">
                <button type="button" onclick="bukaModalJadwal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
                    <i class="far fa-clock mr-2"></i> Jadwalkan
                </button>
                <button type="submit" name="aksi_kirim" value="sekarang" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
                    <i class="fas fa-paper-plane mr-2"></i> Kirim Sekarang
                </button>
            </div>
        </form>
    </div>

    <!-- Kartu "Gunakan Template" -->
    <div class="bg-white p-6 rounded-2xl shadow-lg mt-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Gunakan Template</h2>
        <div class="flex flex-col sm:flex-row gap-4 items-center">
            <select id="template-select" class="flex-grow w-full border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500">
                <option value="">-- Pilih Template untuk Digunakan --</option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?php echo $template['id']; ?>" data-template="<?php echo htmlspecialchars($template['isi_template']); ?>">
                        <?php echo htmlspecialchars($template['judul_template']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="bukaModalKelola()" class="w-full sm:w-auto bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg flex-shrink-0">
                <i class="fas fa-cog mr-2"></i> Kelola
            </button>
            <button type="button" onclick="bukaModalTambah()" class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex-shrink-0 transition-colors">
                <i class="fas fa-plus mr-2"></i> Tambah Template Baru
            </button>
        </div>
    </div>
</div>

<!-- Modal Tambah Template Baru -->
<div id="modalFormTemplate" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 transform transition-all duration-300">
        <h3 id="modalTemplateTitle" class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Tambah Template Baru</h3>
        <form id="formTemplate" method="POST" action="">
            <input type="hidden" name="action" id="templateAction" value="simpan_template">
            <input type="hidden" name="template_id" id="templateId">
            <div class="space-y-4">
                <div>
                    <label for="judulTemplateInput" class="block text-sm font-medium text-gray-700">Judul Template</label>
                    <input type="text" name="judul_template" id="judulTemplateInput" class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500" placeholder="Contoh: Pengumuman Libur Nasional" required>
                </div>
                <div>
                    <label for="isiTemplateInput" class="block text-sm font-medium text-gray-700">Isi Template</label>
                    <textarea name="isi_template" id="isiTemplateInput" rows="8" class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500" placeholder="Ketik isi template di sini..." required></textarea>
                </div>
            </div>
            <div class="flex justify-end pt-6 mt-4 border-t">
                <button type="button" onclick="tutupModalFormTemplate()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg mr-2">Batal</button>
                <button type="submit" id="templateSubmitButton" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Simpan Template</button>
            </div>
        </form>
    </div>
</div>

<div id="modalJadwal" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Jadwalkan Pengiriman</h3>
        <div>
            <label for="waktu_kirim_input" class="block text-sm font-medium text-gray-700">Pilih Tanggal & Waktu Kirim</label>
            <input type="datetime-local" id="waktu_kirim_input" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        </div>
        <div class="flex justify-end pt-6 mt-4 border-t">
            <button type="button" onclick="tutupModalJadwal()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg mr-2">Batal</button>
            <button type="button" onclick="submitJadwal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Simpan Jadwal</button>
        </div>
    </div>
</div>

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
                    <?php foreach ($templates as $template): ?>
                        <tr id="template-row-<?php echo $template['id']; ?>" class="border-b">
                            <td class="py-2 px-3 font-medium"><?php echo htmlspecialchars($template['judul_template']); ?></td>
                            <td class="py-2 px-3 text-sm text-gray-500 italic">
                                <?php echo htmlspecialchars(substr($template['isi_template'], 0, 70)) . '...'; ?>
                            </td>
                            <td class="py-2 px-3 text-center">
                                <button onclick='bukaModalEdit(<?php echo json_encode($template); ?>)' class="text-blue-500 hover:text-blue-700 mr-3" title="Edit">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <form method="POST" action="" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus template ini?');">
                                    <input type="hidden" name="action" value="hapus_template">
                                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700" title="Hapus">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const autoHideAlert = (alertId) => {
            const alertElement = document.getElementById(alertId);
            if (alertElement) {
                setTimeout(() => {
                    alertElement.style.transition = 'opacity 0.5s ease';
                    alertElement.style.opacity = '0';
                    setTimeout(() => {
                        alertElement.style.display = 'none';
                    }, 500); // Waktu untuk animasi fade-out
                }, 3000); // 3000 milidetik = 3 detik
            }
        };
        autoHideAlert('success-alert');
        autoHideAlert('error-alert');

        // === DEKLARASI ELEMEN-ELEMEN PENTING ===

        // Elemen utama
        const pesanTextarea = document.getElementById('pesan-textarea');
        const templateSelect = document.getElementById('template-select');

        // Elemen untuk Modal Kelola
        const modalKelola = document.getElementById('modalKelola');

        // Elemen untuk Modal Form (Tambah/Edit)
        const modalFormTemplate = document.getElementById('modalFormTemplate');
        const modalTemplateTitle = document.getElementById('modalTemplateTitle');
        const formTemplate = document.getElementById('formTemplate');
        const templateAction = document.getElementById('templateAction');
        const templateId = document.getElementById('templateId');
        const judulTemplateInput = document.getElementById('judulTemplateInput');
        const isiTemplateInput = document.getElementById('isiTemplateInput');
        const templateSubmitButton = document.getElementById('templateSubmitButton');

        // === FUNGSI-FUNGSI UNTUK MENGONTROL MODAL ===

        // Fungsi untuk membuka/menutup Modal Kelola
        window.bukaModalKelola = function() {
            modalKelola.classList.remove('hidden');
        }
        window.tutupModalKelola = function() {
            modalKelola.classList.add('hidden');
        }

        // Fungsi untuk menutup Modal Form Tambah/Edit
        window.tutupModalFormTemplate = function() {
            modalFormTemplate.classList.add('hidden');
        }

        // Fungsi ini dipanggil saat tombol "Tambah Template Baru" diklik
        window.bukaModalTambah = function() {
            formTemplate.reset(); // Mengosongkan form
            modalTemplateTitle.textContent = 'Tambah Template Baru';
            templateSubmitButton.textContent = 'Simpan Template';
            // Atur ulang warna tombol menjadi hijau
            templateSubmitButton.className = 'bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg';
            templateAction.value = 'simpan_template';
            templateId.value = ''; // Pastikan ID kosong
            modalFormTemplate.classList.remove('hidden');
        }

        // Fungsi ini dipanggil saat ikon 'edit' di dalam modal kelola diklik
        window.bukaModalEdit = function(template) {
            tutupModalKelola();
            formTemplate.reset();
            modalTemplateTitle.textContent = 'Edit Template';
            templateSubmitButton.textContent = 'Update Template';
            // Ubah warna tombol menjadi biru untuk mode edit
            templateSubmitButton.className = 'bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg';
            templateAction.value = 'update_template';

            // Isi form dengan data yang ada
            templateId.value = template.id;
            judulTemplateInput.value = template.judul_template;
            isiTemplateInput.value = template.isi_template;

            modalFormTemplate.classList.remove('hidden');
        }

        // --- BAGIAN SCRIPT UNTUK MODAL PENJADWALAN ---

        // 1. Deklarasi elemen-elemen yang dibutuhkan
        const modalJadwal = document.getElementById('modalJadwal');
        // Pastikan form utama Anda punya ID ini: id="formPengumuman"
        const formPengumuman = document.getElementById('formPengumuman');

        // 2. Fungsi untuk membuka modal
        window.bukaModalJadwal = function() {
            if (modalJadwal) {
                modalJadwal.classList.remove('hidden');
            } else {
                alert("Error: Elemen modal dengan ID 'modalJadwal' tidak ditemukan!");
            }
        }

        // 3. Fungsi untuk menutup modal
        window.tutupModalJadwal = function() {
            if (modalJadwal) {
                modalJadwal.classList.add('hidden');
            }
        }

        // 4. Fungsi untuk memproses dan mengirim form terjadwal
        window.submitJadwal = function() {
            const waktuKirimInput = document.getElementById('waktu_kirim_input');

            if (!waktuKirimInput || !formPengumuman) {
                alert("Error: Elemen form penting tidak ditemukan. Periksa ID pada form utama dan input waktu.");
                return;
            }

            const waktuKirimValue = waktuKirimInput.value;
            if (!waktuKirimValue) {
                alert('Silakan pilih tanggal dan waktu pengiriman terlebih dahulu.');
                return;
            }

            // Membuat input tersembunyi untuk 'waktu_kirim'
            const hiddenInputWaktu = document.createElement('input');
            hiddenInputWaktu.type = 'hidden';
            hiddenInputWaktu.name = 'waktu_kirim';
            hiddenInputWaktu.value = waktuKirimValue;
            formPengumuman.appendChild(hiddenInputWaktu);

            // Membuat input tersembunyi untuk 'aksi_kirim'
            const hiddenInputAksi = document.createElement('input');
            hiddenInputAksi.type = 'hidden';
            hiddenInputAksi.name = 'aksi_kirim';
            hiddenInputAksi.value = 'jadwalkan';
            formPengumuman.appendChild(hiddenInputAksi);

            // Mengirim (submit) form utama
            formPengumuman.submit();
        }

        // === EVENT LISTENER UTAMA ===

        // Event listener untuk dropdown template utama
        templateSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const templateContent = selectedOption.dataset.template || '';
            pesanTextarea.value = templateContent;
        });

        // Logika untuk "Pilih Semua" per kategori (tetap sama)
        const semuaPilihan = document.querySelectorAll('.pilih-semua');
        semuaPilihan.forEach(pilihan => {
            pilihan.addEventListener('change', function() {
                const isChecked = this.checked;
                const kategoriContainer = this.closest('.kategori-grup');
                const checkboxesInKategori = kategoriContainer.querySelectorAll('input[type="checkbox"]:not(.pilih-semua)');
                checkboxesInKategori.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            });
        });

        // Fungsi untuk notifikasi Toast (diambil dari fitur lain)
        const toastElement = document.getElementById('toast-notification'); // Pastikan Anda punya div ini
        const toastMessage = document.getElementById('toast-message'); // Pastikan Anda punya span ini di dalam div toast

        function tampilkanToast(message, type = 'success') {
            if (!toastElement || !toastMessage) return; // Jangan jalankan jika elemen tidak ada
            toastMessage.textContent = message;
            toastElement.classList.remove('bg-red-600', 'bg-gray-800');
            if (type === 'success') {
                toastElement.classList.add('bg-gray-800');
            } else {
                toastElement.classList.add('bg-red-600');
            }
            toastElement.classList.remove('opacity-0', 'translate-x-[120%]');
            setTimeout(() => {
                toastElement.classList.add('opacity-0', 'translate-x-[120%]');
            }, 3000);
        }

        // Logika untuk redirect (tetap sama)
        <?php if (!empty($redirect_url)): ?>
            window.location.href = '<?php echo $redirect_url; ?>';
        <?php endif; ?>

        // === KODE BARU UNTUK DROPDOWN CHECKLIST ===
        const dropdownButton = document.getElementById('penerima-dropdown-button');
        const dropdownPanel = document.getElementById('penerima-dropdown-panel');
        const dropdownLabel = document.getElementById('penerima-dropdown-label');
        const allCheckboxes = document.querySelectorAll('.penerima-checkbox');
        const dropdownContainer = document.getElementById('penerima-dropdown-container');

        // Fungsi untuk membuka/menutup dropdown
        dropdownButton.addEventListener('click', () => {
            dropdownPanel.classList.toggle('hidden');
        });

        // Fungsi untuk menutup dropdown saat klik di luar
        window.addEventListener('click', function(e) {
            if (!dropdownContainer.contains(e.target)) {
                dropdownPanel.classList.add('hidden');
            }
        });

        // Fungsi untuk mengupdate label tombol
        function updateButtonLabel() {
            const checkedCount = document.querySelectorAll('.penerima-checkbox:checked').length;
            if (checkedCount === 0) {
                dropdownLabel.textContent = 'Pilih Penerima...';
            } else {
                dropdownLabel.textContent = `${checkedCount} Penerima Terpilih`;
            }
        }

        // Panggil fungsi update saat checkbox manapun berubah
        allCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateButtonLabel);
        });
        // Panggil juga saat "Pilih Semua" berubah
        document.querySelectorAll('.pilih-semua').forEach(pilihan => {
            pilihan.addEventListener('change', updateButtonLabel);
        });
        // === AKHIR KODE BARU ===
    });
</script>

<?php $conn->close(); ?>
<!-- Di sini Anda bisa menyertakan footer -->