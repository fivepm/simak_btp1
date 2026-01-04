<?php
// Variabel $conn dan data session sudah tersedia dari index.php
$nama_guru = htmlspecialchars($_SESSION['user_nama']);
$guru_id = $_SESSION['user_id'];
$guru_kelompok = $_SESSION['user_kelompok'] ?? '';
$guru_kelas = $_SESSION['user_kelas'] ?? '';

// === AMBIL DATA STATISTIK UNTUK DASHBOARD ===

// 1. Ambil jadwal mengajar yang sedang aktif saat ini
$jadwal_aktif = null;
$belum_dimulai = false; // Tambahkan flag ini

if (!empty($guru_kelompok) && !empty($guru_kelas)) {
    // Set timezone PHP ke WIB
    date_default_timezone_set('Asia/Jakarta');
    $waktu_sekarang_php = new DateTime(); // Waktu PHP saat ini (WIB)

    // Kueri Anda
    $sql_jadwal = "SELECT jp.id, jp.kelas, jp.kelompok, jp.tanggal, jp.jam_mulai, jp.jam_selesai, p.nama_periode 
                   FROM jadwal_presensi jp
                   JOIN periode p ON p.id = jp.periode_id
                   WHERE 
                    jp.kelompok = ? 
                    AND jp.kelas = ? 
                    -- PERBAIKAN: Ambil jadwal HARI INI saja, tanpa cek waktu NOW() di SQL
                    AND jp.tanggal = CURDATE() 
                   LIMIT 1"; // Ambil satu saja karena asumsi 1 jadwal per hari

    $stmt_jadwal = $conn->prepare($sql_jadwal);
    if ($stmt_jadwal) {
        $stmt_jadwal->bind_param("ss", $guru_kelompok, $guru_kelas);
        $stmt_jadwal->execute();
        $result_jadwal = $stmt_jadwal->get_result();

        if ($result_jadwal && $result_jadwal->num_rows > 0) {
            $jadwal_aktif = $result_jadwal->fetch_assoc();

            // ==========================================================
            // ▼▼▼ TAMBAHAN: Cek Waktu Mulai & Akhir di PHP ▼▼▼
            // ==========================================================
            try {
                $waktu_mulai_kbm = new DateTime($jadwal_aktif['tanggal'] . ' ' . $jadwal_aktif['jam_mulai']);
                $waktu_akhir_toleransi = new DateTime($jadwal_aktif['tanggal'] . ' ' . $jadwal_aktif['jam_selesai']);
                $waktu_akhir_toleransi->add(new DateInterval('PT6H')); // Tambah 6 jam

                // Cek apakah sekarang SEBELUM waktu mulai KBM
                $belum_dimulai = ($waktu_sekarang_php < $waktu_mulai_kbm);

                // Cek apakah sekarang DI LUAR jendela aktif (sebelum mulai ATAU setelah akhir+6jam)
                $di_luar_jendela_aktif = ($waktu_sekarang_php < $waktu_mulai_kbm || $waktu_sekarang_php > $waktu_akhir_toleransi);
            } catch (Exception $e) {
                error_log("Error parsing date/time for schedule ID " . $jadwal_aktif['id'] . ": " . $e->getMessage());
                // Jika error parsing, anggap saja di luar jendela aktif
                $di_luar_jendela_aktif = true;
                $belum_dimulai = false; // Pastikan flag belum_dimulai false jika ada error
            }
            // ==========================================================
            // ▲▲▲ AKHIR TAMBAHAN ▲▲▲
            // ==========================================================

        }
        $stmt_jadwal->close();
    } else {
        error_log("Gagal prepare statement sql_jadwal: " . $conn->error);
    }
}

// 2. Ambil ringkasan data kelas
$ringkasan_kelas = ['total' => 0, 'laki_laki' => 0, 'perempuan' => 0];
if (!empty($guru_kelompok) && !empty($guru_kelas)) {
    $sql_ringkasan = "SELECT 
                        COUNT(id) as total,
                        SUM(CASE WHEN jenis_kelamin = 'Laki-laki' THEN 1 ELSE 0 END) as laki_laki,
                        SUM(CASE WHEN jenis_kelamin = 'Perempuan' THEN 1 ELSE 0 END) as perempuan
                      FROM peserta
                      WHERE kelompok = ? AND kelas = ? AND status = 'Aktif'";
    $stmt_ringkasan = $conn->prepare($sql_ringkasan);
    $stmt_ringkasan->bind_param("ss", $guru_kelompok, $guru_kelas);
    $stmt_ringkasan->execute();
    $result_ringkasan = $stmt_ringkasan->get_result();
    if ($result_ringkasan) {
        $data = $result_ringkasan->fetch_assoc();
        $ringkasan_kelas['total'] = $data['total'] ?? 0;
        $ringkasan_kelas['laki_laki'] = $data['laki_laki'] ?? 0;
        $ringkasan_kelas['perempuan'] = $data['perempuan'] ?? 0;
    }
    $stmt_ringkasan->close();
}

