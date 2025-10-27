<?php
// File ini adalah endpoint AJAX murni.
// Pastikan file ini di-include oleh index.php Anda, 
// sehingga $conn dan $_SESSION sudah tersedia.
include '../../../config/config.php';

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['error' => 'Koneksi database tidak ditemukan.']);
    exit;
}

// ==========================================================
// ▼▼▼ ARRAY BARU UNTUK LOG ▼▼▼
// ==========================================================
$debug_logs = []; // Array untuk menyimpan log
// ==========================================================

// ==========================================================
// ▼▼▼ HELPER BARU: Fungsi Pemanggil API Gemini (Log ke Array) ▼▼▼
// ==========================================================
/**
 * Memanggil Gemini API untuk menganalisis data dan mengembalikan JSON.
 * @param string $prompt_data Teks ringkasan data untuk dianalisis.
 * @param array &$logs Referensi ke array log global.
 * @return array ['catatan_kondisi' => '', 'rekomendasi_tindakan' => '']
 */
function callGeminiAPI($prompt_data, &$logs)
{ // Terima $logs by reference
    $logs[] = "[PHP] Gemini API Call - Prompt: " . $prompt_data; // Ganti error_log

    $api_key = $_ENV['GEMINI_TOKEN'];

    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=' . $api_key; // Key akan di-inject oleh environment

    // System prompt yang kuat untuk hasil yang terstruktur
    $system_prompt = "Anda adalah asisten admin monitoring akademik (SIMAK) yang cerdas dan lugas. 
Tugas Anda adalah menganalisis data harian dan menulis dua bagian: 'Catatan Kondisi' dan 'Rekomendasi Tindakan'.
Gunakan bahasa Indonesia yang formal, singkat, dan profesional. Fokus pada poin-poin penting.
Selalu berikan respons dalam format JSON yang diminta.";

    // Skema JSON yang kita inginkan dari AI
    $json_schema = [
        'type' => 'OBJECT',
        'properties' => [
            'catatan_kondisi' => [
                'type' => 'STRING',
                'description' => 'Ringkasan 2-3 kalimat tentang kondisi hari ini. Sebutkan poin paling menonjol (misal: jumlah alpa, jadwal terlewat).'
            ],
            'rekomendasi_tindakan' => [
                'type' => 'STRING',
                'description' => 'Poin-poin tindakan (langkah konkret) yang perlu diambil. Jika ada alpa, rekomendasikan follow-up. Jika ada jadwal terlewat, rekomendasikan pengingat.'
            ]
        ],
        'required' => ['catatan_kondisi', 'rekomendasi_tindakan']
    ];

    // Payload lengkap
    $payload = [
        'systemInstruction' => [
            'parts' => [['text' => $system_prompt]]
        ],
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt_data]]]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => $json_schema,
            'temperature' => 0.5 // Sedikit kreatif tapi tetap fokus
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    // Timeout untuk koneksi dan eksekusi (dalam detik)
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Beri waktu 30 detik untuk AI memproses

    // Nonaktifkan verifikasi SSL jika di localhost (jangan gunakan di produksi)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Ambil status code HTTP
    $curl_error = curl_error($ch);
    curl_close($ch);

    // TAMBAHAN LOGGING: Catat hasil cURL
    $logs[] = "[PHP] Gemini API Call - cURL HTTP Status: " . $http_code; // Ganti error_log
    $logs[] = "[PHP] Gemini API Call - cURL Error: " . ($curl_error ?: 'None'); // Ganti error_log
    // Log response body hanya jika perlu (bisa sangat panjang)
    // $logs[] = "[PHP] Gemini API Call - Raw Response Body: " . $response_body; 


    if ($curl_error) {
        $logs[] = "[PHP] Gemini API Call - FALLBACK triggered due to cURL error."; // Ganti error_log
        return ['catatan_kondisi' => 'Gagal terhubung ke API: ' . $curl_error, 'rekomendasi_tindakan' => ''];
    }

    // Periksa jika status code bukan 200 OK
    if ($http_code !== 200) {
        $logs[] = "[PHP] Gemini API Call - FALLBACK triggered due to non-200 HTTP status: " . $http_code; // Ganti error_log
        return ['catatan_kondisi' => 'Gagal menganalisis data. (API Status: ' . $http_code . ')', 'rekomendasi_tindakan' => 'Mohon isi manual.'];
    }

    $response_data = json_decode($response_body, true);

    // Ambil bagian teks (yang berisi JSON) dari respons
    $gemini_text_output = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($gemini_text_output) {
        $logs[] = "[PHP] Gemini API Call - Text Output from AI: " . $gemini_text_output; // Ganti error_log

        // Dekode JSON yang diberikan oleh Gemini
        $parsed_text = json_decode($gemini_text_output, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $logs[] = "[PHP] Gemini API Call - JSON Parsed Successfully."; // Ganti error_log
            return [
                'catatan_kondisi' => $parsed_text['catatan_kondisi'] ?? 'AI Gagal memformat catatan.',
                'rekomendasi_tindakan' => $parsed_text['rekomendasi_tindakan'] ?? 'AI Gagal memformat rekomendasi.'
            ];
        } else {
            $logs[] = "[PHP] Gemini API Call - FALLBACK triggered due to JSON decode error: " . json_last_error_msg(); // Ganti error_log
            return [
                'catatan_kondisi' => 'Gagal memproses respons AI (JSON Error: ' . json_last_error_msg() . ')',
                'rekomendasi_tindakan' => 'Mohon isi manual.'
            ];
        }
    } else {
        $logs[] = "[PHP] Gemini API Call - FALLBACK triggered because expected text part was not found in API response."; // Ganti error_log
    }

    // Fallback jika terjadi kesalahan tak terduga
    $logs[] = "[PHP] Gemini API Call - FALLBACK triggered due to unexpected error."; // Ganti error_log
    return [
        'catatan_kondisi' => 'Gagal menganalisis data. (AI Error)',
        'rekomendasi_tindakan' => 'Mohon isi manual.'
    ];
}
// ==========================================================
// ▲▲▲ AKHIR FUNGSI HELPER API ▼▼▲
// ==========================================================


