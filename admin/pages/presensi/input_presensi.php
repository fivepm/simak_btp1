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
$back_url = '?page=presensi/jadwal&periode_id=' . ($jadwal['periode_id'] ?? '') . '&kelompok=' . ($jadwal['kelompok'] ?? '') . '&kelas=' . ($jadwal['kelas'] ?? '');

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
    <div class="mb-6"><a href="<?php echo $back_url; ?>" class="text-indigo-600 hover:underline flex items-center gap-1"><i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Jadwal</a></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- KOLOM KIRI: JURNAL -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-lg shadow-md border-t-4 border-indigo-500 sticky top-4">
                <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100">
                    <h3 class="text-xl font-bold text-gray-800">Jurnal Harian</h3>

                    <!-- DUA TOMBOL AKSI -->
                    <div class="flex gap-2">
                        <button type="button" id="btn-tambah-tambahan" class="text-xs bg-yellow-500 text-white hover:bg-yellow-600 px-3 py-2 rounded-lg font-semibold transition shadow flex items-center gap-1" title="Materi Tambahan (Nasehat/Tamu)">
                            <i class="fa-solid fa-star"></i> Tambahan
                        </button>
                        <button type="button" id="btn-tambah-materi" class="text-xs bg-indigo-600 text-white hover:bg-indigo-700 px-3 py-2 rounded-lg font-semibold transition shadow flex items-center gap-1">
                            <i class="fa-solid fa-plus"></i> Materi
                        </button>
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pengajar*</label>
                        <input type="text" id="input-pengajar" value="<?php echo htmlspecialchars($jadwal['pengajar'] ?? ''); ?>"
                            class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition"
                            placeholder="Nama Anda...">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Materi Tersampaikan:</label>
                        <div id="list-materi-container" class="space-y-3 min-h-[50px]">
                            <div class="flex flex-col items-center justify-center py-4 text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                                <i class="fa-solid fa-circle-notch fa-spin mb-2"></i>
                                <span class="text-xs">Memuat materi...</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <button type="button" id="btn-simpan-jurnal"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition transform hover:scale-[1.02] active:scale-95 w-full flex justify-center items-center gap-2">
                            <i class="fa-brands fa-whatsapp text-xl"></i> Simpan Jurnal & Kirim WA
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: PRESENSI -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md min-w-0 h-fit border-t-4 border-indigo-500 sticky top-4">
            <h3 class="text-xl font-medium text-gray-800 mb-4 border-b pb-2 flex justify-between items-center">
                <span>Presensi Peserta</span>
                <span class="text-sm font-normal text-gray-500 bg-gray-100 px-2 py-1 rounded">Total: <?php echo count($peserta_presensi); ?> Siswa</span>
            </h3>
            <form id="form-presensi">
                <input type="hidden" name="action" value="simpan_kehadiran">
                <input type="hidden" name="jadwal_id" value="<?php echo $jadwal_id; ?>">
                <div class="overflow-x-auto overflow-y-auto max-h-[70vh] border rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="py-3 px-4 text-left text-xs font-bold text-gray-500 uppercase">Nama Siswa</th>
                                <th class="py-3 px-4 text-center text-xs font-bold text-gray-500 uppercase">Status Kehadiran</th>
                                <th class="py-3 px-4 text-left text-xs font-bold text-gray-500 uppercase w-1/3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="presensiTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($peserta_presensi as $peserta): $rekap_id = $peserta['id']; ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></div>
                                        <input type="hidden" name="nomor_hp_ortu[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['nomor_hp_orang_tua'] ?? ''); ?>">
                                        <input type="hidden" name="nama_peserta[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['nama_lengkap']); ?>">
                                        <input type="hidden" name="kirim_wa[<?php echo $rekap_id; ?>]" value="<?php echo htmlspecialchars($peserta['kirim_wa'] ?? ''); ?>">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap justify-center gap-2">
                                            <?php
                                            $statuses = ['Hadir', 'Izin', 'Sakit', 'Alpa'];
                                            $colors = ['Hadir' => 'green', 'Izin' => 'blue', 'Sakit' => 'yellow', 'Alpa' => 'red'];
                                            foreach ($statuses as $status):
                                                $color = $colors[$status];
                                                $checked = ($peserta['status_kehadiran'] === $status) ? 'checked' : '';
                                            ?>
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="kehadiran[<?php echo $rekap_id; ?>]" value="<?php echo $status; ?>" class="sr-only peer status-radio" data-keterangan-id="keterangan-<?php echo $rekap_id; ?>" <?php echo $checked; ?>>
                                                    <span class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 hover:shadow-md bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 border-<?php echo $color; ?>-200 peer-checked:bg-<?php echo $color; ?>-600 peer-checked:text-white">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <input type="text" name="keterangan[<?php echo $rekap_id; ?>]" id="keterangan-<?php echo $rekap_id; ?>" value="<?php echo htmlspecialchars($peserta['keterangan'] ?? ''); ?>" class="block w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition disabled:bg-gray-100 disabled:text-gray-400" placeholder="Catatan...">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 text-right">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition transform hover:scale-105 flex items-center gap-2 ml-auto"><i class="fa-solid fa-check-circle"></i> Simpan Kehadiran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH MATERI KURIKULUM -->
