<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriHafalanDoa extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Hafalan Doa" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Hafalan Doa' LIMIT 1");

        if (empty($row)) {
            echo "Peringatan: Kategori 'Hafalan Doa' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Hafalan Doa
        $daftarHafalan = [
            'Asmaul husna (1 sampai 99)',
            'Doa ketika akan tidur dan setelah bangun tidur',
            'Doa ketika akan makan dan selesai makan',
            'Doa kebaikan dunia dan akhirot',
            'Doa untuk kedua orangtua',
            'Doa masuk dan keluar WC',
            'Doa dan dzikir setelah sholat',
            'Doa ketetapan iman',
            'Doa minta ilmu yang bermanfaat',
            'Doa minta ilham yang baik',
            'Doa masuk dan keluar rumah',
            'Doa pagi dan sore',
            'Doa masuk dan keluar masjid',
            'Doa memakai pakaian',
            'Doa ketika berbuka puasa',
            'Doa mohon kesabaran',
            'Doa mohon kesehatan',
            'Doa ketika bersin',
            'Doa ketika ada angin kencang',
            'Doa ketika menjenguk orang yang sakit',
            'Doa ketika memakai pakaian baru',
            'Doa ketika naik kendaraan',
            'Doa lailatul qodar',
            'Doa ketika masuk pasar',
            'Doa berlindung dari syirik',
            'Doa berlindung dari siksa kubur',
            'Doa sujud al-Qur\'an',
            'Kumpulan doa Nabi Muhammad',
            'Doa berlindung dari sifat munafiq',
            'Doa agar bisa bersyukur',
            'Doa berlindung dari jeleknya pendengaran, ucapan dan pengelihatan',
            'Doa berlindung dari sifat pelit dan penakut',
            'Doa minta dimudahkan dalam segala urusan',
            'Doa minta dipilihkan sesuatu yang baik',
            'Doa minta 10 kebaikan (tetapnya iman, hati khusyu’, tegaknya agama dll)',
            'Doa ketika ada petir',
            'Doa perlindungan dari bencana',
            'Doa perlindungan dari penganiayaan',
            'Doa ketika takut pada orang kafir',
            'Doa ketika bertempat di tempat yang baru',
            'Doa ketika mimpi yang baik dan jelek',
            'Doa minta surga firdaus',
            'Doa pengayoman',
            'Doa maskumambang sapu jagat'
        ];

        // 3. Transformasi array (Tanpa iterasi 6 pertemuan)
        $dataInsert = [];
        foreach ($daftarHafalan as $hafalan) {
            $dataInsert[] = [
                'master_materi_id' => $materiId, // FK ke tabel master_materi
                'judul_detail'     => $hafalan,  // Langsung menggunakan nama doa
                'total_isi'        => 1,         // Nilai default karena tipe CHECKLIST
                'created_at'       => $now
            ];
        }

        // 4. Lakukan Insert Massal ke tabel detail (Total: 44 baris)
        $this->table('master_materi_detail')->insert($dataInsert)->saveData();
    }
}