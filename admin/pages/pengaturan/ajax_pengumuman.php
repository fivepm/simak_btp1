<?php
session_start();
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';
// Sesuaikan path fungsi kirimWhatsApp Anda
require_once '../../helpers/wa_gateway.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak valid.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {

    // =======================================================
    // 1. KIRIM KE SATU TARGET (dipanggil loop per-penerima)
    // =======================================================
    if ($action === 'kirim_satu') {
        $target = trim($_POST['target'] ?? '');
        $pesan  = trim($_POST['pesan']  ?? '');
        $label  = trim($_POST['label']  ?? $target); // Nama/label penerima untuk log

        if (empty($target) || empty($pesan)) {
            echo json_encode(['status' => 'error', 'message' => 'Target atau pesan kosong.']);
            exit;
        }

        if (function_exists('kirimWhatsApp') && kirimWhatsApp($target, $pesan)) {
            echo json_encode(['status' => 'success', 'message' => "Berhasil dikirim ke: $label"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Gagal kirim ke: $label"]);
        }
    }

    // =======================================================
    // 2. JADWALKAN PENGIRIMAN
    // =======================================================
    elseif ($action === 'jadwalkan') {
        $pesan       = trim($_POST['pesan']       ?? '');
        $waktu_kirim = trim($_POST['waktu_kirim'] ?? '');
        $targets     = $_POST['targets'] ?? [];          // Array [{target, label}]

        if (empty($pesan) || empty($waktu_kirim) || empty($targets)) {
            throw new Exception("Data tidak lengkap untuk penjadwalan.");
        }

        $stmt = $conn->prepare(
            "INSERT INTO pesan_terjadwal (nomor_tujuan, isi_pesan, status, waktu_kirim) VALUES (?, ?, 'pending', ?)"
        );

        foreach ($targets as $t) {
            $nomor = is_array($t) ? ($t['target'] ?? '') : $t;
            if (empty($nomor)) continue;
            $stmt->bind_param("sss", $nomor, $pesan, $waktu_kirim);
            $stmt->execute();
        }
        $stmt->close();

        $jumlah = count($targets);
        writeLog('INSERT', "Menjadwalkan pengumuman untuk $jumlah penerima pada $waktu_kirim");
        echo json_encode(['status' => 'success', 'message' => "Pengumuman berhasil dijadwalkan untuk $jumlah penerima."]);
    }

    // =======================================================
    // 3. SIMPAN TEMPLATE BARU
    // =======================================================
    elseif ($action === 'simpan_template') {
        $judul = trim($_POST['judul_template'] ?? '');
        $isi   = trim($_POST['isi_template']   ?? '');

        if (empty($judul) || empty($isi)) {
            throw new Exception("Judul dan isi template wajib diisi.");
        }

        $stmt = $conn->prepare("INSERT INTO pengumuman_template (judul_template, isi_template) VALUES (?, ?)");
        $stmt->bind_param("ss", $judul, $isi);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            writeLog('INSERT', "Menambah template pengumuman: $judul");
            echo json_encode([
                'status'  => 'success',
                'message' => 'Template berhasil disimpan.',
                'id'      => $new_id,
                'judul'   => $judul,
                'isi'     => $isi,
            ]);
        } else {
            throw new Exception("Gagal menyimpan template.");
        }
        $stmt->close();
    }

    // =======================================================
    // 4. UPDATE TEMPLATE
    // =======================================================
    elseif ($action === 'update_template') {
        $id    = (int)($_POST['template_id']    ?? 0);
        $judul = trim($_POST['judul_template']  ?? '');
        $isi   = trim($_POST['isi_template']    ?? '');

        if (!$id || empty($judul) || empty($isi)) {
            throw new Exception("Data update template tidak lengkap.");
        }

        $stmt = $conn->prepare("UPDATE pengumuman_template SET judul_template = ?, isi_template = ? WHERE id = ?");
        $stmt->bind_param("ssi", $judul, $isi, $id);

        if ($stmt->execute()) {
            writeLog('UPDATE', "Memperbarui template ID $id: $judul");
            echo json_encode([
                'status'  => 'success',
                'message' => 'Template berhasil diperbarui.',
                'id'      => $id,
                'judul'   => $judul,
                'isi'     => $isi,
            ]);
        } else {
            throw new Exception("Gagal memperbarui template.");
        }
        $stmt->close();
    }

    // =======================================================
    // 5. HAPUS TEMPLATE
    // =======================================================
    elseif ($action === 'hapus_template') {
        $id = (int)($_POST['template_id'] ?? 0);

        if (!$id) {
            throw new Exception("ID template tidak valid.");
        }

        $stmt = $conn->prepare("DELETE FROM pengumuman_template WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            writeLog('DELETE', "Menghapus template ID $id");
            echo json_encode(['status' => 'success', 'message' => 'Template berhasil dihapus.', 'id' => $id]);
        } else {
            throw new Exception("Gagal menghapus template.");
        }
        $stmt->close();
    }

    else {
        throw new Exception("Action tidak dikenali: $action");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}