<div id="modalMateri" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 hidden backdrop-blur-sm px-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-bold text-white">Input Materi Kurikulum</h3>
            <button onclick="closeMateriModal()" class="text-white hover:text-gray-200 focus:outline-none">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <form id="form-tambah-materi" class="p-6 space-y-4">
            <input type="hidden" name="action" value="simpan_materi_detail">
            <input type="hidden" name="jadwal_id" value="<?php echo $jadwal_id; ?>">
            <input type="hidden" name="tipe_input" id="input_tipe_target">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Kategori Materi</label>
                <select id="select_kategori" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Memuat Kategori... --</option>
                </select>
            </div>

            <div id="area_target_selection" class="hidden space-y-4">
                <div id="block_pilih_target" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Materi / Surat / Kitab</label>
                    <select id="select_target_range" name="target_id" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Pilih Materi --</option>
                    </select>
                    <p id="info_target_limit" class="text-xs text-indigo-600 mt-1 hidden"></p>
                </div>

                <div id="block_input_range" class="hidden bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-sm font-semibold text-gray-700 pb-1">Capaian Hari Ini:</p>
                    <p class="text-xs text-black-700 border-b pb-1 mb-2" id="hint_halaman">Note: </p>
                    <div id="form_range_fields" class="grid grid-cols-2 gap-4 hidden">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1" id="label_start">Dari</label>
                            <input type="number" step="0.01" name="capaian_start" id="input_start" class="w-full border border-gray-300 rounded p-2 text-center" placeholder="0">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1" id="label_end">Sampai</label>
                            <input type="number" step="0.01" name="capaian_end" id="input_end" class="w-full border border-gray-300 rounded p-2 text-center" placeholder="0">
                        </div>
                    </div>
                    <div id="form_manual_fields" class="hidden">
                        <label class="block text-xs text-gray-500 mb-1">Volume / Jumlah</label>
                        <div class="flex items-center gap-2">
                            <input type="number" step="0.01" name="volume_manual" class="w-24 border border-gray-300 rounded p-2 text-center" placeholder="0">
                            <span id="label_satuan_manual" class="text-sm text-gray-500">Satuan</span>
                        </div>
                    </div>
                </div>

                <div id="block_checklist_list" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Checklist Materi Tersampaikan:</label>
                    <div id="checklist_container" class="max-h-40 overflow-y-auto border border-gray-300 rounded-lg p-2 bg-gray-50 space-y-2"></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Khusus (Opsional)</label>
                    <input type="text" name="catatan_tambahan" class="w-full border border-gray-300 rounded-lg p-2 text-sm" placeholder="Contoh: Perlu diulang...">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg shadow transition">
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

        const btnKembali = document.querySelector('a[href^="?page=jadwal"]');
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
                            <label class="block text-sm font-medium text-gray-700">Materi Apa? <span class="text-red-500">*</span></label>
                            <input id="swal-judul" class="w-full border border-gray-300 rounded p-2 mt-1" placeholder="Contoh: Nasehat Kejujuran">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pemateri <span class="text-red-500">*</span></label>
                            <input id="swal-pemateri" class="w-full border border-gray-300 rounded p-2 mt-1" placeholder="Contoh: Bpk. H. Fulan">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Keterangan (Opsional)</label>
                            <textarea id="swal-ket" class="w-full border border-gray-300 rounded p-2 mt-1" rows="2" placeholder="Catatan tambahan..."></textarea>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Simpan',
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

                    fetch('pages/presensi/ajax_input_presensi.php', {
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

        function resetModal() {
            document.getElementById('form-tambah-materi').reset();
            areaTarget.classList.add('hidden');
            blockPilihTarget.classList.add('hidden');
            blockInputRange.classList.add('hidden');
            blockChecklist.classList.add('hidden');
            selectKategori.innerHTML = '<option value="">-- Memuat Kategori... --</option>';
        }

        function fetchKategori() {
            const formData = new FormData();
            formData.append('action', 'get_kategori_list');
            formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');

            fetch('pages/presensi/ajax_input_presensi.php', {
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
            const option = this.options[this.selectedIndex];
            const tipe = option.dataset.tipe;

            if (!kategori) {
                areaTarget.classList.add('hidden');
                return;
            }

            inputTipeTarget.value = tipe;
            areaTarget.classList.remove('hidden');
            blockPilihTarget.classList.add('hidden');
            blockInputRange.classList.add('hidden');
            blockChecklist.classList.add('hidden');

            fetchTargetsByKategori(kategori, tipe);
        });

        function fetchTargetsByKategori(kategori, tipe) {
            const formData = new FormData();
            formData.append('action', 'get_target_by_kategori');
            formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');
            formData.append('kategori', kategori);

            if (tipe === 'CHECKLIST') {
                blockChecklist.classList.remove('hidden');
                checklistContainer.innerHTML = '<p class="text-gray-400 text-xs">Memuat daftar...</p>';
            } else {
                blockPilihTarget.classList.remove('hidden');
                selectTargetRange.innerHTML = '<option>Memuat...</option>';
            }

            fetch('pages/presensi/ajax_input_presensi.php', {
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
                    checklistContainer.innerHTML = '<p class="text-red-500 text-xs">Tidak ada target di kategori ini.</p>';
                    return;
                }
                targets.forEach(t => {
                    const div = document.createElement('div');
                    div.className = 'flex items-start gap-2 p-1 hover:bg-white rounded';
                    const disabled = t.is_filled_today ? 'disabled' : '';
                    const labelStyle = t.is_filled_today ? 'text-gray-400 line-through' : 'text-gray-700';
                    const checked = t.is_filled_today ? 'checked' : '';
                    div.innerHTML = `
                        <input type="checkbox" name="target_id[]" value="${t.id}" id="chk_${t.id}" class="mt-1" ${disabled} ${checked}>
                        <label for="chk_${t.id}" class="text-sm ${labelStyle} cursor-pointer w-full">
                            ${t.judul_materi} 
                            ${t.is_filled_today ? '<span class="text-xs text-green-500">(Sudah)</span>' : ''}
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
                formRangeFields.classList.remove('hidden');
                formManualFields.classList.add('hidden');
                let satuan = target.satuan;
                if (satuan === 'Halaman') {
                    document.getElementById('hint_halaman').textContent = `Note: Jika akhir halaman yang disampaikan tidak genap 1 halaman, masukkan capaian sampai halaman sebelumnya. Contoh : hanya sampai halaman 3 baris 7 (tidak genap satu halaman), maka tulis sampai halaman 2`;
                } else {
                    hint_halaman.classList.add('hidden');
                }
                document.getElementById('label_start').textContent = `Dari ${target.satuan}`;
                document.getElementById('label_end').textContent = `Sampai ${target.satuan}`;

                const tStart = parseFloat(target.target_start);
                const tEnd = parseFloat(target.target_end);

                infoTargetLimit.classList.remove('hidden');
                infoTargetLimit.textContent = `Target Probul: ${target.satuan} ${tStart} - ${tEnd}`;
                document.getElementById('input_start').placeholder = tStart;
                document.getElementById('input_end').placeholder = tStart;
            } else {
                formRangeFields.classList.add('hidden');
                formManualFields.classList.remove('hidden');
                document.getElementById('label_satuan_manual').textContent = target.satuan;
                infoTargetLimit.classList.add('hidden');
            }
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
            }

            showLoading();
            const formData = new FormData(this);

            fetch('pages/presensi/ajax_input_presensi.php', {
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
            const container = document.getElementById('list-materi-container');
            const formData = new FormData();
            formData.append('action', 'load_jurnal_hari_ini');
            formData.append('jadwal_id', '<?php echo $jadwal_id; ?>');

            fetch('pages/presensi/ajax_input_presensi.php', {
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
                container.innerHTML = '<div class="text-center py-4 border-2 border-dashed border-gray-200 rounded-lg text-gray-400 text-sm">Belum ada materi yang diinput hari ini.</div>';
                return;
            }
            items.forEach(item => {
                let contentHTML = '';
                let borderClass = 'border-indigo-500';
                let badgeClass = 'bg-indigo-50 text-indigo-600 border-indigo-100';
                let hapusAction = 'hapusMateri';

                if (item.is_tambahan) {
                    borderClass = 'border-yellow-500';
                    badgeClass = 'bg-yellow-50 text-yellow-600 border-yellow-100';
                    hapusAction = 'hapusTambahan';
                    contentHTML = `
                        <div class="mt-1 flex flex-col gap-1 text-sm text-gray-700">
                            <span class="font-medium text-gray-900">${item.judul_materi}</span>
                            <span class="text-xs text-gray-500"><i class="fa-solid fa-user-tie"></i> ${item.teks_capaian}</span>
                            ${item.catatan_tambahan ? `<span class="text-xs italic bg-gray-100 p-1 rounded">"${item.catatan_tambahan}"</span>` : ''}
                        </div>`;
                } else {
                    contentHTML = `
                        <h4 class="font-bold text-gray-800 text-sm mt-1">${item.judul_materi}</h4>
                        <div class="mt-1 flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-solid fa-check-circle text-green-500"></i>
                            <span class="font-medium">${item.teks_capaian}</span>
                        </div>
                        ${item.catatan_tambahan ? `<p class="text-xs text-gray-500 mt-1 italic">"${item.catatan_tambahan}"</p>` : ''}`;
                }

                const el = document.createElement('div');
                el.className = `bg-gray-50 border-l-4 ${borderClass} p-3 rounded shadow-sm relative group hover:bg-white transition`;
                el.innerHTML = `
                    <div class="pr-6">
                        <div class="flex justify-between items-start">
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded border ${badgeClass}">${item.kategori}</span>
                        </div>
                        ${contentHTML}
                    </div>
                    <button class="absolute top-2 right-2 text-gray-300 hover:text-red-500 p-1 rounded transition btn-hapus-item" data-action="${hapusAction}" data-id="${item.id}">
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
                title: 'Hapus Item?',
                text: "Materi ini akan dihapus.",
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
                    fetch('pages/presensi/ajax_input_presensi.php', {
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
                    fetch('pages/presensi/ajax_input_presensi.php', {
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
                    confirmButtonText: 'Ya, Simpan'
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
            fetch('pages/presensi/ajax_input_presensi.php', {
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

        const presensiTableBody = document.getElementById('presensiTableBody');

        function updateKeterangan(radio) {
            const keteranganInput = document.getElementById(radio.dataset.keteranganId);
            if (!keteranganInput) return;
            const status = radio.value;
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
                if (keteranganInput.value === 'Hadir' || keteranganInput.value === 'Tanpa Keterangan') keteranganInput.value = '';
            }
        }
        if (presensiTableBody) {
            presensiTableBody.querySelectorAll('.status-radio:checked').forEach(updateKeterangan);
            presensiTableBody.addEventListener('change', e => {
                if (e.target.classList.contains('status-radio')) updateKeterangan(e.target);
            });
        }
        const formPresensi = document.getElementById('form-presensi');
        if (formPresensi) {
            formPresensi.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (!formPresensi.checkValidity()) {
                    formPresensi.reportValidity();
                    return;
                }
                showLoading();
                const formData = new FormData(formPresensi);
                fetch('pages/presensi/ajax_input_presensi.php', {
                        method: 'POST',
                        body: formData
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