?>
<div class="container mx-auto space-y-8">
    <!-- Header Sambutan -->
    <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-800">
            Selamat Datang, <?php echo $nama_guru; ?>!
        </h1>
        <p class="text-md text-gray-500 mt-2">
            Anda mengajar di Kelompok <span class="font-semibold capitalize text-green-600"><?php echo htmlspecialchars($guru_kelompok); ?></span>
            untuk Kelas <span class="font-semibold capitalize text-green-600"><?php echo htmlspecialchars($guru_kelas); ?></span>.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Jadwal & Ringkasan -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Kartu Jadwal Aktif -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-4 text-center">Jadwal Mengajar Saat Ini</h2>
                <?php if ($jadwal_aktif): ?>
                    <!-- Tampilkan Info Jadwal -->
                    <div class="bg-green-100 text-green-800 p-4 rounded-lg text-left">
                        <p class="font-semibold">Jadwal mengajar Anda hari ini:</p>
                        <ul class="list-disc list-inside mt-2 text-sm">
                            <li><strong>Periode:</strong> <span class="capitalize"><?php echo htmlspecialchars($jadwal_aktif['nama_periode']); ?></span></li>
                            <li><strong>Kelas:</strong> <span class="capitalize"><?php echo htmlspecialchars($jadwal_aktif['kelas']); ?></span></li>
                            <li><strong>Waktu:</strong> <?php echo date("H:i", strtotime($jadwal_aktif['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal_aktif['jam_selesai'])); ?></li>
                        </ul>
                    </div>

                    <!-- Tombol Presensi -->
                    <?php
                    // Tentukan status tombol
                    $is_disabled = $di_luar_jendela_aktif; // Tombol disable jika di luar jendela aktif
                    $button_classes = "inline-block w-full text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition duration-200 text-lg text-center";

                    if ($is_disabled) {
                        $button_classes .= " bg-gray-400 cursor-not-allowed";
                        $button_text = $belum_dimulai ? 'Presensi Belum Dibuka' : 'Waktu Presensi Habis'; // Teks berbeda
                    } else {
                        $button_classes .= " bg-indigo-600 hover:bg-indigo-700";
                        $button_text = 'Mulai Presensi Sekarang';
                    }
                    ?>
                    <a href="?page=input_presensi&jadwal_id=<?php echo $jadwal_aktif['id']; ?>"
                        id="presensi-btn-<?php echo $jadwal_aktif['id']; ?>"
                        class="<?php echo $button_classes; ?>"
                        data-jadwal-id="<?php echo $jadwal_aktif['id']; ?>"
                        data-belum-dimulai="<?php echo $belum_dimulai ? 'true' : 'false'; ?>"
                        <?php if ($is_disabled) echo 'aria-disabled="true"'; ?>>
                        <?php echo $button_text; ?>
                    </a>

                    <!-- Placeholder untuk Pesan Error (awalnya disembunyikan) -->
                    <div id="msg-presensi-<?php echo $jadwal_aktif['id']; ?>" class="text-center text-red-600 font-semibold mt-2 hidden">
                        <!-- Pesan akan diisi oleh JavaScript -->
                    </div>
                <?php else: ?>
                    <div class="text-center py-6">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-md font-medium text-gray-900">Tidak Ada Jadwal Aktif</h3>
                        <p class="mt-1 text-sm text-gray-500">Saat ini tidak ada jadwal mengajar yang sedang berlangsung.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kartu Ringkasan Kelas -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Ringkasan Kelas Saya</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-700 font-semibold">Total Peserta</p>
                        <p class="text-3xl font-bold text-blue-900"><?php echo $ringkasan_kelas['total']; ?></p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-green-700 font-semibold">Laki-laki</p>
                        <p class="text-3xl font-bold text-green-900"><?php echo $ringkasan_kelas['laki_laki']; ?></p>
                    </div>
                    <div class="bg-pink-50 p-4 rounded-lg">
                        <p class="text-sm text-pink-700 font-semibold">Perempuan</p>
                        <p class="text-3xl font-bold text-pink-900"><?php echo $ringkasan_kelas['perempuan']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Akses Cepat -->
        <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Akses Cepat</h2>
            <div class="space-y-3">
                <a href="?page=jadwal" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-calendar-alt fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Lihat Semua Jadwal</span>
                </a>
                <a href="?page=daftar_peserta" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-users fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Daftar Peserta</span>
                </a>
                <a href="?page=rekap_kehadiran" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-clipboard-list fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Rekap Kehadiran</span>
                </a>
                <a href="?page=rekap_jurnal" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-book-reader fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Rekap Jurnal</span>
                </a>
                <!-- TOMBOL BARU DITAMBAHKAN DI SINI -->
                <a href="?page=catatan_bk" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-user-shield fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Catatan BK</span>
                </a>
                <a href="?page=pustaka_materi/index" class="flex items-center w-full p-4 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <i class="fas fa-book-open fa-lg text-gray-600"></i>
                    <span class="ml-4 font-medium text-gray-800">Pustaka Materi</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Pastikan sesi sudah dimulai dan koneksi database ($conn) tersedia
if (isset($_SESSION['user_id'])) {
    $cp_user_id = $_SESSION['user_id'];
    $cp_role = $_SESSION['user_role'] ?? 'guru';

    // Tentukan tabel target
    $cp_table = ($cp_role === 'guru') ? 'guru' : 'users';

    // Ambil Hash PIN dari database
    $stmt_cp = $conn->prepare("SELECT pin FROM $cp_table WHERE id = ?");
    $stmt_cp->bind_param("i", $cp_user_id);
    $stmt_cp->execute();
    $res_cp = $stmt_cp->get_result();
    $data_cp = $res_cp->fetch_assoc();
    $stmt_cp->close();

    // Cek apakah PIN cocok dengan default '123456'
    if ($data_cp && password_verify('354313', $data_cp['pin'])) {

        // Tentukan Lokasi Halaman Profil (Sesuaikan path ini dengan struktur foldermu)
        // Contoh: jika guru di 'users/guru/profil.php'
        $link_profil = '?page=profile/index';

        echo "
        <!-- Pastikan SweetAlert2 sudah diload. Jika belum, uncomment baris bawah ini -->
        <!-- <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script> -->

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Cek apakah user baru saja menutup popup ini di sesi ini (opsional, agar tidak spamming setiap refresh)
                if (!sessionStorage.getItem('ignore_pin_warning')) {
                    
                    Swal.fire({
                        title: '⚠️ Keamanan Akun',
                        html: `
                            <div class='text-left text-sm text-gray-600'>
                                <p class='mb-2'>Anda terdeteksi masih menggunakan <b>PIN Default</b>.</p>
                                <p>Demi keamanan data, mohon segera ganti PIN Anda melalui menu Profil.</p>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ganti PIN Sekarang',
                        cancelButtonText: 'Ingatkan Nanti',
                        confirmButtonColor: '#f59e0b', // Amber/Yellow
                        cancelButtonColor: '#9ca3af',  // Gray
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Redirect ke halaman profil
                            window.location.href = '$link_profil';
                        } else {
                            // Jika pilih 'Nanti', simpan flag di session storage browser
                            // agar tidak muncul lagi sampai browser ditutup
                            sessionStorage.setItem('ignore_pin_warning', 'true');
                        }
                    });
                }
            });
        </script>
        ";
    }
}
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ambil tombol presensi (karena hanya ada satu)
        const button = document.querySelector('a[id^="presensi-btn-"]');

        if (button) {
            button.addEventListener('click', function(event) {
                // Cek apakah tombol memiliki atribut 'aria-disabled'
                const isDisabled = button.getAttribute('aria-disabled') === 'true';
                // Cek apakah karena belum dimulai
                const belumDimulai = button.getAttribute('data-belum-dimulai') === 'true';

                if (isDisabled) {
                    // 1. Cegah link default
                    event.preventDefault();

                    // 2. Ambil ID jadwal
                    const jadwalId = button.getAttribute('data-jadwal-id');
                    if (!jadwalId) return;

                    // 3. Cari elemen pesan error
                    const errorMsgElement = document.getElementById('msg-presensi-' + jadwalId);

                    if (errorMsgElement) {
                        // 4. Tentukan pesan error
                        errorMsgElement.textContent = belumDimulai ? 'Pengajian belum dimulai' : 'Waktu presensi sudah habis, Silahkan akses Input Presensi melalui menu "Jadwal Mengajar" di Sidebar'; // Pesan dinamis

                        // 5. Tampilkan pesan
                        errorMsgElement.classList.remove('hidden');

                        // 6. Sembunyikan pesan setelah 3 detik
                        setTimeout(() => {
                            errorMsgElement.classList.add('hidden');
                        }, 3000);
                    }
                }
                // Jika tombol tidak disabled, link akan berjalan normal
            });
        }
    });
</script>