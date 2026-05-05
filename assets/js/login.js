// --- HELPER BASE64 ---
function bufferToBase64url(buffer) {
    const bytes = new Uint8Array(buffer);
    let str = '';
    for (const charCode of bytes) str += String.fromCharCode(charCode);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

function base64urlToBuffer(base64url) {
    const padding = '='.repeat((4 - base64url.length % 4) % 4);
    const base64 = (base64url + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray.buffer;
}

document.addEventListener('DOMContentLoaded', function() {
    // --- SERVICE WORKER (PWA) ---
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    // --- DOM ELEMENTS ---
    const els = {
        biometricContainer: document.getElementById('biometric-container'),
        biometricDivider: document.getElementById('biometric-divider'),
        startBtn: document.getElementById('start-scan-btn'),
        stopBtn: document.getElementById('stop-scan-btn'),
        fileInput: document.getElementById('qr-input-file'),
        scannerBox: document.getElementById('scanner-container'),
        errorBox: document.getElementById('error-message'),
        errorText: document.getElementById('error-text'),
        loader: document.getElementById('loadingOverlay'),

        // PIN Modal
        pinModal: document.getElementById('pinModal'),
        pinInput: document.getElementById('pin-input-field'),
        pinSubmit: document.getElementById('submit-pin-btn'),
        pinCancel: document.getElementById('cancel-pin-btn'),
        pinError: document.getElementById('pin-error'),
        pinUser: document.getElementById('pin-user-name'),

        // Welcome
        welcomeOverlay: document.getElementById('welcome-overlay'),
        welcomeName: document.getElementById('welcome-user-name'),
        welcomeRole: document.getElementById('welcome-user-role'),
        loginBox: document.getElementById('login-box')
    };

    // --- HELPER UI FUNCTIONS ---
    const showError = (msg) => {
        els.errorText.textContent = msg;
        els.errorBox.classList.remove('hidden');
        setTimeout(() => els.errorBox.classList.add('hidden'), 5000);
    };
    const hideError = () => els.errorBox.classList.add('hidden');
    const showLoader = () => els.loader.classList.remove('hidden');
    const hideLoader = () => els.loader.classList.add('hidden');

    // --- LOGIKA WEBAUTHN BERDASARKAN STATUS PERANGKAT ---
    if (window.PublicKeyCredential && els.biometricContainer) {
        let passkeyCount = 0;
        for (let i = 0; i < localStorage.length; i++) {
            if (localStorage.key(i) && localStorage.key(i).startsWith('simak_cred_id_')) {
                passkeyCount++;
            }
        }

        els.biometricDivider.classList.remove('hidden');
        els.biometricContainer.classList.remove('hidden');
        els.biometricContainer.classList.add('flex');

        if (passkeyCount === 0) {
            els.biometricContainer.innerHTML = `
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-center shadow-sm">
                    <div class="w-10 h-10 mx-auto bg-gray-200 rounded-full flex items-center justify-center text-gray-400 mb-2 shadow-inner">
                        <i class="fa-solid fa-fingerprint text-lg"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-600">Fast Login Belum Aktif</p>
                    <p class="text-[10px] text-gray-500 mt-1 leading-relaxed">Gunakan Scan QR atau ketik PIN untuk masuk pertama kali, lalu daftarkan perangkat ini di menu Profil.</p>
                </div>
            `;
        } else if (passkeyCount === 1) {
            els.biometricContainer.innerHTML = `
                <button id="biometric-login-btn" class="group w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-emerald-200 transition-all flex items-center justify-center gap-3 relative overflow-hidden">
                    <div class="absolute inset-0 w-full h-full bg-white/10 scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></div>
                    <i class="fa-solid fa-fingerprint text-xl"></i>
                    <span>Masuk dengan Fast Login</span>
                </button>
            `;
        } else {
            els.biometricContainer.innerHTML = `
                <button id="biometric-login-btn" class="group w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg shadow-emerald-200 transition-all flex flex-col items-center justify-center relative overflow-hidden">
                    <div class="absolute inset-0 w-full h-full bg-white/10 scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <i class="fa-solid fa-users text-lg"></i>
                        <span>Fast Login (${passkeyCount} Akun)</span>
                    </div>
                    <span class="text-[9px] text-emerald-200 font-normal"><i class="fa-solid fa-circle-info mr-1"></i>Pilih akun pada pop-up sistem</span>
                </button>
            `;
        }

        const bioBtn = document.getElementById('biometric-login-btn');
        if (bioBtn) bioBtn.addEventListener('click', handleBiometricLogin);
    }

    // --- FUNGSI EKSEKUSI WEBAUTHN ---
    async function handleBiometricLogin() {
        hideError();
        showLoader();

        try {
            const allowCredentials = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('simak_cred_id_')) {
                    const credId = localStorage.getItem(key);
                    allowCredentials.push({
                        type: 'public-key',
                        id: base64urlToBuffer(credId),
                        transports: ['internal'] // internal = biometrik perangkat ini
                    });
                }
            }
            
            const challengeResponse = await fetch('auth/webauthn/get_login_challenge.php');
            const challengeData = await challengeResponse.json();

            if (!challengeData.success) throw new Error(challengeData.message || "Gagal mendapatkan challenge keamanan.");

            const publicKeyCredentialRequestOptions = {
                challenge: base64urlToBuffer(challengeData.challenge),
                rpId: challengeData.rpId,
                timeout: 60000,
                userVerification: "required",
                allowCredentials: allowCredentials
            };

            hideLoader(); 
            const assertion = await navigator.credentials.get({ publicKey: publicKeyCredentialRequestOptions });
            showLoader(); 

            const authData = {
                id: assertion.id,
                clientDataJSON: bufferToBase64url(assertion.response.clientDataJSON),
                authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
                signature: bufferToBase64url(assertion.response.signature),
                userHandle: assertion.response.userHandle ? bufferToBase64url(assertion.response.userHandle) : null
            };

            const verifyResponse = await fetch('auth/webauthn/verify_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(authData)
            });

            const verifyResult = await verifyResponse.json();
            hideLoader();

            if (verifyResult.success) {
                showWelcomeAnimation(verifyResult.nama, verifyResult.tampilan_role, verifyResult.redirect_url);
            } else {
                showError(verifyResult.message || 'Gagal memverifikasi sidik jari/wajah.');
            }

        } catch (error) {
            hideLoader();
            console.error(error);
            if (error.name !== 'NotAllowedError' && error.name !== 'AbortError') {
                showError('Proses login biometrik dibatalkan atau terjadi kesalahan.');
            }
        }
    }

    // --- LOGIN QR + PIN PROCESS ---
    let currentBarcode = null;
    let html5QrCode = new Html5Qrcode("qr-reader");

    async function processLogin(data) {
        hideError();
        if (!data.pin) showLoader();

        try {
            const response = await fetch('auth/login_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new Error("Respon server tidak valid.");
            }

            const result = await response.json();
            hideLoader();

            if (result.success) {
                if (result.require_pin) {
                    currentBarcode = data.barcode;
                    els.pinUser.textContent = result.nama;
                    els.pinModal.classList.remove('hidden');
                    els.pinInput.value = '';
                    els.pinError.classList.add('hidden');
                    setTimeout(() => els.pinInput.focus(), 100);
                } else {
                    els.pinModal.classList.add('hidden');
                    showWelcomeAnimation(result.nama, result.tampilan_role, result.redirect_url);
                }
            } else {
                if (data.pin) {
                    els.pinError.textContent = result.message || 'PIN Salah';
                    els.pinError.classList.remove('hidden');
                    els.pinInput.value = '';
                    els.pinInput.focus();
                    els.pinModal.firstElementChild.classList.add('animate-shake');
                    setTimeout(() => els.pinModal.firstElementChild.classList.remove('animate-shake'), 500);
                } else {
                    showError(result.message || 'Login gagal.');
                }
            }
        } catch (error) {
            hideLoader();
            console.error(error);
            if (data.pin) {
                els.pinError.textContent = "Gagal terhubung ke server.";
                els.pinError.classList.remove('hidden');
            } else {
                showError('Terjadi kesalahan koneksi.');
            }
        }
    }

    // --- PIN LOGIC ---
    if(els.pinSubmit) {
        els.pinSubmit.addEventListener('click', () => {
            const pin = els.pinInput.value;
            if (pin.length < 6) {
                els.pinError.textContent = "PIN harus 6 digit.";
                els.pinError.classList.remove('hidden');
                return;
            }
            processLogin({ barcode: currentBarcode, pin: pin });
        });
    }

    if(els.pinInput) {
        els.pinInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') els.pinSubmit.click();
        });
    }

    if(els.pinCancel) {
        els.pinCancel.addEventListener('click', () => {
            els.pinModal.classList.add('hidden');
            currentBarcode = null;
            els.pinInput.value = '';
        });
    }

    // --- ANIMASI WELCOME ---
    function showWelcomeAnimation(name, role, url) {
        els.loginBox.style.transform = 'scale(0.9)';
        els.loginBox.style.opacity = '0';

        els.welcomeName.textContent = name;
        els.welcomeRole.textContent = role;
        els.welcomeOverlay.classList.add('show');

        setTimeout(() => {
            window.location.href = url;
        }, 1800);
    }

    // --- SCANNER LOGIC ---
    const onScanSuccess = (decodedText) => {
        try { html5QrCode.stop(); } catch (err) {}
        els.scannerBox.classList.add('hidden');
        processLogin({ barcode: decodedText });
    };

    if(els.startBtn) {
        els.startBtn.addEventListener('click', () => {
            hideError();
            els.scannerBox.classList.remove('hidden');
            const config = { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 };
            html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess)
                .catch(err => {
                    showError("Tidak dapat mengakses kamera.");
                    els.scannerBox.classList.add('hidden');
                });
        });
    }

    if(els.stopBtn) {
        els.stopBtn.addEventListener('click', () => {
            try { html5QrCode.stop(); } catch (e) {}
            els.scannerBox.classList.add('hidden');
        });
    }

    if(els.fileInput) {
        els.fileInput.addEventListener('change', e => {
            if (e.target.files.length === 0) return;
            hideError();
            showLoader();
            html5QrCode.scanFile(e.target.files[0], true)
                .then(text => {
                    hideLoader();
                    onScanSuccess(text);
                })
                .catch(err => {
                    hideLoader();
                    showError('Barcode tidak terbaca dari gambar ini.');
                });
        });
    }
});