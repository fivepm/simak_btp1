<?php
include '../../../config/config.php';
// Mirip ajax_get_stats_harian, tapi query agregat mingguan
if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['error' => 'Koneksi DB tidak ada.']);
    exit;
}
$debug_logs = [];

// ==========================================================
// ▼▼▼ FUNGSI LENGKAP Pemanggil API Gemini (Log ke Array) ▼▼▼
// ==========================================================
/**
 * Memanggil Gemini API untuk menganalisis data dan mengembalikan JSON.
 * @param string $prompt_data Teks ringkasan data untuk dianalisis.
 * @param array &$logs Referensi ke array log global.
 * @return array ['catatan_kondisi' => '', 'rekomendasi_tindakan' => '']
 */
function callGeminiAPI($prompt_data, &$logs)
{ // Terima $logs by reference
    $logs[] = "[PHP] Gemini API Call - Prompt: " . $prompt_data;

    // API Key Handling (Biarkan kosong jika pakai environment)
    $api_key = $_ENV['GEMINI_TOKEN']; // <-- Ganti ini jika pakai key manual
    if (empty($api_key)) {
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=';
    } else {
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=' . $api_key;
    }

    // System prompt
    $system_prompt = "Anda adalah asisten admin monitoring akademik (SIMAK) yang cerdas dan lugas. Tugas Anda adalah menganalisis data harian dan menulis dua bagian: 'Catatan Kondisi' dan 'Rekomendasi Tindakan'. Gunakan bahasa Indonesia yang formal, singkat, dan profesional. Fokus pada poin-poin penting. Selalu berikan respons dalam format JSON yang diminta.";

    // Skema JSON
    $json_schema = [
        'type' => 'OBJECT',
        'properties' => [
            'catatan_kondisi' => [
                'type' => 'STRING',
                'description' => 'Ringkasan 2-3 kalimat tentang kondisi minggu ini. Sebutkan poin paling menonjol (misal: jumlah alpa, jadwal terlewat).' // Deskripsi disesuaikan
            ],
            'rekomendasi_tindakan' => [
                'type' => 'STRING',
                'description' => 'Poin-poin tindakan (langkah konkret) mingguan yang perlu diambil. Jika ada alpa berulang, rekomendasikan follow-up intensif.' // Deskripsi disesuaikan
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
            'temperature' => 0.5
        ]
    ];

    // Persiapan cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Hanya jika perlu di localhost

    // Eksekusi cURL
    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Logging Hasil cURL
    $logs[] = "[PHP] Gemini API Call - cURL HTTP Status: " . $http_code;
    $logs[] = "[PHP] Gemini API Call - cURL Error: " . ($curl_error ?: 'None');

    // Handle Error cURL
    if ($curl_error) {
        $logs[] = "[PHP] Gemini API Call - FALLBACK triggered due to cURL error.";
        return ['catatan_kondisi' => 'Gagal terhubung ke API: ' . $curl_error, 'rekomendasi_tindakan' => ''];
    }
    // Handle Error HTTP Status
    if ($http_code !== 200) {
        $logs[] = "[PHP] Gemini API Call - FALLBACK triggered due to non-200 HTTP status: " . $http_code;
        return ['catatan_kondisi' => 'Gagal menganalisis data. (API Status: ' . $http_code . ')', 'rekomendasi_tindakan' => 'Mohon isi manual.'];
    }

    // Proses Respons Sukses
    $response_data = json_decode($response_body, true);
    $gemini_text_output = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($gemini_text_output) {
        $logs[] = "[PHP] Gemini API Call - Text Output from AI: " . $gemini_text_output;
        $parsed_text = json_decode($gemini_text_output, true);

        // Cek JSON valid
        if (json_last_error() === JSON_ERROR_NONE) {
            $logs[] = "[PHP] Gemini API Call - JSON Parsed Successfully.";
            return [
                'catatan_kondisi' => $parsed_text['catatan_kondisi'] ?? 'AI Gagal memformat catatan.',
                'rekomendasi_tindakan' => $parsed_text['rekomendasi_tindakan'] ?? 'AI Gagal memformat rekomendasi.'
            ];
        } else {
            // Handle JSON error
            $logs[] = "[PHP] Gemini API Call - FALLBACK triggered due to JSON decode error: " . json_last_error_msg();
            return [
                'catatan_kondisi' => 'Gagal memproses respons AI (JSON Error: ' . json_last_error_msg() . ')',
                'rekomendasi_tindakan' => 'Mohon isi manual.'
            ];
        }
    } else {
        // Handle jika struktur respons tidak sesuai
        $logs[] = "[PHP] Gemini API Call - FALLBACK triggered because expected text part was not found in API response.";
    }

    // Fallback Umum
    $logs[] = "[PHP] Gemini API Call - FALLBACK triggered due to unexpected error.";
    return [
        'catatan_kondisi' => 'Gagal menganalisis data. (AI Error)',
        'rekomendasi_tindakan' => 'Mohon isi manual.'
    ];
} // Akhir fungsi callGeminiAPI


// Ambil tanggal mulai & akhir
$tanggal_mulai = $_GET['tanggal_mulai'] ?? null;
$tanggal_akhir = $_GET['tanggal_akhir'] ?? null;

if (empty($tanggal_mulai) || empty($tanggal_akhir)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parameter tanggal mulai/akhir kosong.', 'debug_logs' => ["[PHP] Error: Tanggal mulai/akhir kosong."]]);
    exit;
}

// Validasi Tanggal Laporan Mingguan (Cek duplikasi 'Final' berdasarkan tanggal_mulai)
try {
    $stmt_cek = $conn->prepare("SELECT id FROM laporan_mingguan WHERE tanggal_mulai = ? AND status_laporan = 'Final'");
    $stmt_cek->bind_param("s", $tanggal_mulai);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();
    if ($result_cek->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Laporan FINAL untuk minggu ini sudah ada.', 'debug_logs' => ["[PHP] Validasi Gagal: Laporan FINAL sudah ada untuk minggu mulai $tanggal_mulai."]]);
        $stmt_cek->close();
        exit;
    }
    $stmt_cek->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal memvalidasi tanggal: ' . $e->getMessage(), 'debug_logs' => ["[PHP] Exception saat validasi tanggal: " . $e->getMessage()]]);
    exit;
}

// Struktur respons
$response = [
    'global' => [
        'tanggal_mulai' => $tanggal_mulai,
        'tanggal_akhir' => $tanggal_akhir,
        'total_jadwal_minggu_ini' => 0,
        'total_presensi_terisi' => 0,
        'total_jurnal_terisi' => 0,
        'jadwal_terlewat' => 0,
        'total_siswa_hadir' => 0,
        'total_siswa_izin' => 0,
        'total_siswa_sakit' => 0,
        'total_siswa_alpa' => 0,
        'total_presensi_kosong' => 0,
    ],
    'rincian_per_kelompok' => [],
    'daftar_alpa' => [],
    'catatan_kondisi_ai' => '',
    'rekomendasi_tindakan_ai' => '',
    'debug_logs' => []
];

$problem_alpa_classes = [];
$problem_terlewat_classes = []; // Data untuk AI

try {
    // Kueri Rincian Mingguan (Dengan Collate)
    $sql_rincian_mingguan = "
        SELECT 
            p.kelompok, p.kelas,
            COUNT(DISTINCT jp.id) AS total_jadwal_minggu, 
            COUNT(DISTINCT CASE WHEN rp.status_kehadiran IS NOT NULL AND rp.status_kehadiran != '' THEN jp.id END) AS jadwal_presensi_terisi,
            COUNT(DISTINCT CASE WHEN jp.pengajar IS NOT NULL AND jp.pengajar != '' THEN jp.id END) AS jadwal_jurnal_terisi,
            SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) AS alpa
            -- PERBAIKAN: Hitung 'kosong' langsung di SQL
            , SUM(CASE WHEN rp.status_kehadiran IS NULL OR rp.status_kehadiran = '' THEN 1 ELSE 0 END) AS kosong 
        FROM 
            (SELECT DISTINCT kelompok, kelas FROM peserta WHERE kelompok IS NOT NULL AND kelas IS NOT NULL) p
        LEFT JOIN 
            jadwal_presensi jp ON p.kelompok = jp.kelompok COLLATE utf8mb4_unicode_ci 
                               AND p.kelas = jp.kelas COLLATE utf8mb4_unicode_ci 
                               AND jp.tanggal >= ? AND jp.tanggal <= ?  
        LEFT JOIN 
            rekap_presensi rp ON jp.id = rp.jadwal_id
        WHERE
            p.kelompok IS NOT NULL AND p.kelompok != '' AND p.kelas IS NOT NULL AND p.kelas != ''
        GROUP BY p.kelompok, p.kelas
        ORDER BY p.kelompok, FIELD(p.kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah')
    ";

    $stmt_rincian = $conn->prepare($sql_rincian_mingguan);
    if (!$stmt_rincian) throw new Exception("Gagal prepare sql_rincian_mingguan: " . $conn->error);
    $stmt_rincian->bind_param("ss", $tanggal_mulai, $tanggal_akhir);
    $stmt_rincian->execute();
    $result_rincian = $stmt_rincian->get_result();

    if ($result_rincian) {
        while ($row = $result_rincian->fetch_assoc()) {
            $kelompok = $row['kelompok'];
            $kelas = $row['kelas'];
            if (!isset($response['rincian_per_kelompok'][$kelompok])) {
                $response['rincian_per_kelompok'][$kelompok] = [];
            }
            $jadwal_terlewat_mingguan = $row['total_jadwal_minggu'] - $row['jadwal_presensi_terisi'];

            $response['rincian_per_kelompok'][$kelompok][$kelas] = [
                'total_jadwal_minggu' => (int)$row['total_jadwal_minggu'],
                'hadir' => (int)$row['hadir'],
                'izin' => (int)$row['izin'],
                'sakit' => (int)$row['sakit'],
                'alpa' => (int)$row['alpa'],
                'kosong' => (int)$row['kosong'], // Ambil 'kosong' dari SQL
                'jadwal_presensi_terisi' => (int)$row['jadwal_presensi_terisi'],
                'jadwal_jurnal_terisi' => (int)$row['jadwal_jurnal_terisi']
                // 'total_entri' bisa dihapus jika tidak dipakai lagi
            ];
            // Kumpulkan data masalah untuk AI (mingguan)
            if ((int)$row['alpa'] > 0) $problem_alpa_classes[] = "$kelompok $kelas (" . $row['alpa'] . ")";
            if ($jadwal_terlewat_mingguan > 0) $problem_terlewat_classes[] = "$kelompok $kelas (" . $jadwal_terlewat_mingguan . ")";

            // Akumulasi Global (mingguan) - (Kosong tidak perlu diakumulasi global)
            $response['global']['total_jadwal_minggu_ini'] += (int)$row['total_jadwal_minggu'];
            $response['global']['total_presensi_terisi'] += (int)$row['jadwal_presensi_terisi'];
            $response['global']['total_jurnal_terisi'] += (int)$row['jadwal_jurnal_terisi'];
            $response['global']['jadwal_terlewat'] += $jadwal_terlewat_mingguan;
            $response['global']['total_siswa_hadir'] += (int)$row['hadir'];
            $response['global']['total_siswa_izin'] += (int)$row['izin'];
            $response['global']['total_siswa_sakit'] += (int)$row['sakit'];
            $response['global']['total_siswa_alpa'] += (int)$row['alpa'];
        }
    }
    $stmt_rincian->close();

    // Kueri Daftar Siswa Alpa (Mingguan - Dengan Collate)
    $sql_alpa_mingguan = "
        SELECT 
            p.nama_lengkap, p.kelompok, p.kelas, p.nama_orang_tua, p.nomor_hp_orang_tua,
            COUNT(rp.id) as jumlah_alpa 
        FROM rekap_presensi rp
        JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
        JOIN peserta p ON rp.peserta_id = p.id
        WHERE 
            jp.tanggal BETWEEN ? AND ? 
            AND rp.status_kehadiran = 'Alpa'
        GROUP BY p.id, p.nama_lengkap, p.kelompok, p.kelas, p.nama_orang_tua, p.nomor_hp_orang_tua 
        ORDER BY 
            jumlah_alpa DESC, 
            p.kelompok COLLATE utf8mb4_unicode_ci, 
            p.kelas COLLATE utf8mb4_unicode_ci, 
            p.nama_lengkap COLLATE utf8mb4_unicode_ci 
    ";

    $stmt_alpa = $conn->prepare($sql_alpa_mingguan);
    if (!$stmt_alpa) throw new Exception("Gagal prepare sql_alpa_mingguan: " . $conn->error);
    $stmt_alpa->bind_param("ss", $tanggal_mulai, $tanggal_akhir);
    $stmt_alpa->execute();
    $result_alpa = $stmt_alpa->get_result();
    if ($result_alpa) {
        while ($row_alpa = $result_alpa->fetch_assoc()) {
            $response['daftar_alpa'][] = $row_alpa;
        }
    }
    $stmt_alpa->close();

    // Panggil API Gemini (Prompt disesuaikan untuk mingguan)
    $prompt_text = "Tolong analisis data laporan mingguan SIMAK (" . $tanggal_mulai . " s/d " . $tanggal_akhir . "):\n";
    $prompt_text .= "- Total Jadwal Minggu Ini: " . $response['global']['total_jadwal_minggu_ini'] . "\n";
    $prompt_text .= "- Jadwal Terlewat (Presensi Kosong): " . $response['global']['jadwal_terlewat'] . "\n";
    $prompt_text .= "- Total Siswa Alpa (akumulasi): " . $response['global']['total_siswa_alpa'] . "\n";
    $prompt_text .= "- Total Siswa Izin/Sakit (akumulasi): " . ($response['global']['total_siswa_izin'] + $response['global']['total_siswa_sakit']) . "\n";
    if (!empty($problem_alpa_classes)) $prompt_text .= "- Detail Kelas dengan Alpa Tertinggi (akumulasi): " . implode(', ', $problem_alpa_classes) . ".\n";
    if (!empty($problem_terlewat_classes)) $prompt_text .= "- Detail Jadwal Terlewat (akumulasi): " . implode(', ', $problem_terlewat_classes) . ".\n";
    if ($response['global']['total_jadwal_minggu_ini'] == 0) $prompt_text = "Tidak ada jadwal KBM pada minggu " . $tanggal_mulai . " s/d " . $tanggal_akhir . ". Buat catatan singkat.";

    $generated_text = callGeminiAPI($prompt_text, $debug_logs);
    $response['catatan_kondisi_ai'] = $generated_text['catatan_kondisi'] ?? 'AI Gagal.';
    $response['rekomendasi_tindakan_ai'] = $generated_text['rekomendasi_tindakan'] ?? 'Isi manual.';

    // Kirim response
    $response['debug_logs'] = $debug_logs;
    header('Content-Type: application/json');
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    $debug_logs[] = "[PHP] AJAX Stats Error Exception: " . $e->getMessage();
    echo json_encode(['error' => 'Terjadi kesalahan internal: ' . $e->getMessage(), 'debug_logs' => $debug_logs]);
}
exit;
