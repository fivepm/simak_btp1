<?php
// Persiapan Variabel PHP
$pk_user_id = $_SESSION['user_id'];
$pk_role = $_SESSION['user_role'];
$tipe_passkey = $pk_role;

// Ambil semua data passkey milik user ini, pastikan mengambil credential_id juga
$stmt_pk = $conn->prepare("SELECT id, credential_id, nama_perangkat, last_used_at, created_at FROM user_passkeys WHERE user_id = ? AND tipe_user = ? ORDER BY created_at DESC");
$stmt_pk->bind_param("ss", $pk_user_id, $tipe_passkey);
$stmt_pk->execute();
$res_pk = $stmt_pk->get_result();

$passkeys = [];
while ($row = $res_pk->fetch_assoc()) {
    $passkeys[] = $row;
}
$total_passkeys = count($passkeys);
$stmt_pk->close();

// Path dinamis
$fetch_path = isset($webauthn_path) ? $webauthn_path : '../auth/webauthn/';
?>

<!-- CARD KEAMANAN BIOMETRIK (FAST LOGIN) -->
<div id="passkey-section" class="bg-white rounded-xl shadow-md overflow-hidden mt-6 mb-6 transition-all duration-700">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
        <h3 class="font-bold text-gray-800"><i class="fa-solid fa-shield-halved mr-2 text-emerald-500"></i>Perangkat Terhubung (Fast Login)</h3>
        <?php if ($total_passkeys > 0): ?>
            <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2.5 py-1 rounded-full border border-emerald-200"><?php echo $total_passkeys; ?> Perangkat</span>
        <?php else: ?>
            <span class="bg-gray-100 text-gray-500 text-[10px] font-bold px-2.5 py-1 rounded-full border border-gray-200">Belum Aktif</span>
        <?php endif; ?>
    </div>
    
    <div class="p-6">
        <div class="mb-5">
            <p class="text-sm text-gray-500">Daftarkan sensor sidik jari atau pemindai wajah atau PIN device di perangkat Anda untuk masuk ke SIMAK dengan lebih cepat dan aman.</p>
        </div>

        <!-- LIST PERANGKAT YANG SUDAH TERDAFTAR -->
        <?php if ($total_passkeys > 0): ?>
        <div class="bg-gray-50 rounded-lg border border-gray-200 p-3 mb-5 space-y-2">
            <?php foreach($passkeys as $pk): ?>
            <div class="flex items-center justify-between bg-white p-3 rounded-lg border border-gray-100 shadow-sm transition hover:border-emerald-200" id="passkey-item-<?php echo $pk['credential_id']; ?>">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 border border-emerald-100">
                        <i class="fa-solid fa-fingerprint text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-800 flex items-center gap-2">
                            <?php echo htmlspecialchars($pk['nama_perangkat']); ?>
                            <!-- Badge 'Perangkat Ini' akan disuntikkan via JS jika cocok -->
                            <span class="badge-perangkat-ini hidden px-2 py-0.5 text-[9px] font-bold bg-emerald-100 text-emerald-700 rounded-full">Perangkat Ini</span>
                        </p>
                        <p class="text-[10px] text-gray-400 mt-0.5">
                            <i class="fa-regular fa-clock mr-1"></i>Aktivitas terakhir: <?php echo $pk['last_used_at'] ? date('d M Y, H:i', strtotime($pk['last_used_at'])) : 'Belum digunakan'; ?>
                        </p>
                    </div>
                </div>
                
                <!-- TOMBOL HAPUS (Membawa data-cred-id untuk verifikasi JS) -->
                <button class="btn-hapus-passkey text-gray-400 hover:text-red-600 hover:bg-red-50 p-2.5 rounded-lg transition" data-id="<?php echo $pk['id']; ?>" data-name="<?php echo htmlspecialchars($pk['nama_perangkat']); ?>" data-cred-id="<?php echo $pk['credential_id']; ?>" title="Cabut Akses Perangkat Ini">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- KONTEM TOMBOL DAFTAR & KETERANGAN -->
        <div id="biometric-action-container">
            <!-- SECTION: TOMBOL TAMBAH (Default tampil, disembunyikan JS jika sudah terdaftar) -->
            <div id="biometric-add-section" class="flex flex-col sm:flex-row gap-3">
                <button id="btnDaftarBiometrik" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-4 rounded-xl shadow-md transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus"></i> Tambahkan Perangkat Ini
                </button>
            </div>

            <!-- SECTION: KETERANGAN SUDAH TERDAFTAR (Disembunyikan default, dimunculkan JS jika cocok) -->
            <div id="biometric-registered-section" class="hidden mt-2 p-4 bg-emerald-50 rounded-xl border border-emerald-200 flex items-center justify-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-200 text-emerald-700 flex items-center justify-center flex-shrink-0 shadow-inner">
                    <i class="fa-solid fa-check text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-emerald-800">Perangkat Ini Sudah Terdaftar</p>
                    <p class="text-xs text-emerald-600">Anda dapat menggunakan Fast Login (Sidik Jari/Wajah/PIN Device) pada perangkat ini.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS WEBAUTHN -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnDaftarBiometrik = document.getElementById('btnDaftarBiometrik');
    const btnHapusList = document.querySelectorAll('.btn-hapus-passkey');
    
    // Konfigurasi Deteksi Perangkat via LocalStorage
    const currentUserId = '<?php echo $pk_user_id; ?>';
    const storageKey = 'simak_cred_id_' + currentUserId;
    const localCredId = localStorage.getItem(storageKey);
    
    // Ambil daftar credential_id yang terdaftar dari PHP ke JS Array
    const registeredCreds = [
        <?php foreach($passkeys as $pk) echo "'" . $pk['credential_id'] . "',"; ?>
    ];

    let isThisDeviceRegistered = false;

    // Cek apakah perangkat ini memiliki kunci yang masih valid di Database
    if (localCredId && registeredCreds.includes(localCredId)) {
        isThisDeviceRegistered = true;
    } else {
        // Jika kuncinya sudah dihapus (misal dihapus dari HP lain), bersihkan memori laptop
        localStorage.removeItem(storageKey);
    }

    // Atur Tampilan berdasarkan status perangkat
    if (isThisDeviceRegistered) {
        document.getElementById('biometric-add-section').classList.add('hidden');
        document.getElementById('biometric-registered-section').classList.remove('hidden');
        
        // Munculkan badge "Perangkat Ini" di daftar list
        const currentListItem = document.getElementById('passkey-item-' + localCredId);
        if(currentListItem) {
            const badge = currentListItem.querySelector('.badge-perangkat-ini');
            if(badge) badge.classList.remove('hidden');
        }
    }

    // --- LOGIKA AUTO-FOCUS HIGHLIGHT DARI DASHBOARD ---
    if (window.location.hash === '#passkey-section') {
        const passkeySection = document.getElementById('passkey-section');
        const btnDaftar = document.getElementById('btnDaftarBiometrik');
        
        // Hanya jalankan animasi jika tombol daftar masih tersedia (belum daftar)
        if (passkeySection && !isThisDeviceRegistered) {
            // Scroll ke bagian passkey dengan mulus (diberi sedikit jeda agar halaman selesai dimuat)
            setTimeout(() => {
                passkeySection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Berikan Efek Glow (Nyala Hijau) & sedikit membesar pada Card
                passkeySection.classList.add('ring-4', 'ring-emerald-400', 'ring-offset-2', 'scale-[1.02]', 'relative', 'z-10');
                
                // Berikan Efek Denyut (Pulse) seolah memanggil pada tombol
                if (btnDaftar) {
                    btnDaftar.classList.add('animate-pulse', 'ring-4', 'ring-emerald-200');
                }
                
                // Matikan semua efek secara perlahan setelah 3.5 detik
                setTimeout(() => {
                    passkeySection.classList.remove('ring-4', 'ring-emerald-400', 'ring-offset-2', 'scale-[1.02]', 'relative', 'z-10');
                    if (btnDaftar) {
                        btnDaftar.classList.remove('animate-pulse', 'ring-4', 'ring-emerald-200');
                    }
                    
                    // Bersihkan hash dari URL agar tidak berkedip ulang jika user me-refresh halaman
                    history.replaceState(null, null, window.location.pathname + window.location.search);
                }, 3500);
            }, 600);
        }
    }

    // --- HELPER WEBAUTHN ---
    const bufferToBase64url = (buffer) => {
        const bytes = new Uint8Array(buffer);
        let str = '';
        for (const charCode of bytes) str += String.fromCharCode(charCode);
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    };
    
    const base64urlToBuffer = (base64url) => {
        const padding = '='.repeat((4 - base64url.length % 4) % 4);
        const base64 = (base64url + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
        return outputArray.buffer;
    };

    // --- PROSES DAFTAR ---
    if (btnDaftarBiometrik) {
        btnDaftarBiometrik.addEventListener('click', function() {
            if (!window.PublicKeyCredential) {
                Swal.fire('Tidak Didukung', 'Browser atau perangkat Anda tidak mendukung fitur biometrik.', 'error');
                return;
            }
            
            Swal.fire({
                title: 'Nama Perangkat',
                text: 'Beri nama untuk perangkat ini agar mudah dikenali di daftar.',
                input: 'text',
                inputPlaceholder: 'Misal: HP Samsung A56 5G, Laptop Asusku...',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Lanjut Scan <i class="fa-solid fa-arrow-right ml-1"></i>',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                inputValidator: (value) => {
                    if (!value || value.trim() === '') return 'Nama perangkat tidak boleh kosong!';
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const customDeviceName = result.value.trim();
                    if (typeof showLoading === "function") showLoading('Meminta akses sensor...');
                    
                    try {
                        const req = await fetch('<?php echo $fetch_path; ?>register_challenge.php');
                        const rawText = await req.text();
                        let res;
                        try { res = JSON.parse(rawText); } catch (e) { throw new Error("SERVER ERROR: " + rawText.substring(0, 100)); }
                        
                        if(!res.success) throw new Error(res.message);

                        const createOptions = {
                            publicKey: {
                                challenge: base64urlToBuffer(res.challenge),
                                rp: { name: "SIMAK", id: window.location.hostname },
                                user: { id: base64urlToBuffer(res.user.id), name: res.user.name, displayName: res.user.displayName },
                                pubKeyCredParams: [{ type: "public-key", alg: -7 }, { type: "public-key", alg: -257 }],
                                authenticatorSelection: { userVerification: "required" },
                                timeout: 60000,
                                attestation: "none"
                            }
                        };

                        if (typeof hideLoading === "function") hideLoading();
                        
                        const credential = await navigator.credentials.create(createOptions);
                        
                        if (typeof showLoading === "function") showLoading('Menyimpan sandi enkripsi...');

                        const attestationData = {
                            id: credential.id,
                            clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                            attestationObject: bufferToBase64url(credential.response.attestationObject),
                            deviceName: customDeviceName
                        };

                        const verifyReq = await fetch('<?php echo $fetch_path; ?>register_process.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(attestationData)
                        });
                        
                        const rawTextVerify = await verifyReq.text();
                        if (typeof hideLoading === "function") hideLoading();

                        let verifyRes;
                        try { verifyRes = JSON.parse(rawTextVerify); } catch (e) {
                            console.error("Error Mentah PHP:", rawTextVerify);
                            Swal.fire({title: 'SERVER ERROR PHP!', html: `<div class="text-left text-xs bg-gray-100 p-3 rounded font-mono text-red-600 border border-red-200 overflow-x-auto max-h-40">${rawTextVerify}</div>`, icon: 'error', width: '600px'});
                            return;
                        }

                        if(verifyRes.success) {
                            // SIMPAN ID KUNCI KE MEMORI BROWSER AGAR DIINGAT SEBAGAI "PERANGKAT INI"
                            localStorage.setItem(storageKey, credential.id);
                            Swal.fire('Berhasil!', verifyRes.message, 'success').then(() => window.location.reload());
                        } else {
                            Swal.fire('Pendaftaran Gagal!', verifyRes.message, 'error');
                        }

                    } catch (err) {
                        if (typeof hideLoading === "function") hideLoading();
                        if(err.name !== 'NotAllowedError' && err.name !== 'AbortError') {
                            Swal.fire('Gagal', err.message || 'Terjadi kesalahan sistem saat mendaftarkan sidik jari.', 'error');
                        }
                    }
                }
            });
        });
    }

    // --- PROSES HAPUS ---
    btnHapusList.forEach(btn => {
        btn.addEventListener('click', function() {
            const pkId = this.getAttribute('data-id');
            const pkName = this.getAttribute('data-name');
            const pkCredId = this.getAttribute('data-cred-id');

            Swal.fire({
                title: 'Cabut Akses?',
                html: `Anda akan mencabut akses Fast Login untuk perangkat:<br><b class="text-emerald-600">${pkName}</b>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: '<i class="fa-solid fa-trash-can"></i> Ya, Cabut Akses',
                cancelButtonText: 'Batal'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    if (typeof showLoading === "function") showLoading('Menghapus data...');
                    try {
                        const res = await fetch('<?php echo $fetch_path; ?>delete_passkey.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: pkId })
                        });
                        const data = await res.json();
                        if (typeof hideLoading === "function") hideLoading();
                        
                        if(data.success) {
                            // JIKA YANG DIHAPUS ADALAH PERANGKAT INI, BERSIHKAN MEMORI BROWSERNYA
                            if (pkCredId === localCredId) {
                                localStorage.removeItem(storageKey);
                            }
                            Swal.fire({
                                title: 'Akses Dicabut!',
                                html: `<p class="mb-3 text-sm text-gray-700">${data.message}</p>
                                       <div class="bg-blue-50 p-3 rounded-lg border border-blue-100 text-left">
                                         <p class="text-xs text-blue-800"><i class="fa-solid fa-circle-info mr-1"></i> <b>Catatan:</b> Riwayat kunci mungkin masih terlihat di pengaturan <i>Password Manager</i> perangkat Anda, namun kunci tersebut sudah mati dan tidak bisa lagi digunakan untuk masuk ke web ini.</p>
                                       </div>`,
                                icon: 'success'
                            }).then(() => window.location.reload());
                        } else {
                            Swal.fire('Gagal!', data.message, 'error');
                        }
                    } catch (err) {
                        if (typeof hideLoading === "function") hideLoading();
                        Swal.fire('Gagal!', 'Terjadi kesalahan sistem.', 'error');
                    }
                }
            });
        });
    });
});
</script>