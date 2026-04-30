<?php
// Pastikan session sudah ada
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $cp_user_id = $_SESSION['user_id'];
?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Cek apakah browser/perangkat mendukung biometrik
            if (window.PublicKeyCredential) {
                
                // Gunakan ID User untuk mengecek laci memori yang tepat
                const currentUserId = '<?php echo $cp_user_id; ?>';
                const storageKey = 'simak_cred_id_' + currentUserId;
                const localCredId = localStorage.getItem(storageKey);
                const hasIgnored = sessionStorage.getItem('ignore_passkey_warning');

                // JIKA PERANGKAT INI BELUM TERDAFTAR (Kosong) DAN BELUM DI-IGNORE HARI INI
                if (!localCredId && !hasIgnored) {
                    setTimeout(() => {
                        Swal.fire({
                            title: '⚡ Fast Login Tersedia!',
                            html: `
                                <div class='text-left text-sm text-gray-600'>
                                    <p class='mb-2'><b>Perangkat ini</b> belum didaftarkan untuk fitur Fast Login.</p>
                                    <p>Aktifkan Sidik Jari atau Face ID sekarang agar Anda bisa absen lebih cepat tanpa PIN!</p>
                                </div>
                            `,
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: '<i class="fa-solid fa-fingerprint"></i> Daftarkan Sekarang',
                            cancelButtonText: 'Nanti Saja',
                            confirmButtonColor: '#10b981', 
                            cancelButtonColor: '#9ca3af',  
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Arahkan ke halaman profil (URL relatif terhadap dashboard yang sedang dibuka)
                                window.location.href = '?page=profile/index#passkey-section';
                            } else {
                                // Jangan ganggu lagi selama tab browser ini belum ditutup
                                sessionStorage.setItem('ignore_passkey_warning', 'true');
                            }
                        });
                    }, 1500); // Delay agar tidak menabrak animasi loading dashboard
                }
            }
        });
    </script>
<?php
}
?>