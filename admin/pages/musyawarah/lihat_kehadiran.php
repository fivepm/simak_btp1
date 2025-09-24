<?php
$id_musyawarah = $_GET['id'] ?? null;

// Keamanan: Pastikan ID Musyawarah valid
if (!$id_musyawarah || !filter_var($id_musyawarah, FILTER_VALIDATE_INT)) {
    // Redirect ke halaman daftar jika ID tidak valid
    header('Location: ?page=musyawarah/daftar_kehadiran&status=error&msg=' . urlencode('ID Musyawarah tidak valid.'));
    exit();
}

// 1. Ambil Detail Musyawarah
$stmt_musyawarah = $conn->prepare("SELECT nama_musyawarah, tanggal FROM musyawarah WHERE id = ?");
$stmt_musyawarah->bind_param("i", $id_musyawarah);
$stmt_musyawarah->execute();
$musyawarah = $stmt_musyawarah->get_result()->fetch_assoc();
$stmt_musyawarah->close();

if (!$musyawarah) {
    header('Location: ?page=musyawarah/daftar_kehadiran&status=error&msg=' . urlencode('Musyawarah tidak ditemukan.'));
    exit();
}

// 2. Ambil Daftar Hadir
$stmt_hadir = $conn->prepare("SELECT nama_peserta, jabatan, status FROM kehadiran_musyawarah WHERE id_musyawarah = ? ORDER BY urutan ASC");
$stmt_hadir->bind_param("i", $id_musyawarah);
$stmt_hadir->execute();
$result_hadir = $stmt_hadir->get_result();
$stmt_hadir->close();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Hadir - <?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body class="bg-gray-100">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">

        <!-- Header Halaman -->
        <div class="mb-6">
            <a href="?page=musyawarah/daftar_kehadiran" class="text-cyan-600 hover:text-cyan-800 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Daftar Musyawarah
            </a>
            <h1 class="text-3xl font-bold text-gray-800 mt-2">Daftar Hadir Peserta</h1>
            <p class="text-gray-600">
                Musyawarah: <span class="font-semibold"><?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?></span>
                (<?php echo date('d F Y', strtotime($musyawarah['tanggal'])); ?>)
            </p>
        </div>

        <!-- Kontainer Tabel -->
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-3 px-4 w-16 text-center text-sm font-semibold text-gray-600 uppercase">No.</th>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Nama Peserta</th>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Dapukan</th>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <?php if ($result_hadir->num_rows > 0): ?>
                            <?php $nomor = 1; ?>
                            <?php while ($peserta = $result_hadir->fetch_assoc()): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4 text-center"><?php echo $nomor++; ?></td>
                                    <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($peserta['nama_peserta']); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($peserta['jabatan']); ?></td>
                                    <td class="py-3 px-4">
                                        <?php
                                        $status = htmlspecialchars($peserta['status']);
                                        $icon = 'fa-question-circle text-gray-500'; // Default
                                        if ($status == 'Hadir') {
                                            $icon = 'fa-check-circle text-green-500';
                                        } elseif ($status == 'Izin') {
                                            $icon = 'fa-info-circle text-blue-500';
                                        }
                                        ?>
                                        <span class="flex items-center">
                                            <i class="fas <?php echo $icon; ?> mr-2"></i>
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-6 text-gray-500">
                                    Belum ada data kehadiran untuk musyawarah ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>

</html>
<?php
$conn->close();
?>