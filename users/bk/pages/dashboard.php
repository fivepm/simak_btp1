<?php
// Query Statistik
// 1. Total Siswa (Sesuai Tingkat)
$sql_siswa = "SELECT COUNT(*) as total FROM peserta WHERE status='Aktif'";
if ($bk_tingkat == 'kelompok') {
    $sql_siswa .= " AND kelompok = '$bk_kelompok'";
}
$total_siswa = $conn->query($sql_siswa)->fetch_assoc()['total'];

// 2. Total Kasus Bulan Ini
$bulan_ini = date('Y-m');
$sql_kasus = "SELECT COUNT(*) as total FROM catatan_bk cb 
              JOIN peserta p ON cb.peserta_id = p.id 
              WHERE cb.tanggal_catatan LIKE '$bulan_ini%'";
if ($bk_tingkat == 'kelompok') {
    $sql_kasus .= " AND p.kelompok = '$bk_kelompok'";
}
$total_kasus = $conn->query($sql_kasus)->fetch_assoc()['total'];

// 3. Siswa Paling Sering Melanggar (Top 5)
$sql_top = "SELECT p.nama_lengkap, p.kelas, p.kelompok, COUNT(cb.id) as jumlah_kasus 
            FROM catatan_bk cb 
            JOIN peserta p ON cb.peserta_id = p.id
            WHERE p.status = 'Aktif' ";
if ($bk_tingkat == 'kelompok') {
    $sql_top .= " AND p.kelompok = '$bk_kelompok' ";
}
$sql_top .= " GROUP BY cb.peserta_id ORDER BY jumlah_kasus DESC LIMIT 5";
$top_pelanggar = $conn->query($sql_top);

// 4. Data Grafik Tren Kasus (6 Bulan Terakhir)
$chart_labels = [];
$chart_data = [];

// Loop mundur 5 bulan ke belakang sampai bulan ini (total 6 bulan)
for ($i = 5; $i >= 0; $i--) {
    $month_val = date('Y-m', strtotime("-$i months")); // Format: 2023-10
    $month_name = date('M', strtotime("-$i months"));  // Format: Oct

    $chart_labels[] = $month_name;

    // Query jumlah kasus per bulan tersebut
    $sql_chart = "SELECT COUNT(*) as total FROM catatan_bk cb 
                  JOIN peserta p ON cb.peserta_id = p.id
                  WHERE cb.tanggal_catatan LIKE '$month_val%' AND p.status='Aktif' ";

    if ($bk_tingkat == 'kelompok') {
        $sql_chart .= " AND p.kelompok = '$bk_kelompok'";
    }

    $res_chart = $conn->query($sql_chart)->fetch_assoc();
    $chart_data[] = (int)$res_chart['total'];
}
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6 mb-8">
    <!-- Card Statistik -->
    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500 font-medium">Total Siswa Dipantau</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($total_siswa); ?></p>
        </div>
        <div class="p-3 bg-blue-50 rounded-full text-blue-600">
            <i class="fa-solid fa-users text-xl"></i>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-red-500 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500 font-medium">Kasus Bulan Ini</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($total_kasus); ?></p>
        </div>
        <div class="p-3 bg-red-50 rounded-full text-red-600">
            <i class="fa-solid fa-triangle-exclamation text-xl"></i>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Grafik Tren -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Tren Kasus/Pelanggaran (6 Bulan Terakhir)</h3>
        <!-- Perbaikan: Bungkus canvas dalam div dengan height pasti (h-72) dan relative -->
        <div class="relative h-72 w-full">
            <canvas id="bkChart"></canvas>
        </div>
    </div>

    <!-- Tabel Top Pelanggar -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Perlu Perhatian Khusus (Top 5)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th class="px-4 py-3">Nama Siswa</th>
                        <th class="px-4 py-3 text-center">Jml Kasus</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($top_pelanggar->num_rows > 0): ?>
                        <?php while ($row = $top_pelanggar->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $row['kelas'] . ' - ' . $row['kelompok']; ?></div>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-red-600">
                                    <?php echo $row['jumlah_kasus']; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="?page=catatan&cari=<?php echo urlencode($row['nama_lengkap']); ?>" class="text-indigo-600 hover:underline">Lihat</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-center text-gray-500">Tidak ada data pelanggaran signifikan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('bkChart').getContext('2d');

    // Data dari PHP
    const labels = <?php echo json_encode($chart_labels); ?>;
    const dataKasus = <?php echo json_encode($chart_data); ?>;

    // Plugin Kustom untuk Menampilkan Label Data di Atas Titik
    const showDataLabels = {
        id: 'showDataLabels',
        afterDatasetsDraw(chart, args, pluginOptions) {
            const {
                ctx,
                data
            } = chart;

            ctx.save();
            chart.getDatasetMeta(0).data.forEach((datapoint, index) => {
                const value = data.datasets[0].data[index];

                // Konfigurasi Font Angka
                ctx.font = 'bold 12px sans-serif';
                ctx.fillStyle = '#4f46e5'; // Warna Indigo-600 agar senada dengan grafik
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';

                // Gambar teks (value) sedikit di atas titik koordinat (y - 8)
                // Hanya gambar jika nilainya ada (opsional, saat ini semua digambar)
                ctx.fillText(value, datapoint.x, datapoint.y - 8);
            });
            ctx.restore();
        }
    };

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Jumlah Kasus',
                data: dataKasus,
                borderColor: 'rgb(79, 70, 229)', // Indigo-600
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                borderWidth: 2,
                tension: 0.3, // Membuat garis melengkung halus
                fill: true,
                pointBackgroundColor: '#fff',
                pointBorderColor: 'rgb(79, 70, 229)',
                pointBorderWidth: 2,
                pointRadius: 5, // Titik sedikit lebih besar agar jelas
                pointHoverRadius: 7,
                pointHoverBackgroundColor: 'rgb(79, 70, 229)',
                pointHoverBorderColor: '#fff'
            }]
        },
        // Daftarkan plugin kustom disini
        plugins: [showDataLabels],
        options: {
            responsive: true,
            maintainAspectRatio: false, // Penting agar mengikuti height container
            layout: {
                padding: {
                    top: 20 // Tambah padding atas agar angka tertinggi tidak terpotong
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: true, // Tooltip tetap aktif jika mouse hover
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' Kasus';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1, // Pastikan angka bulat
                        precision: 0
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    suggestedMax: Math.max(...dataKasus) // Tambah ruang di atas grafik
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
</script>