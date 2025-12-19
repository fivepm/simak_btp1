<?php
// --- Security Check ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo "<p>Akses ditolak.</p>";
    return;
}
if (!isset($conn)) {
    echo "<p>Koneksi database hilang.</p>";
    return;
}

// --- 1. CEK STATUS AKTIF/RENCANA ---
$currentSession = null;
$tasks = [];
$sql_curr = "SELECT * FROM maintenance_sessions WHERE status IN ('planned', 'active') LIMIT 1";
$res_curr = mysqli_query($conn, $sql_curr);
if (mysqli_num_rows($res_curr) > 0) {
    $currentSession = mysqli_fetch_assoc($res_curr);

    // Ambil tugas-tugasnya
    $sessId = $currentSession['id'];
    $sql_tasks = "SELECT * FROM maintenance_tasks WHERE session_id = $sessId ORDER BY created_at ASC";
    $res_tasks = mysqli_query($conn, $sql_tasks);
    while ($row = mysqli_fetch_assoc($res_tasks)) {
        $tasks[] = $row;
    }
}

// --- 2. AMBIL RIWAYAT (Completed & Cancelled) ---
// Kita tampilkan 'cancelled' juga agar admin tau riwayat pembatalan
$historyLogs = [];
$sql_hist = "SELECT * FROM maintenance_sessions WHERE status IN ('completed', 'cancelled') ORDER BY created_at DESC LIMIT 10";
$res_hist = mysqli_query($conn, $sql_hist);
while ($row = mysqli_fetch_assoc($res_hist)) {
    $historyLogs[] = $row;
}
?>

