<?php

/**
 * Mengirim pesan WhatsApp via CraftiveLabs (Teks Saja)
 * * @param string $nomor       Nomor tujuan (Format: 628xxx)
 * @param string $pesan       Isi pesan
 */
function kirimWhatsApp($nomor, $pesan)
{
    $apiToken = $_ENV['WA_API_TOKEN'];
    $sessionKey = $_ENV['WA_SESSION_KEY'];
    $url = 'https://notify.craftivelabs.com/api/message/send';

    $pesan_wm = $pesan . "\n\n> SIMAK Banguntapan 1.";
    $data = [
        'session_key' => $sessionKey,
        'phone'       => $nomor,
        'message'     => $pesan_wm
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    unset($ch);

    if ($result === false) {
        return [
            'status' => 'error',
            'message' => 'Koneksi Gagal: ' . $curlError
        ];
    }

    $response = json_decode($result, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'status' => 'success',
            'message' => 'Pesan berhasil dikirim.',
            'data' => $response
        ];
    } else {
        return [
            'status' => 'error',
            'message' => 'Gagal mengirim pesan. HTTP Code: ' . $httpCode,
            'data' => $response
        ];
    }
}

/**
 * Mengirim pesan WhatsApp beserta media dari hasil file upload ($_FILES).
 *
 * Fungsi ini menangani masalah Android yang sering melaporkan MIME type file
 * sebagai 'application/octet-stream' (BIN) meskipun file aslinya adalah PDF.
 * Solusinya: deteksi MIME dari isi fisik file, bukan dari laporan browser.
 *
 * @param string $phone      Nomor tujuan (Format: 628xxx)
 * @param string $caption    Caption pesan yang menyertai media
 * @param string $tmpPath    Path file sementara dari $_FILES['...']['tmp_name']
 * @param string $rawName    Nama asli file dari $_FILES['...']['name']
 * @return bool              true jika berhasil, false jika gagal
 * @throws Exception         Jika file gagal dipindahkan atau terjadi error cURL
 */
