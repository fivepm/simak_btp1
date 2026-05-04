<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriHafalanDalil extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Hafalan Dalil" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Hafalan Dalil' LIMIT 1");

        if (empty($row)) {
            echo "Peringatan: Kategori 'Hafalan Dalil' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Hafalan Dalil
        $daftarHafalan = [
            'Kewajiban beribadah kepada Alloh, beragama Islam dan menetapi Qur\'an Hadits Jamaah',
            'Lima Bab',
            'Empat Tali Keimanan',
            'Tri Sukses Generasi Penerus',
            'Enam Thobiat Luhur Jamaah',
            'Lima Syarat Kerukunan',
            'Wajibnya berjamaah',
            'Empat Maqodirullloh (qodar nikmat, qodar cobaan, qodar musibah, qodar salah)',
            'Empat Roda Berputar dalam jamaah',
            'Lima Usaha mencari kefahaman'
        ];

        // 3. Transformasi array dan otomatis kalikan 6 pertemuan
        $dataInsert = [];
        foreach ($daftarHafalan as $hafalan) {
                $dataInsert[] = [
                    'master_materi_id' => $materiId, // FK ke tabel master_materi
                    'judul_detail'     => $hafalan,
                    'total_isi'        => 1,         // Nilai default karena tipe CHECKLIST
                    'created_at'       => $now
                ];
        }

        // 4. Lakukan Insert Massal ke tabel detail (Total: 10 x 6 = 60 baris)
        $this->table('master_materi_detail')->insert($dataInsert)->saveData();
    }
}