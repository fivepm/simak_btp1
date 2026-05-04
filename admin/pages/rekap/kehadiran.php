<?php
// 1. Panggil file Backend (Sesuaikan path-nya jika perlu)
require_once 'ajax_kehadiran.php';
?>

<div class="container mx-auto space-y-6">

    <!-- BAGIAN 1: FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md border-t-4 border-indigo-600">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Filter Rekapitulasi Kehadiran</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="rekap/kehadiran">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium">Periode</label>
                    <select name="periode_id" id="filter_periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500" required>
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nama_periode']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md border py-2 px-3 text-gray-500 cursor-not-allowed" disabled>
                        <input type="hidden" name="kelompok" id="filter_kelompok" value="<?php echo $admin_kelompok; ?>">
                    <?php else: ?>
                        <select name="kelompok" id="filter_kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500" required>
                            <option value="-" <?php echo ($selected_kelompok == '-') ? 'selected' : ''; ?>>-- Pilih Kelompok --</option>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium">Kelas</label>
                    <select name="kelas" id="filter_kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500" required>
                        <option value="-" <?php echo ($selected_kelas == '-') ? 'selected' : ''; ?>>-- Pilih Kelas --</option>
                        <?php
                        $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                        foreach ($kelas_opts as $k):
                        ?>
                            <option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucwords($k); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition duration-200">
                        Tampilkan Rekap
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- TAMPILAN JIKA FILTER BELUM DIPILIH LENGKAP -->
    <?php if (!$is_filter_complete): ?>
        <div class="bg-white p-12 rounded-lg shadow-md border border-gray-100 text-center">
            <div class="mx-auto w-24 h-24 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center mb-4">
                <i class="fa-solid fa-filter text-4xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Pilih Filter Terlebih Dahulu</h3>
            <p class="text-gray-500">Silakan pilih <b>Periode</b>, <b>Kelompok</b>, dan <b>Kelas</b> pada formulir di atas untuk menampilkan data rekapitulasi kehadiran.</p>
        </div>
    <?php endif; ?>

    <!-- TAMPILAN JIKA FILTER SUDAH LENGKAP (BAGIAN 2: TABEL REKAP) -->
    <?php if ($is_filter_complete): ?>

        <!-- TOMBOL EKSPOR TERFILTER BARU (Menggunakan SweetAlert) -->
        <div class="bg-white p-4 rounded-lg shadow-md flex flex-col md:flex-row justify-between md:items-center gap-4 border-l-4 border-green-500">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Ekspor Rekap Kehadiran</h3>
                <p class="text-sm text-gray-600">Ekspor data kehadiran sesuai dengan kelompok dan kelas yang sedang aktif.</p>
            </div>
            <button type="button" onclick="exportKehadiranPDF()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg inline-flex items-center transition duration-300 shadow-sm w-full md:w-auto justify-center">
                <i class="fa-solid fa-file-pdf mr-2"></i> Ekspor Laporan
            </button>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <div class="mb-4 border-b pb-4 text-center">
                <h2 class="text-xl font-bold text-gray-800">Rekapitulasi Kehadiran Total</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Kelompok: <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelompok); ?></span> |
                    Kelas: <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelas); ?></span> |
                    Periode: <span class="font-semibold"><?php echo htmlspecialchars($selected_periode_nama); ?></span>
                </p>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-yellow-200">
                    <tr>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">No.</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Nama Peserta</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Total</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-green-700 uppercase">Hadir</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-blue-700 uppercase">Izin</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-yellow-700 uppercase">Sakit</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-red-700 uppercase">Alpa</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Persentase</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($rekap_data)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500 italic">Tidak ada data rekap untuk filter yang dipilih.</td>
                        </tr>
                        <?php else: $i = 1;
                        foreach ($rekap_data as $rekap): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-center text-sm text-gray-600"><?php echo $i++; ?></td>
                                <td class="px-6 py-4 font-semibold text-gray-800"><?php echo htmlspecialchars($rekap['nama_lengkap']); ?></td>
                                <td class="px-6 py-4 text-center text-sm"><?php echo $rekap['total_pertemuan']; ?></td>
                                <td class="px-6 py-4 text-center text-green-600 font-bold"><?php echo $rekap['hadir']; ?></td>
                                <td class="px-6 py-4 text-center text-blue-600 font-bold"><?php echo $rekap['izin']; ?></td>
                                <td class="px-6 py-4 text-center text-yellow-600 font-bold"><?php echo $rekap['sakit']; ?></td>
                                <td class="px-6 py-4 text-center text-red-600 font-bold"><?php echo $rekap['alpa']; ?></td>
                                <td class="px-6 py-4 text-center font-bold text-lg 
                                    <?php
                                    if ($rekap['persentase'] >= 80) echo 'text-green-600';
                                    elseif ($rekap['persentase'] >= 60) echo 'text-yellow-600';
                                    else echo 'text-red-600';
                                    ?>">
                                    <?php echo $rekap['persentase'] ? round($rekap['persentase']) : '0'; ?>%
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-bold text-gray-800 border-t-2 border-gray-300">
                        <td colspan="7" class="text-right px-6 py-4">Rata-rata Kehadiran Kelas:</td>
                        <td class="text-center px-6 py-4 text-lg">
                            <?php echo number_format($rata_rata_kehadiran, 2); ?>%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- TABEL RINCIAN TANGGAL (GRID) -->
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-4 text-center">Rincian Kehadiran per Tanggal</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-yellow-200">
                    <tr>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">No.</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase sticky left-0 z-10 bg-yellow-200">Nama Peserta</th>
                        <?php foreach ($tanggal_jadwal as $tanggal): ?>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase border-l border-yellow-300"><?php echo date('d/m', strtotime($tanggal)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($detail_kehadiran)): ?>
                        <tr>
                            <td colspan="<?php echo count($tanggal_jadwal) + 2; ?>" class="text-center py-8 text-gray-500 italic">Tidak ada rincian kehadiran.</td>
                        </tr>
                        <?php else: $i = 1;
                        foreach ($detail_kehadiran as $nama => $kehadiran): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-center text-sm text-gray-600"><?php echo $i++; ?></td>
                                <!-- Nama Sticky -->
                                <td class="px-4 py-3 font-semibold text-gray-800 sticky left-0 bg-white hover:bg-gray-50 z-10 border-r border-gray-200">
                                    <?php echo htmlspecialchars($nama); ?>
                                </td>

                                <?php foreach ($tanggal_jadwal as $tanggal):
                                    if (array_key_exists($tanggal, $kehadiran)) {
                                        $status_raw = $kehadiran[$tanggal];
                                        if ($status_raw === null) {
                                            $tampilan = '<i class="fa-solid fa-circle-question" title="Belum Diinput"></i>';
                                            $color = 'text-orange-400';
                                            $bg_cell = '';
                                        } else {
                                            $tampilan = substr($status_raw, 0, 1);
                                            $bg_cell = '';
                                            if ($status_raw === 'Hadir') $color = 'text-green-600 font-bold bg-green-50';
                                            elseif ($status_raw === 'Izin') $color = 'text-blue-600 font-bold bg-blue-50';
                                            elseif ($status_raw === 'Sakit') $color = 'text-yellow-600 font-bold bg-yellow-50';
                                            elseif ($status_raw === 'Alpa') $color = 'text-red-600 font-bold bg-red-50';
                                            else $color = 'text-gray-600';
                                        }
                                    } else {
                                        $tampilan = '-';
                                        $color = 'text-gray-300';
                                        $bg_cell = 'bg-gray-50';
                                    }
                                ?>
                                    <td class="px-4 py-3 text-center border-l border-gray-100 <?php echo $color . ' ' . $bg_cell; ?>">
                                        <?php echo $tampilan; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Rincian per Siswa (Daftar) -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-4 text-center">Rincian Kehadiran per Siswa</h2>

            <?php if (empty($rincian_per_siswa)): ?>
                <p class="text-center text-gray-500 py-8 italic">Tidak ada data rincian untuk ditampilkan.</p>
            <?php else: ?>
                <div class="space-y-8">
                    <?php $nomor = 1;
                    foreach ($rincian_per_siswa as $nama => $records): ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <!-- Header Nama Siswa -->
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h3 class="text-lg font-bold text-gray-800">
                                    <?php echo $nomor++ . '. ' . htmlspecialchars($nama); ?>
                                </h3>
                            </div>
                            <!-- Tabel Dalam -->
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-white">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Tanggal</th>
                                        <th class="px-4 py-2 text-center text-xs font-bold text-gray-500 uppercase w-32">Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Keterangan Tambahan</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <?php foreach ($records as $rec): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 whitespace-nowrap text-gray-700">
                                                <?php echo formatTanggalIndo($rec['tanggal']); ?>
                                            </td>
                                            <td class="px-4 py-2 text-center whitespace-nowrap font-bold
                                                <?php
                                                if ($rec['status_kehadiran'] === 'Hadir') echo 'text-green-600';
                                                elseif ($rec['status_kehadiran'] === 'Izin') echo 'text-blue-600';
                                                elseif ($rec['status_kehadiran'] === 'Sakit') echo 'text-yellow-600';
                                                elseif ($rec['status_kehadiran'] === 'Alpa') echo 'text-red-600';
                                                else echo 'text-gray-400';
                                                ?>">
                                                <?php echo htmlspecialchars($rec['status_kehadiran'] ?? 'Kosong'); ?>
                                            </td>
                                            <td class="px-4 py-2 text-gray-600 italic">
                                                <?php echo htmlspecialchars(ucwords($rec['keterangan'] ?? '') ?: '-'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- =========================================================================
     JAVASCRIPT: EKSPOR DENGAN SWEETALERT2
     ========================================================================= -->
<script>
    async function exportKehadiranPDF() {
        // 1. Ambil nilai filter yang saat ini aktif
        const periodeId = document.getElementById('filter_periode_id').value;
        const kelompok = document.getElementById('filter_kelompok').value;
        const kelas = document.getElementById('filter_kelas').value;

        // Validasi ekstra (walaupun tombol harusnya hanya muncul kalau sudah terisi)
        if (!periodeId || kelompok === '-' || kelas === '-') {
            Swal.fire('Peringatan', 'Harap pastikan semua filter (Periode, Kelompok, Kelas) telah dipilih.', 'warning');
            return;
        }

        // 2. Tampilkan SweetAlert dengan form pilihan laporan (Custom HTML)
        const {
            isConfirmed,
            value: formValues
        } = await Swal.fire({
            title: 'Pilihan Ekspor Kehadiran',
            html: `
                <div class="text-left mt-2 space-y-3">
                    <p class="text-sm text-gray-600 mb-2">Pilih data apa saja yang ingin Anda cetak ke dalam PDF:</p>
                    
                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                        <input type="checkbox" id="swal-chk-rekap" value="rekap_total" class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" checked>
                        <span class="ml-3 text-sm font-medium text-gray-800">Rekapitulasi Kehadiran (Tabel Total)</span>
                    </label>
                    
                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                        <input type="checkbox" id="swal-chk-grid" value="rinci_tanggal" class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
                        <span class="ml-3 text-sm font-medium text-gray-800">Rincian per Tanggal (Tampilan Grid)</span>
                    </label>
                    
                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                        <input type="checkbox" id="swal-chk-siswa" value="rinci_siswa" class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
                        <span class="ml-3 text-sm font-medium text-gray-800">Rincian per Siswa (Tampilan Daftar)</span>
                    </label>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-download"></i> Unduh PDF',
            confirmButtonColor: '#16a34a', // hijau Tailwind (green-600)
            cancelButtonText: 'Batal',
            focusConfirm: false,
            preConfirm: () => {
                const isRekap = document.getElementById('swal-chk-rekap').checked;
                const isGrid = document.getElementById('swal-chk-grid').checked;
                const isSiswa = document.getElementById('swal-chk-siswa').checked;

                if (!isRekap && !isGrid && !isSiswa) {
                    Swal.showValidationMessage('Anda harus memilih setidaknya satu tipe laporan!');
                    return false;
                }

                return {
                    isRekap,
                    isGrid,
                    isSiswa
                };
            }
        });

        // 3. Jika user menekan tombol Batal/Close
        if (!isConfirmed) {
            return;
        }

        // 4. Jika dilanjutkan, tampilkan Loading
        Swal.fire({
            title: 'Membuat Laporan PDF...',
            text: 'Merekap data kehadiran, mohon tunggu sebentar.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // 5. Siapkan Data yang akan dikirim (Menyerupai cara form HTML lama bekerja)
        const formData = new FormData();
        formData.append('action', 'export_terfilter');
        formData.append('periode_id', periodeId);
        formData.append('kelompok', kelompok);
        formData.append('kelas', kelas);
        formData.append('format', 'pdf');

        // Masukkan pilihan checkbox menjadi array di PHP (tipe_laporan[])
        if (formValues.isRekap) formData.append('tipe_laporan[]', 'rekap_total');
        if (formValues.isGrid) formData.append('tipe_laporan[]', 'rinci_tanggal');
        if (formValues.isSiswa) formData.append('tipe_laporan[]', 'rinci_siswa');

        try {
            // 6. Eksekusi request ke backend export
            const response = await fetch('pages/export/export_rekap_kehadiran.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(errorText || 'Gagal membuat laporan dari server.');
            }

            // 7. Proses pengunduhan file (Blob)
            let filename = "Laporan_Kehadiran.pdf";
            const disposition = response.headers.get('Content-Disposition');
            if (disposition && disposition.includes('filename="')) {
                filename = disposition.split('filename="')[1].split('"')[0];
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();

            a.remove();
            window.URL.revokeObjectURL(url);

            // 8. Beri tahu sukses
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Dokumen Rekap Kehadiran PDF berhasil diunduh.',
                timer: 2000,
                showConfirmButton: false
            });

        } catch (error) {
            // Tangkap jika gagal
            Swal.fire('Terjadi Kesalahan!', error.message, 'error');
        }
    }
</script>