<?php
// ===================================================================
// Query Canggih untuk Mengambil Daftar Percakapan Terakhir (DIPERBAIKI)
// ===================================================================
$conversations = [];
$sql = "
    SELECT 
        contact,
        MAX(last_message) as last_message,
        MAX(last_timestamp) as last_timestamp,
        -- Menggunakan MAX() untuk memenuhi aturan ONLY_FULL_GROUP_BY
        COALESCE(MAX(g.nama), MAX(ps.nama_lengkap), MAX(gw.nama_grup), MAX(pn.nama), contact) as display_name
    FROM (
        -- Ambil semua pesan keluar
        SELECT 
            nomor_tujuan as contact, 
            SUBSTRING(isi_pesan, 1, 40) as last_message, 
            timestamp_kirim as last_timestamp 
        FROM log_pesan_wa
        
        UNION ALL
        
        -- Ambil semua pesan masuk (balasan)
        SELECT 
            -- Jika dari grup, gunakan id_grup; jika pribadi, gunakan nomor_pengirim
            COALESCE(id_grup, nomor_pengirim) as contact, 
            SUBSTRING(isi_balasan, 1, 40) as last_message, 
            timestamp_balasan as last_timestamp 
        FROM balasan_wa
    ) as all_messages
    -- Gabungkan dengan tabel guru, peserta, dan grup untuk mendapatkan nama
    LEFT JOIN guru g ON all_messages.contact = g.nomor_wa
    LEFT JOIN peserta ps ON all_messages.contact = ps.nomor_hp_orang_tua
    LEFT JOIN grup_whatsapp gw ON all_messages.contact = gw.group_id
    LEFT JOIN penasehat pn ON all_messages.contact = pn.nomor_wa
    
    GROUP BY contact
    ORDER BY last_timestamp DESC;
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
}
?>

<!-- Di sini Anda bisa menyertakan header/layout utama -->
<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="bg-white p-6 rounded-2xl shadow-lg">

        <h1 class="text-3xl font-bold text-gray-800 mb-4 border-b pb-3">Riwayat Percakapan WhatsApp</h1>

        <!-- Daftar Percakapan -->
        <div class="flex flex-col">
            <?php if (!empty($conversations)): ?>
                <?php foreach ($conversations as $convo): ?>
                    <a href="?page=pengaturan/riwayat_chat&target=<?php echo urlencode($convo['contact']); ?>" class="block hover:bg-gray-100 transition duration-150 ease-in-out">
                        <div class="flex items-center px-4 py-4 sm:px-6 border-b border-gray-200">
                            <div class="min-w-0 flex-1 flex items-center">
                                <div class="flex-shrink-0">
                                    <!-- Ikon untuk grup atau user -->
                                    <?php if (strpos($convo['contact'], '@g.us') !== false): ?>
                                        <div class="h-12 w-12 rounded-full bg-cyan-100 text-cyan-600 flex items-center justify-center">
                                            <i class="fas fa-users fa-lg"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="h-12 w-12 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center">
                                            <i class="fas fa-user fa-lg"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0 flex-1 px-4 md:grid md:grid-cols-2 md:gap-4">
                                    <div>
                                        <?php
                                        if (substr($convo['contact'], -5) == "@g.us") {
                                        ?>
                                            <p class="text-md font-semibold text-cyan-700 truncate"><?php echo htmlspecialchars(substr($convo['display_name'], 5)); ?></p>
                                        <?php
                                        } else {
                                        ?>
                                            <p class="text-md font-semibold text-cyan-700 truncate"><?php echo htmlspecialchars($convo['display_name']); ?></p>
                                        <?php
                                        }
                                        ?>
                                        <p class="mt-1 flex items-center text-sm text-gray-500">
                                            <span class="truncate"><?php echo htmlspecialchars($convo['last_message']); ?>...</span>
                                        </p>
                                    </div>
                                    <div class="hidden md:block">
                                        <div>
                                            <p class="text-sm text-gray-900 text-right">
                                                <time datetime="<?php echo $convo['last_timestamp']; ?>"><?php echo date('d M Y, H:i', strtotime($convo['last_timestamp'])); ?></time>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-chevron-right text-gray-400"></i>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-10 text-gray-500">
                    <p>Belum ada riwayat percakapan yang tercatat.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $conn->close(); ?>
<!-- Di sini Anda bisa menyertakan footer -->