<div class="container mx-auto p-4 md:p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Maintenance System</h1>
        <!-- Indikator Status Global -->
        <?php if ($currentSession && $currentSession['status'] == 'active'): ?>
            <span class="bg-red-100 text-red-800 text-sm font-bold px-3 py-1 rounded-full border border-red-200 animate-pulse">
                ● SISTEM SEDANG MAINTENANCE
            </span>
        <?php else: ?>
            <span class="bg-green-100 text-green-800 text-sm font-bold px-3 py-1 rounded-full border border-green-200">
                ● SISTEM ON-AIR
            </span>
        <?php endif; ?>
    </div>

    <!-- Alert Box -->
    <div id="alert-box" class="hidden mb-4"></div>

    <!-- ================================================= -->
    <!-- LOGIKA TAMPILAN 1: BELUM ADA RENCANA (IDLE)       -->
    <!-- ================================================= -->
    <?php if (!$currentSession): ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Form Buat Rencana -->
            <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg border-t-4 border-blue-500">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Buat Rencana Baru</h2>
                <p class="text-gray-600 text-sm mb-4">
                    Tentukan tujuan maintenance sebelum mematikan sistem. Anda bisa menyusun daftar tugas terlebih dahulu.
                </p>
                <form id="create-plan-form">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Judul Kegiatan</label>
                        <input type="text" id="plan-title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Contoh: Update Raport V2" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Deskripsi (Opsional)</label>
                        <textarea id="plan-desc" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition font-medium">
                        + Buat Draft Rencana
                    </button>
                </form>
            </div>

            <!-- Tabel Riwayat -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Riwayat Maintenance</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kegiatan</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">PIC</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($historyLogs as $log): ?>
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-900"><?php echo htmlspecialchars($log['title']); ?></td>
                                    <td class="px-4 py-2 text-sm">
                                        <?php if ($log['status'] == 'cancelled'): ?>
                                            <span class="px-2 py-1 bg-red-200 text-red-600 rounded text-xs">Dibatalkan</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Selesai</span>
                                            <div class="text-xs text-gray-400 mt-1"><?php echo date('d M H:i', strtotime($log['end_time'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($log['created_by_name']); ?></td>
                                    <td class="px-4 py-2">
                                        <button onclick="viewHistoryDetail(<?php echo $log['id']; ?>)" class="text-blue-600 hover:text-blue-900 text-sm font-medium">Lihat Detail</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- LOGIKA TAMPILAN 2: COMMAND CENTER (ACTIVE/PLANNED)-->
        <!-- ================================================= -->
    <?php else: ?>

        <!-- Status Banner -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6 border-l-8 <?php echo ($currentSession['status'] == 'active') ? 'border-red-500' : 'border-yellow-500'; ?>">
            <div class="flex flex-col md:flex-row justify-between md:items-center">
                <div>
                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded uppercase <?php echo ($currentSession['status'] == 'active') ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                        STATUS: <?php echo $currentSession['status']; ?>
                    </span>
                    <h2 class="text-2xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($currentSession['title']); ?></h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($currentSession['description']); ?></p>
                    <p class="text-sm text-gray-400 mt-1">Dibuat oleh: <?php echo htmlspecialchars($currentSession['created_by_name']); ?> | <?php echo date('d M H:i', strtotime($currentSession['created_at'])); ?></p>
                </div>

                <!-- TOMBOL AKSI UTAMA -->
                <div class="mt-4 md:mt-0 flex flex-col items-center gap-2">

                    <?php if ($currentSession['status'] == 'planned'): ?>
                        <!-- TOMBOL MULAI (ON) -->
                        <div class="text-center mb-2">
                            <span class="text-sm text-gray-500">Website masih bisa diakses.</span>
                        </div>
                        <button onclick="startMaintenance(<?php echo $currentSession['id']; ?>)" class="bg-red-600 text-white font-bold py-3 px-6 rounded-lg shadow-lg hover:bg-red-700 transition transform hover:scale-105 flex items-center gap-2">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            AKTIFKAN MAINTENANCE
                        </button>

                        <!-- TOMBOL BATALKAN RENCANA (BARU) -->
                        <button onclick="cancelPlan(<?php echo $currentSession['id']; ?>)" class="text-gray-500 hover:text-red-600 font-medium text-sm flex items-center gap-1 underline decoration-dotted">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Batalkan/Ganti Rencana Ini
                        </button>
                    <?php else: ?>
                        <!-- TOMBOL SELESAI (OFF) -->
                        <div class="mb-2">
                            <lottie-player src="../assets/animations/man_robot_work.json" background="transparent" speed="1" style="width: 80px; height: 80px;" loop autoplay></lottie-player>
                        </div>
                        <button onclick="finishMaintenance(<?php echo $currentSession['id']; ?>)" class="bg-green-600 text-white font-bold py-3 px-6 rounded-lg shadow-lg hover:bg-green-700 transition transform hover:scale-105 flex items-center gap-2">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            SELESAI & BUKA WEB
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DAFTAR TUGAS -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Kolom Input Tugas -->
            <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg h-fit">
                <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Tambah Tugas</h3>
                <form id="add-task-form">
                    <input type="hidden" id="sess-id" value="<?php echo $currentSession['id']; ?>">
                    <div class="mb-3">
                        <label class="text-xs font-bold text-gray-500 uppercase">Nama Tugas</label>
                        <input type="text" id="task-name" class="w-full mt-1 p-2 border rounded" placeholder="Misal: Backup Database" required>
                    </div>
                    <div class="mb-4">
                        <label class="text-xs font-bold text-gray-500 uppercase">PIC (Penanggung Jawab)</label>
                        <input type="text" id="task-pic" class="w-full mt-1 p-2 border rounded" value="<?php echo $_SESSION['user_nama']; ?>">
                    </div>
                    <button type="submit" class="w-full bg-gray-800 text-white py-2 rounded hover:bg-gray-700 font-medium">
                        + Tambahkan ke Daftar
                    </button>
                </form>
            </div>

            <!-- Kolom Checklist -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 flex justify-between">
                    <span>Daftar Checklist</span>
                    <span class="text-sm font-normal text-gray-500" id="task-count">0 Tugas</span>
                </h3>

                <ul id="task-list" class="space-y-3">
                    <?php if (empty($tasks)): ?>
                        <li id="empty-state" class="text-center py-8 text-gray-400 italic">Belum ada tugas yang didaftarkan.</li>
                    <?php else: ?>
                        <?php foreach ($tasks as $t): ?>
                            <li class="flex items-center justify-between p-3 bg-gray-50 rounded border hover:bg-gray-100 transition group" id="row-<?php echo $t['id']; ?>">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" onchange="toggleTask(<?php echo $t['id']; ?>)" class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500" <?php echo ($t['is_completed'] ? 'checked' : ''); ?>>
                                    <div>
                                        <p class="font-medium <?php echo ($t['is_completed'] ? 'text-gray-400 line-through' : 'text-gray-800'); ?>" id="text-<?php echo $t['id']; ?>">
                                            <?php echo htmlspecialchars($t['task_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">PIC: <?php echo htmlspecialchars($t['pic']); ?></p>
                                    </div>
                                </div>
                                <button onclick="deleteTask(<?php echo $t['id']; ?>)" class="text-red-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- ============================ -->
<!-- MODAL HISTORY DETAIL         -->
<!-- ============================ -->
<div id="history-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-start border-b pb-4 mb-4">
            <div>
                <h3 class="text-xl font-bold text-gray-800" id="hist-title">Detail Maintenance</h3>
                <p class="text-sm text-gray-500" id="hist-meta">...</p>
            </div>
            <button onclick="document.getElementById('history-modal').classList.add('hidden')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
        </div>
        <div id="hist-content">
            <!-- Isi Task List akan dimuat disini via JS -->
            Loading...
        </div>
    </div>
</div>

<!-- ============================ -->
<!-- MODAL PIN DISPLAY (SAAT ON)  -->
<!-- ============================ -->
<div id="pin-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75 hidden">
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full text-center">
        <h3 class="text-lg font-bold text-gray-900">Mode Maintenance AKTIF!</h3>
        <p class="text-sm text-gray-500 mt-2">Catat PIN Konfirmasi ini untuk login admin.</p>
        <div class="mt-4 p-4 bg-gray-100 rounded border-2 border-dashed border-gray-400 flex flex-col items-center justify-center relative group">
            <span id="pin-text" class="text-4xl font-mono font-bold text-gray-800 tracking-widest select-all"></span>
            <!-- Tombol Copy -->
            <button type="button" id="copy-pin-btn" class="flex items-center space-x-1 text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors focus:outline-none bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-full border border-blue-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                <span>Salin Kode</span>
            </button>
        </div>
        <button onclick="location.reload()" class="mt-6 w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Saya sudah mencatatnya</button>
    </div>
</div>

<!-- ============================ -->
<!-- MODAL KONFIRMASI ACTION      -->
<!-- ============================ -->
<div id="confirm-modal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm hidden transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-sm w-full transform scale-100 transition-transform duration-300">

        <!-- Icon Dinamis (Warning/Success) -->
        <div id="confirm-icon-container" class="mx-auto flex items-center justify-center h-14 w-14 rounded-full mb-4 bg-yellow-100">
            <!-- Icon akan diisi via JS -->
            <svg id="confirm-icon" class="h-8 w-8 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <div class="text-center">
            <h3 class="text-xl font-bold text-gray-900 mb-2" id="confirm-title">Konfirmasi</h3>
            <p class="text-sm text-gray-500 mb-6" id="confirm-message">
                Apakah Anda yakin?
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <button type="button" id="btn-cancel-action" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:text-sm">
                Batal
            </button>
            <button type="button" id="btn-confirm-action" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:text-sm">
                Ya, Lanjutkan
            </button>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
<script>
    const API_URL = 'pages/development/ajax_maintenance.php';
    const alertBox = document.getElementById('alert-box');

    // --- VARIABEL UNTUK MODAL KONFIRMASI ---
    const confirmModal = document.getElementById('confirm-modal');
    const confirmTitle = document.getElementById('confirm-title');
    const confirmMessage = document.getElementById('confirm-message');
    const confirmBtn = document.getElementById('btn-confirm-action');
    const cancelBtn = document.getElementById('btn-cancel-action');
    const iconContainer = document.getElementById('confirm-icon-container');
    const iconSvg = document.getElementById('confirm-icon');

    // Variabel untuk menyimpan aksi yang tertunda
    let pendingAction = null;
    // Format: { type: 'start'|'finish', sessionId: 123 }

    function showAlert(msg, type = 'success') {
        const color = type == 'success' ? 'green' : 'red';
        alertBox.innerHTML = `<div class="p-4 rounded bg-${color}-100 text-${color}-800 border border-${color}-200">${msg}</div>`;
        alertBox.classList.remove('hidden');
        setTimeout(() => alertBox.classList.add('hidden'), 3000);
    }

    // --- EVENT LISTENER MODAL KONFIRMASI ---
    cancelBtn.addEventListener('click', () => {
        confirmModal.classList.add('hidden');
        pendingAction = null;
    });

    confirmBtn.addEventListener('click', () => {
        if (!pendingAction) return;

        // Eksekusi aksi yang disimpan
        if (pendingAction.type === 'start') {
            executeStartMaintenance(pendingAction.sessionId);
        } else if (pendingAction.type === 'finish') {
            executeFinishMaintenance(pendingAction.sessionId);
        } else if (pendingAction.type === 'cancel_plan') {
            executeCancelPlan(pendingAction.sessionId);
        }

        confirmModal.classList.add('hidden');
    });

    // --- 1. BUAT RENCANA ---
    const createPlanForm = document.getElementById('create-plan-form');
    if (createPlanForm) {
        createPlanForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const title = document.getElementById('plan-title').value;
            const desc = document.getElementById('plan-desc').value;

            fetch(API_URL, {
                method: 'POST',
                body: JSON.stringify({
                    action: 'create_plan',
                    title,
                    description: desc
                })
            }).then(r => r.json()).then(data => {
                if (data.success) location.reload();
                else showAlert(data.message, 'error');
            });
        });
    }

    // --- 2. TAMBAH TUGAS ---
    const addTaskForm = document.getElementById('add-task-form');
    if (addTaskForm) {
        addTaskForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const session_id = document.getElementById('sess-id').value;
            const task_name = document.getElementById('task-name').value;
            const pic = document.getElementById('task-pic').value;

            fetch(API_URL, {
                method: 'POST',
                body: JSON.stringify({
                    action: 'add_task',
                    session_id,
                    task_name,
                    pic
                })
            }).then(r => r.json()).then(data => {
                if (data.success) location.reload();
                else showAlert(data.message, 'error');
            });
        });
    }

    // --- 3. TOGGLE TUGAS ---
    window.toggleTask = function(id) {
        const text = document.getElementById('text-' + id);
        const isChecked = event.target.checked;
        if (isChecked) text.classList.add('text-gray-400', 'line-through');
        else text.classList.remove('text-gray-400', 'line-through');
        fetch(API_URL, {
            method: 'POST',
            body: JSON.stringify({
                action: 'toggle_task',
                task_id: id,
                status: isChecked ? 1 : 0
            })
        });
    }

    // --- 4. HAPUS TUGAS ---
    window.deleteTask = function(id) {
        if (!confirm('Hapus tugas ini?')) return;
        document.getElementById('row-' + id).remove();
        fetch(API_URL, {
            method: 'POST',
            body: JSON.stringify({
                action: 'delete_task',
                task_id: id
            })
        });
    }

    // --- 5. LOGIKA MULAI MAINTENANCE (ON) ---
    // Tahap A: Buka Modal
    window.startMaintenance = function(sessId) {
        // Setup Modal Tampilan Merah (Peringatan)
        confirmTitle.innerText = "Aktifkan Mode Maintenance?";
        confirmMessage.innerText = "Website akan TERKUNCI untuk publik. Hanya admin yang bisa login melalui Portal Darurat.";

        // Ubah warna icon & tombol jadi Merah
        iconContainer.className = "mx-auto flex items-center justify-center h-14 w-14 rounded-full mb-4 bg-red-100";
        iconSvg.className = "h-8 w-8 text-red-600";
        confirmBtn.className = "w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:text-sm";
        confirmBtn.innerText = "Ya, Kunci Website";

        // Simpan aksi
        pendingAction = {
            type: 'start',
            sessionId: sessId
        };
        confirmModal.classList.remove('hidden');
    }

    const pinText = document.getElementById('pin-text');

    // Tahap B: Eksekusi (Callback)
    function executeStartMaintenance(sessId) {
        const pin = Math.floor(100000 + Math.random() * 900000).toString();
        fetch(API_URL, {
            method: 'POST',
            body: JSON.stringify({
                action: 'start_maintenance',
                session_id: sessId,
                pin: pin
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                document.getElementById('pin-text').innerText = pin;
                document.getElementById('pin-modal').classList.remove('hidden');
            } else showAlert('Gagal memulai.', 'error');
        });
    }

    // --- LOGIKA TOMBOL COPY ---
    const copyBtn = document.getElementById('copy-pin-btn');
    // pinText sudah didefinisikan di atas (generated-pin-text)

    if (copyBtn && pinText) {
        copyBtn.addEventListener('click', function() {
            // Ambil hanya PIN-nya
            const pinCode = pinText.innerText;

            // Tambahkan kata-kata pengantar di sini
            const codeToCopy = `Kode PIN Maintenance SIMAK: ${pinCode}`;

            // Gunakan API clipboard browser modern
            navigator.clipboard.writeText(codeToCopy).then(() => {
                // Simpan konten asli tombol
                const originalContent = copyBtn.innerHTML;

                // Ubah tampilan tombol jadi hijau (Sukses)
                copyBtn.classList.remove('text-blue-600', 'bg-blue-50', 'border-blue-200');
                copyBtn.classList.add('text-green-600', 'bg-green-50', 'border-green-200');
                copyBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Tersalin!</span>
                `;

                // Kembalikan seperti semula setelah 2 detik
                setTimeout(() => {
                    copyBtn.innerHTML = originalContent;
                    copyBtn.classList.remove('text-green-600', 'bg-green-50', 'border-green-200');
                    copyBtn.classList.add('text-blue-600', 'bg-blue-50', 'border-blue-200');
                }, 2000);

            }).catch(err => {
                console.error('Gagal menyalin:', err);
                // Fallback manual jika API gagal
                alert('Gagal menyalin otomatis. Kode PIN: ' + codeToCopy);
            });
        });
    }

    // --- 6. LOGIKA SELESAI MAINTENANCE (OFF) ---
    // Tahap A: Buka Modal
    window.finishMaintenance = function(sessId) {
        // Cek tugas dulu
        const unchecked = document.querySelectorAll('input[type="checkbox"]:not(:checked)');
        let msg = "Website akan dibuka kembali untuk publik. Pastikan semua perbaikan sudah selesai.";
        let btnColor = "bg-green-600 hover:bg-green-700";
        let title = "Selesaikan Maintenance?";

        if (unchecked.length > 0) {
            title = "Tugas Belum Selesai!";
            msg = "PERINGATAN: Masih ada " + unchecked.length + " tugas yang belum dicentang. Apakah Anda yakin ingin memaksa selesai?";
            btnColor = "bg-yellow-600 hover:bg-yellow-700"; // Kuning peringatan
        }

        // Setup Modal Tampilan Hijau/Kuning
        confirmTitle.innerText = title;
        confirmMessage.innerText = msg;

        // Ubah warna icon & tombol
        const bgColor = unchecked.length > 0 ? "bg-yellow-100" : "bg-green-100";
        const iconColor = unchecked.length > 0 ? "text-yellow-600" : "text-green-600";

        iconContainer.className = `mx-auto flex items-center justify-center h-14 w-14 rounded-full mb-4 ${bgColor}`;
        iconSvg.className = `h-8 w-8 ${iconColor}`;
        confirmBtn.className = `w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none sm:text-sm ${btnColor}`;
        confirmBtn.innerText = "Ya, Buka Website";

        // Simpan aksi
        pendingAction = {
            type: 'finish',
            sessionId: sessId
        };
        confirmModal.classList.remove('hidden');
    }

    // Tahap B: Eksekusi (Callback)
    function executeFinishMaintenance(sessId) {
        fetch(API_URL, {
            method: 'POST',
            body: JSON.stringify({
                action: 'finish_maintenance',
                session_id: sessId
            })
        }).then(r => r.json()).then(data => {
            if (data.success) location.reload();
            else showAlert('Gagal menyelesaikan.', 'error');
        });
    }

    // C. Cancel Plan (BARU)
    window.cancelPlan = function(sessId) {
        confirmTitle.innerText = "Batalkan Rencana Ini?";
        confirmMessage.innerText = "Rencana akan diarsipkan sebagai 'Dibatalkan' dan Anda bisa membuat rencana baru untuk kebutuhan mendesak.";
        iconContainer.className = "mx-auto flex items-center justify-center h-14 w-14 rounded-full mb-4 bg-gray-100";
        iconSvg.className = "h-8 w-8 text-gray-600";
        confirmBtn.className = "w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-700 focus:outline-none sm:text-sm";
        confirmBtn.innerText = "Ya, Batalkan";

        pendingAction = {
            type: 'cancel_plan',
            sessionId: sessId
        };
        confirmModal.classList.remove('hidden');
    }

    function executeCancelPlan(sessId) {
        fetch(API_URL, {
            method: 'POST',
            body: JSON.stringify({
                action: 'cancel_plan',
                session_id: sessId
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                location.reload();
            } else {
                showAlert(data.message || 'Gagal membatalkan rencana.', 'error');
            }
        });
    }

    // --- 7. HISTORY DETAIL ---
    window.viewHistoryDetail = function(sessId) {
        const modal = document.getElementById('history-modal');
        const content = document.getElementById('hist-content');
        modal.classList.remove('hidden');
        content.innerHTML = 'Loading data...';

        fetch(API_URL, {
            method: 'POST',
            body: JSON.stringify({
                action: 'get_session_detail',
                session_id: sessId
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                document.getElementById('hist-title').innerText = data.session.title;
                document.getElementById('hist-meta').innerText = `Oleh: ${data.session.created_by_name} | Mulai: ${data.session.start_time}`;

                let html = '<ul class="space-y-2 border-t pt-4">';
                if (data.tasks.length === 0) html += '<li class="text-gray-500 italic">Tidak ada tugas tercatat.</li>';

                data.tasks.forEach(t => {
                    const status = t.is_completed == 1 ?
                        '<span class="text-green-600 font-bold">✓ Selesai</span>' :
                        '<span class="text-red-500 font-bold">✗ Belum</span>';
                    html += `
                        <li class="flex justify-between items-center bg-gray-50 p-3 rounded">
                            <div>
                                <p class="font-medium text-gray-800">${t.task_name}</p>
                                <p class="text-xs text-gray-500">PIC: ${t.pic}</p>
                            </div>
                            <div class="text-sm">${status}</div>
                        </li>
                    `;
                });
                html += '</ul>';
                content.innerHTML = html;
            }
        });
    }
</script>