// Ambil tanggal dari parameter GET
$tanggal_laporan = $_GET['tanggal'] ?? null;
if (empty($tanggal_laporan)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parameter tanggal tidak ditemukan.', 'debug_logs' => ["[PHP] Error: Parameter tanggal kosong."]]); // Kirim log error
    exit;
}

// ==========================================================
// ▼▼▼ FITUR BARU: Validasi Tanggal Laporan ▼▼▼
// ==========================================================
// Cek apakah sudah ada laporan FINAL untuk tanggal ini
try {
    $stmt_cek = $conn->prepare("SELECT id FROM laporan_harian WHERE tanggal_laporan = ? AND status_laporan = 'Final'");
    $stmt_cek->bind_param("s", $tanggal_laporan);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();

    if ($result_cek->num_rows > 0) {
        // HTTP 409 Conflict: Menandakan duplikasi data
        http_response_code(409);
        echo json_encode(['error' => 'Laporan FINAL untuk tanggal ini sudah ada dan tidak dapat dibuat ulang.', 'debug_logs' => ["[PHP] Validasi Gagal: Laporan FINAL sudah ada untuk tanggal $tanggal_laporan."]]); // Kirim log error
        $stmt_cek->close();
        exit;
    }
    $stmt_cek->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal memvalidasi tanggal: ' . $e->getMessage(), 'debug_logs' => ["[PHP] Exception saat validasi tanggal: " . $e->getMessage()]]); // Kirim log error
    exit;
}
// ==========================================================
// ▲▲▲ AKHIR FITUR BARU ▲▲▲
// ==========================================================


// Siapkan struktur data JSON
$response = [
    'global' => [
        'tanggal_laporan' => $tanggal_laporan,
        'total_jadwal_hari_ini' => 0,
        'total_presensi_terisi' => 0,
        'total_jurnal_terisi' => 0,
        'jadwal_terlewat' => 0,
        'total_siswa_hadir' => 0,
        'total_siswa_izin' => 0,
        'total_siswa_sakit' => 0,
        'total_siswa_alpa' => 0,
    ],
    'rincian_per_kelompok' => [],
    'daftar_alpa' => [],
    'catatan_kondisi_ai' => '',
    'rekomendasi_tindakan_ai' => '',
    // ==========================================================
    // ▼▼▼ KEY BARU UNTUK LOG ▼▼▼
    // ==========================================================
    'debug_logs' => [] // Akan diisi nanti
    // ==========================================================
];

// ==========================================================
// ▼▼▼ TAMBAHAN: Variabel untuk prompt AI ▼▼▼
// ==========================================================
$problem_alpa_classes = [];
$problem_terlewat_classes = [];
// ==========================================================