function kirimWhatsAppDariUpload(string $phone, string $caption, string $tmpPath, string $rawName): bool
{
    // -------------------------------------------------------
    // LANGKAH 1: INSPEKSI FISIK FILE
    // Selalu gunakan mime_content_type() — jangan percaya
    // $_FILES['type'] karena Android sering kirim 'application/octet-stream'.
    // -------------------------------------------------------
    $mimeFisik = @mime_content_type($tmpPath);

    // Fallback: baca magic bytes jika mime_content_type() gagal atau tidak akurat
    if (empty($mimeFisik) || $mimeFisik === 'application/octet-stream') {
        $fh     = fopen($tmpPath, 'rb');
        $header = $fh ? fread($fh, 8) : '';
        if ($fh) fclose($fh);

        if (substr($header, 0, 4) === '%PDF') {
            $mimeFisik = 'application/pdf';
        } elseif (substr($header, 0, 4) === "\x89PNG") {
            $mimeFisik = 'image/png';
        } elseif (substr($header, 0, 2) === "\xFF\xD8") {
            $mimeFisik = 'image/jpeg';
        } elseif (substr($header, 0, 4) === "PK\x03\x04") {
            // ZIP container: bisa DOCX atau XLSX — tentukan dari ekstensi nama asli
            $extAsli   = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));
            $mimeFisik = in_array($extAsli, ['xlsx', 'xls'])
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }
    }

    // -------------------------------------------------------
    // LANGKAH 2: TENTUKAN EKSTENSI & MIME TYPE YANG AKURAT
    // Keduanya selalu diambil dari MIME fisik, bukan dari nama file.
    // -------------------------------------------------------
    if (strpos($mimeFisik, 'pdf') !== false) {
        $fileExt        = 'pdf';
        $mimeTypeAkurat = 'application/pdf';
    } elseif (strpos($mimeFisik, 'jpeg') !== false) {
        $fileExt        = 'jpg';
        $mimeTypeAkurat = 'image/jpeg';
    } elseif (strpos($mimeFisik, 'png') !== false) {
        $fileExt        = 'png';
        $mimeTypeAkurat = 'image/png';
    } elseif (strpos($mimeFisik, 'gif') !== false) {
        $fileExt        = 'gif';
        $mimeTypeAkurat = 'image/gif';
    } elseif (strpos($mimeFisik, 'webp') !== false) {
        $fileExt        = 'webp';
        $mimeTypeAkurat = 'image/webp';
    } elseif (strpos($mimeFisik, 'spreadsheet') !== false || strpos($mimeFisik, 'excel') !== false) {
        $fileExt        = 'xlsx';
        $mimeTypeAkurat = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    } elseif (strpos($mimeFisik, 'wordprocessingml') !== false || strpos($mimeFisik, 'msword') !== false) {
        $fileExt        = 'docx';
        $mimeTypeAkurat = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    } elseif (strpos($mimeFisik, 'mp4') !== false || strpos($mimeFisik, 'video') !== false) {
        $fileExt        = 'mp4';
        $mimeTypeAkurat = 'video/mp4';
    } elseif (strpos($mimeFisik, 'mpeg') !== false || strpos($mimeFisik, 'audio') !== false) {
        $fileExt        = 'mp3';
        $mimeTypeAkurat = 'audio/mpeg';
    } else {
        // Fallback: ambil dari nama file asli jika mime tidak dikenali
        $fileExt        = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));
        $mimeTypeAkurat = !empty($mimeFisik) ? $mimeFisik : 'application/octet-stream';
    }

    // -------------------------------------------------------
    // LANGKAH 3: TENTUKAN MEDIA TYPE UNTUK WA GATEWAY
    // -------------------------------------------------------
    $mediaType = 'document'; // default
    if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $mediaType = 'image';
    } elseif (in_array($fileExt, ['mp4', 'avi', 'mkv'])) {
        $mediaType = 'video';
    } elseif (in_array($fileExt, ['mp3', 'ogg', 'wav'])) {
        $mediaType = 'audio';
    }

    // -------------------------------------------------------
    // LANGKAH 4: SANITASI NAMA FILE
    // Android sering mengirim URI aneh seperti 'document:1234'.
    // -------------------------------------------------------
    $namaTanpaExt = preg_replace('/^a-zA-Z0-9_ -/', '_', pathinfo($rawName, PATHINFO_FILENAME));
    if (empty(trim($namaTanpaExt))) {
        $namaTanpaExt = 'berkas_' . time();
    }
    $safeFileName = $namaTanpaExt . ($fileExt ? '.' . $fileExt : '');

    // -------------------------------------------------------
    // LANGKAH 5: SALIN FILE KE PATH SEMENTARA DENGAN EKSTENSI YANG BENAR
    // API WA Gateway membaca nama file fisik di server. Jika path-nya masih
    // /tmp/phpXXXXXX (tanpa ekstensi), gateway akan mengirimnya sebagai BIN.
    // -------------------------------------------------------
    $realFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . '_' . $safeFileName;

    if (!move_uploaded_file($tmpPath, $realFilePath)) {
        throw new Exception("Gagal memproses file upload di server lokal.");
    }

    // -------------------------------------------------------
    // LANGKAH 6: KIRIM KE API WA GATEWAY VIA cURL
    // -------------------------------------------------------
    $bearerToken = $_ENV['WA_API_TOKEN'] ?? '';
    $sessionKey  = $_ENV['WA_SESSION_KEY'] ?? '';
    $captionWm   = $caption . "\n\n> SIMAK Banguntapan 1.";

    $cFile = curl_file_create($realFilePath, $mimeTypeAkurat, $safeFileName);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://notify.craftivelabs.com/api/message/media',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'session_key' => $sessionKey,
            'phone'       => $phone,
            'caption'     => $captionWm,
            'media_type'  => $mediaType,
            'file'        => $cFile,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $bearerToken,
        ],
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Selalu hapus file sementara setelah dikirim agar server tidak penuh
    @unlink($realFilePath);

    if ($err) {
        throw new Exception("cURL Error: " . $err);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("API Gateway Error ($httpCode): " . $response);
    }

    return true;
}

/**
 * @deprecated Gunakan kirimWhatsAppDariUpload() untuk file dari $_FILES.
 *             Fungsi ini tetap dipertahankan untuk kompatibilitas mundur.
 */
function kirimWhatsAppMedia($phoneNumber, $caption, $tmpFilePath, $fileName, $mediaType = 'document')
{
    $bearerToken = $_ENV['WA_API_TOKEN'] ?? '';
    $sessionKey  = $_ENV['WA_SESSION_KEY'] ?? '';
    $captionWm   = $caption . "\n\n> SIMAK Banguntapan 1.";

    $cFile = new CURLFile($tmpFilePath, mime_content_type($tmpFilePath), $fileName);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://notify.craftivelabs.com/api/message/media',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'session_key' => $sessionKey,
            'phone'       => $phoneNumber,
            'caption'     => $captionWm,
            'media_type'  => $mediaType,
            'file'        => $cFile,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $bearerToken,
        ],
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $err ? false : ($httpCode >= 200 && $httpCode < 300);
}