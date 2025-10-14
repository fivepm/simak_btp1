<?php
// Ambil target (nomor HP atau ID Grup) dari URL
$target = $_GET['target'] ?? null;
if (!$target) {
    die("Target percakapan tidak ditemukan.");
}

// ===================================================================
// 1. Ambil semua pesan (keluar & masuk) dan gabungkan
// ===================================================================
$all_messages = [];
$is_group_chat = (strpos($target, '@g.us') !== false);

// --- LOGIKA BARU YANG LEBIH AKURAT ---

// Ambil pesan KELUAR dari sistem (logika ini sama untuk grup dan pribadi)
$stmt_out = $conn->prepare("SELECT 'keluar' as tipe, isi_pesan as pesan, status_kirim, timestamp_kirim as timestamp FROM log_pesan_wa WHERE nomor_tujuan = ?");
$stmt_out->bind_param("s", $target);
$stmt_out->execute();
$result_out = $stmt_out->get_result();
while ($row = $result_out->fetch_assoc()) {
    $all_messages[] = $row;
}
$stmt_out->close();

// Ambil pesan MASUK (balasan) dengan logika terpisah berdasarkan tipe chat
if ($is_group_chat) {
    // Jika ini chat grup, ambil semua balasan dari grup tersebut
    $stmt_in = $conn->prepare("SELECT 'masuk' as tipe, isi_balasan as pesan, nama_pengirim, timestamp_balasan as timestamp FROM balasan_wa WHERE id_grup = ?");
    $stmt_in->bind_param("s", $target);
} else {
    // Jika ini chat pribadi, ambil balasan dari nomor tersebut (dan pastikan bukan dari grup)
    $stmt_in = $conn->prepare("SELECT 'masuk' as tipe, isi_balasan as pesan, nama_pengirim, timestamp_balasan as timestamp FROM balasan_wa WHERE nomor_pengirim = ? AND id_grup IS NULL");
    $stmt_in->bind_param("s", $target);
}

$stmt_in->execute();
$result_in = $stmt_in->get_result();
while ($row = $result_in->fetch_assoc()) {
    $all_messages[] = $row;
}
$stmt_in->close();

// --- AKHIR PERUBAHAN LOGIKA ---

// Urutkan semua pesan berdasarkan timestamp
usort($all_messages, function ($a, $b) {
    return strtotime($a['timestamp']) - strtotime($b['timestamp']);
});

// Ambil nama tampilan untuk header chat
$display_name = $target;
$stmt_name = $conn->prepare("
    SELECT COALESCE(g.nama, ps.nama_lengkap, gw.nama_grup, pn.nama, ?) as name 
    FROM (SELECT 1) dummy 
    LEFT JOIN guru g ON g.nomor_wa = ? 
    LEFT JOIN peserta ps ON ps.nomor_hp_orang_tua = ? 
    LEFT JOIN grup_whatsapp gw ON gw.group_id = ?
    LEFT JOIN penasehat pn ON pn.nomor_wa = ?
");
$stmt_name->bind_param("sssss", $target, $target, $target, $target, $target);
$stmt_name->execute();
$result_name = $stmt_name->get_result()->fetch_assoc();
if ($result_name && !empty($result_name['name'])) {
    $display_name = $result_name['name'];
}
$stmt_name->close();

?>
<!-- Di sini Anda bisa menyertakan header/layout utama -->
<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="bg-white rounded-2xl shadow-lg flex flex-col h-[80vh]">

        <!-- Header Chat -->
        <div class="flex-shrink-0 flex items-center p-4 border-b">
            <a href="?page=pengaturan/daftar_chat" class="text-cyan-600 hover:text-cyan-800 mr-4">
                <i class="fas fa-arrow-left fa-lg"></i>
            </a>
            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                <i class="fas fa-<?php echo $is_group_chat ? 'users' : 'user'; ?> text-gray-600"></i>
            </div>
            <div>
                <!-- <h2 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($display_name); ?></h2> -->
                <?php
                if (substr($target, -5) == "@g.us") {
                ?>
                    <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars(substr($display_name, 5)); ?></p>
                <?php
                } else {
                ?>
                    <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($display_name); ?></p>
                <?php
                }
                ?>
                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($target); ?></p>
            </div>
        </div>

        <!-- Badan Chat (Area Pesan) -->
        <div id="chat-body" class="flex-grow p-6 overflow-y-auto bg-gray-50">
            <div class="space-y-4">
                <?php if (!empty($all_messages)): ?>
                    <?php foreach ($all_messages as $msg): ?>
                        <?php if ($msg['tipe'] === 'keluar'): ?>
                            <!-- Gelembung Pesan Keluar (Kanan) -->
                            <div class="flex justify-end">
                                <div class="max-w-md lg:max-w-lg bg-cyan-500 text-white rounded-xl rounded-br-none p-3 shadow">
                                    <p class="text-sm"><?php echo nl2br(htmlspecialchars($msg['pesan'])); ?></p>
                                    <div class="text-xs text-cyan-100 mt-2 text-right flex items-center justify-end">
                                        <span><?php echo date('H:i', strtotime($msg['timestamp'])); ?></span>
                                        <?php
                                        $status_icon = 'fa-check'; // Terkirim
                                        if ($msg['status_kirim'] == 'Diterima') $status_icon = 'fa-check-double';
                                        if ($msg['status_kirim'] == 'Dibaca') $status_icon = 'fa-check-double text-blue-300';
                                        if ($msg['status_kirim'] == 'Gagal') $status_icon = 'fa-exclamation-circle text-red-300';
                                        ?>
                                        <i class="fas <?php echo $status_icon; ?> ml-2"></i>
                                    </div>
                                </div>
                            </div>
                        <?php else: // tipe === 'masuk' 
                        ?>
                            <!-- Gelembung Pesan Masuk (Kiri) -->
                            <div class="flex justify-start">
                                <div class="max-w-md lg:max-w-lg bg-white rounded-xl rounded-bl-none p-3 shadow border">
                                    <?php if ($is_group_chat && isset($msg['nama_pengirim'])): // Jika dari grup, tampilkan nama pengirim 
                                    ?>
                                        <p class="text-sm font-semibold text-purple-600"><?php echo htmlspecialchars($msg['nama_pengirim']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($msg['pesan'])); ?></p>
                                    <div class="text-xs text-gray-400 mt-2 text-right">
                                        <span><?php echo date('H:i', strtotime($msg['timestamp'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-gray-500">Tidak ada pesan dalam percakapan ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Script untuk auto-scroll ke pesan terakhir
    document.addEventListener('DOMContentLoaded', function() {
        const chatBody = document.getElementById('chat-body');
        if (chatBody) {
            chatBody.scrollTop = chatBody.scrollHeight;
        }
    });
</script>

<?php $conn->close(); ?>
<!-- Di sini Anda bisa menyertakan footer -->