try {
    // ==========================================================
    // ▼▼▼ PERUBAHAN BESAR: Kueri Rincian per Kelas ▼▼▼
    // ==========================================================

    $sql_rincian = "
        SELECT 
            p.kelompok,
            p.kelas,
            
            COUNT(DISTINCT jp.id) AS total_jadwal,
            COUNT(DISTINCT CASE WHEN rp.status_kehadiran IS NOT NULL AND rp.status_kehadiran != '' THEN jp.id END) AS jadwal_presensi_terisi,
            COUNT(DISTINCT CASE WHEN jp.pengajar IS NOT NULL AND jp.pengajar != '' THEN jp.id END) AS jadwal_jurnal_terisi,
            
            SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) AS alpa
        FROM 
            (SELECT DISTINCT kelompok, kelas FROM peserta WHERE kelompok IS NOT NULL AND kelas IS NOT NULL) p
        LEFT JOIN 
            jadwal_presensi jp ON p.kelompok = jp.kelompok COLLATE utf8mb4_unicode_ci -- Tambah COLLATE
                                AND p.kelas = jp.kelas COLLATE utf8mb4_unicode_ci -- Tambah COLLATE
                                AND jp.tanggal = ?
        LEFT JOIN 
            rekap_presensi rp ON jp.id = rp.jadwal_id
        WHERE
            p.kelompok IS NOT NULL AND p.kelompok != '' AND p.kelas IS NOT NULL AND p.kelas != ''
        GROUP BY 
            p.kelompok, p.kelas
        ORDER BY
            p.kelompok, FIELD(p.kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah');
    ";

    $stmt_rincian = $conn->prepare($sql_rincian);
    if (!$stmt_rincian) {
        throw new Exception("Gagal prepare statement sql_rincian: " . $conn->error);
    }
    $stmt_rincian->bind_param("s", $tanggal_laporan);
    $stmt_rincian->execute();
    $result_rincian = $stmt_rincian->get_result();
    // ==========================================================
    // ▲▲▲ AKHIR PERUBAHAN KUERI ▲▲▲
    // ==========================================================

    if ($result_rincian) {
        while ($row = $result_rincian->fetch_assoc()) {
            $kelompok = $row['kelompok'];
            $kelas = $row['kelas'];

            // Inisialisasi kelompok jika belum ada
            if (!isset($response['rincian_per_kelompok'][$kelompok])) {
                $response['rincian_per_kelompok'][$kelompok] = [];
            }

            $jadwal_terlewat_kelas = $row['total_jadwal'] - $row['jadwal_presensi_terisi'];

            // Masukkan data rincian
            $response['rincian_per_kelompok'][$kelompok][$kelas] = [
                'total_jadwal' => (int)$row['total_jadwal'],
                'hadir' => (int)$row['hadir'],
                'izin' => (int)$row['izin'],
                'sakit' => (int)$row['sakit'],
                'alpa' => (int)$row['alpa'],
                'jadwal_terisi' => (int)$row['jadwal_presensi_terisi'],
                'jurnal_terisi' => (int)$row['jadwal_jurnal_terisi']
            ];

            // ==========================================================
            // ▼▼▼ TAMBAHAN: Kumpulkan data masalah untuk AI ▼▼▼
            // ==========================================================
            if ((int)$row['alpa'] > 0) {
                $problem_alpa_classes[] = "$kelompok $kelas (" . $row['alpa'] . " siswa)";
            }
            if ($jadwal_terlewat_kelas > 0) {
                $problem_terlewat_classes[] = "$kelompok $kelas (" . $jadwal_terlewat_kelas . " jadwal)";
            }
            // ==========================================================

            // Akumulasi data ke Global
            $response['global']['total_jadwal_hari_ini'] += (int)$row['total_jadwal'];
            $response['global']['total_presensi_terisi'] += (int)$row['jadwal_presensi_terisi'];
            $response['global']['total_jurnal_terisi'] += (int)$row['jadwal_jurnal_terisi'];
            $response['global']['jadwal_terlewat'] += $jadwal_terlewat_kelas;
            $response['global']['total_siswa_hadir'] += (int)$row['hadir'];
            $response['global']['total_siswa_izin'] += (int)$row['izin'];
            $response['global']['total_siswa_sakit'] += (int)$row['sakit'];
            $response['global']['total_siswa_alpa'] += (int)$row['alpa'];
        }
    }
    $stmt_rincian->close();

    // ==========================================================
    // ▼▼▼ TAMBAHAN: Kueri Daftar Siswa Alpa ▼▼▼
    // ==========================================================

    $sql_alpa = "
        SELECT 
            p.nama_lengkap, p.kelompok, p.kelas, p.nama_orang_tua, p.nomor_hp_orang_tua
        FROM rekap_presensi rp
        JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
        JOIN peserta p ON rp.peserta_id = p.id
        WHERE 
            jp.tanggal = ? AND rp.status_kehadiran = 'Alpa'
        ORDER BY
            p.kelompok COLLATE utf8mb4_unicode_ci, -- Tambah COLLATE
            p.kelas COLLATE utf8mb4_unicode_ci, -- Tambah COLLATE
            p.nama_lengkap COLLATE utf8mb4_unicode_ci -- Tambah COLLATE
    ";

    $stmt_alpa = $conn->prepare($sql_alpa);
    if (!$stmt_alpa) {
        throw new Exception("Gagal prepare statement sql_alpa: " . $conn->error);
    }
    $stmt_alpa->bind_param("s", $tanggal_laporan);
    $stmt_alpa->execute();
    $result_alpa = $stmt_alpa->get_result();

    if ($result_alpa) {
        while ($row_alpa = $result_alpa->fetch_assoc()) {
            $response['daftar_alpa'][] = $row_alpa;
        }
    }
    $stmt_alpa->close();
    // ==========================================================
    // ▲▲▲ AKHIR TAMBAHAN ▲▲▲
    // ==========================================================

    // ==========================================================
    // ▼▼▼ TAMBAHAN: Panggil API Gemini ▼▼▼
    // ==========================================================

    // Buat prompt ringkasan untuk AI
    $prompt_text = "Tolong analisis data laporan harian SIMAK berikut:\n";
    $prompt_text .= "- Tanggal Laporan: " . $tanggal_laporan . "\n";
    $prompt_text .= "- Total Jadwal: " . $response['global']['total_jadwal_hari_ini'] . "\n";
    $prompt_text .= "- Jadwal Terlewat (Presensi Kosong): " . $response['global']['jadwal_terlewat'] . "\n";
    $prompt_text .= "- Total Siswa Alpa: " . $response['global']['total_siswa_alpa'] . "\n";
    $prompt_text .= "- Total Siswa Izin/Sakit: " . ($response['global']['total_siswa_izin'] + $response['global']['total_siswa_sakit']) . "\n";

    if (!empty($problem_alpa_classes)) {
        $prompt_text .= "- Detail Kelas Alpa: " . implode(', ', $problem_alpa_classes) . ".\n";
    }
    if (!empty($problem_terlewat_classes)) {
        $prompt_text .= "- Detail Jadwal Terlewat: " . implode(', ', $problem_terlewat_classes) . ".\n";
    }
    if ($response['global']['total_jadwal_hari_ini'] == 0) {
        $prompt_text = "Tidak ada jadwal KBM pada tanggal " . $tanggal_laporan . ". Buat catatan singkat bahwa hari ini libur atau tidak ada KBM.";
    }

    // Panggil API (passing array log by reference)
    $generated_text = callGeminiAPI($prompt_text, $debug_logs); // Kirim $debug_logs

    // Masukkan hasil AI ke respons JSON
    $response['catatan_kondisi_ai'] = $generated_text['catatan_kondisi'] ?? 'AI Gagal memproses.';
    $response['rekomendasi_tindakan_ai'] = $generated_text['rekomendasi_tindakan'] ?? 'Mohon isi manual.';

    // ==========================================================
    // ▲▲▲ AKHIR PANGGILAN API ▲▲▲
    // ==========================================================

    // ==========================================================
    // ▼▼▼ TAMBAHAN: Masukkan array log ke response ▼▼▼
    // ==========================================================
    $response['debug_logs'] = $debug_logs;
    // ==========================================================

    // Kirim response sebagai JSON
    header('Content-Type: application/json');
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    // TAMBAHAN LOGGING: Catat error exception ke array log
    $debug_logs[] = "[PHP] AJAX Stats Error Exception: " . $e->getMessage();
    echo json_encode(['error' => 'Terjadi kesalahan internal: ' . $e->getMessage(), 'debug_logs' => $debug_logs]); // Sertakan log di error response
}

// Pastikan untuk exit setelah echo JSON
exit;
