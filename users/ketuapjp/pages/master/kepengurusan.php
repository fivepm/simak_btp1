<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Ambil data admin yang sedang login untuk hak akses
$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

$redirect_url = '';

// === AMBIL DATA UNTUK DITAMPILKAN ===
function group_by_jabatan($data)
{
    $grouped = [];
    foreach ($data as $row) {
        $grouped[$row['jabatan']][] = $row;
    }
    return $grouped;
}

// HAK AKSES: Tentukan daftar kelompok yang akan ditampilkan
$kelompok_list = ($ketuapjp_tingkat === 'desa') ? ['bintaran', 'gedongkuning', 'jombor', 'sunten'] : [$ketuapjp_kelompok];

$pengurus_desa = [];
if ($ketuapjp_tingkat === 'desa') {
    $sql_desa = "SELECT id, nama_pengurus, jabatan FROM kepengurusan WHERE tingkat = 'desa' AND jabatan != 'Wali Kelas' ORDER BY FIELD(jabatan, 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Pengawas'), nama_pengurus";
    $result_desa = $conn->query($sql_desa);
    if ($result_desa) {
        $pengurus_desa = group_by_jabatan($result_desa->fetch_all(MYSQLI_ASSOC));
    }
}

$pengurus_kelompok = [];
foreach ($kelompok_list as $kelompok) {
    $sql_kel = "SELECT id, nama_pengurus, jabatan FROM kepengurusan WHERE tingkat = 'kelompok' AND kelompok = ? AND jabatan != 'Wali Kelas' ORDER BY FIELD(jabatan, 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Pengawas'), nama_pengurus";
    $stmt_kel = $conn->prepare($sql_kel);
    $stmt_kel->bind_param("s", $kelompok);
    $stmt_kel->execute();
    $result_kel = $stmt_kel->get_result();
    if ($result_kel) {
        $pengurus_kelompok[$kelompok]['inti'] = group_by_jabatan($result_kel->fetch_all(MYSQLI_ASSOC));
    }

    $sql_wali = "SELECT id, nama_pengurus, kelas FROM kepengurusan WHERE jabatan = 'Wali Kelas' AND kelompok = ? ORDER BY FIELD(kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'), nama_pengurus";
    $stmt_wali = $conn->prepare($sql_wali);
    $stmt_wali->bind_param("s", $kelompok);
    $stmt_wali->execute();
    $result_wali = $stmt_wali->get_result();
    if ($result_wali) {
        while ($row = $result_wali->fetch_assoc()) {
            $pengurus_kelompok[$kelompok]['wali_kelas'][$row['kelas']] = $row;
        }
    }
}
?>
<div class="container mx-auto space-y-8">
    <div>
        <h1 class="text-3xl font-semibold text-gray-800">Struktur Kepengurusan PJP</h1>
        <p class="mt-1 text-gray-600">Daftar Pengurus PJP tingkat Desa dan Kelompok.</p>
    </div>

    <!-- KARTU PJP DESA (Hanya untuk admin desa) -->
    <?php if ($ketuapjp_tingkat === 'desa'): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 border-b pb-2 mb-4">PJP Desa</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php $jabatan_list = ['Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Pengawas']; ?>
                <?php foreach ($jabatan_list as $jabatan): ?>
                    <div>
                        <h3 class="font-semibold text-gray-600"><?php echo $jabatan; ?></h3>
                        <ul class="list-disc list-inside text-gray-800 mt-1">
                            <?php if (!empty($pengurus_desa[$jabatan])): foreach ($pengurus_desa[$jabatan] as $p): ?>
                                    <li class="flex items-center justify-between group hover:bg-yellow-200">
                                        <span><?php echo htmlspecialchars($p['nama_pengurus']); ?></span>
                                    </li>
                                <?php endforeach;
                            else: ?>
                                <li class="text-gray-400 italic">Belum ada data</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- KARTU PJP KELOMPOK -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php foreach ($kelompok_list as $kelompok): ?>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold text-gray-800 border-b pb-2 mb-4 capitalize"><?php echo $kelompok; ?></h2>
                <div class="space-y-4">
                    <?php $jabatan_list_kelompok = ['Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Pengawas']; ?>
                    <?php foreach ($jabatan_list_kelompok as $jabatan): ?>
                        <div>
                            <h3 class="font-semibold text-gray-600"><?php echo $jabatan; ?></h3>
                            <ul class="list-disc list-inside text-gray-800 mt-1 text-sm">
                                <?php if (!empty($pengurus_kelompok[$kelompok]['inti'][$jabatan])): foreach ($pengurus_kelompok[$kelompok]['inti'][$jabatan] as $p): ?>
                                        <li class="flex items-center justify-between group hover:bg-yellow-200">
                                            <span><?php echo htmlspecialchars($p['nama_pengurus']); ?></span>
                                        </li>
                                    <?php endforeach;
                                else: ?>
                                    <li class="text-gray-400 italic">Belum ada data</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                    <div>
                        <h3 class="font-semibold text-gray-600 border-t pt-4 mt-4">Wali Kelas</h3>
                        <div class="space-y-2 mt-2 text-sm">
                            <?php $kelas_list_semua = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah']; ?>
                            <?php foreach ($kelas_list_semua as $kelas): ?>
                                <div class="flex items-center justify-between group">
                                    <span class="text-gray-500 capitalize"><?php echo $kelas; ?>:</span>
                                    <?php if (isset($pengurus_kelompok[$kelompok]['wali_kelas'][$kelas])): $wali = $pengurus_kelompok[$kelompok]['wali_kelas'][$kelas]; ?>
                                        <div class="font-semibold text-gray-800 flex items-center">
                                            <span><?php echo htmlspecialchars($wali['nama_pengurus']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">Belum Ditetapkan</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($redirect_url)): ?>
            window.location.href = '<?php echo $redirect_url; ?>';
        <?php endif; ?>
    });
</script>