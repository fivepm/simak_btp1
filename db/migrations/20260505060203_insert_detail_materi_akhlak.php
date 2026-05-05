<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriAkhlak extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Akhlak" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Akhlak' LIMIT 1");

        if (empty($row)) {
            echo "Peringatan: Kategori 'Akhlak' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Akhlak (Dikelompokkan berdasarkan Bab/Poin Utama)
        $daftarAkhlak = [
            'Pribadi' => [
                'Terampil jujur, amanah, mujhid-muzhid (Enam Thobiat Luhur)',
                'Terampil melakukan sifat-sifat yang baik',
                'Terampil menjauhi akhlaq tercela'
            ],
            'Keluarga' => [
                'Terampil berbuat baik kepada orangtua',
                'Terampil berbuat baik kepada saudara dan kerabat'
            ],
            'Ulil Amri, Guru, dan Muballigh-Muballighot' => [
                'Terampil kewajiban berbuat baik kepada Ulil Amri',
                'Terampil kewajiban berbuat baik kepada guru dan muballigh-muballighot'
            ],
            'Masyarakat dan Lingkungan Alam Sekitar' => [
                'Terampil kewajiban berbuat baik kepada teman',
                'Terampil kewajiban berbuat baik kepada tetangga',
                'Terampil berbuat baik kepada tamu',
                'Terampil berbuat baik kepada tokoh masyarakat dan tokoh agama',
                'Terampil berbuat baik kepada pejabat pemerintah',
                'Memiliki karakter berbuat baik di lingkungan kerja',
                'Memiliki karakter cinta alam sekitar'
            ]
        ];

        // 3. Transformasi array menjadi format "Nama Poin. Poin (n) : Sub Poin"
        $dataInsert = [];
        foreach ($daftarAkhlak as $poin => $subPoins) {
            foreach ($subPoins as $index => $subPoin) {
                $nomorSubPoin = $index + 1;
                $dataInsert[] = [
                    'master_materi_id' => $materiId, // FK ke tabel master_materi
                    'judul_detail'     => $poin . '. Poin (' . $nomorSubPoin . ') : ' . $subPoin, 
                    'total_isi'        => 1,         // Nilai default karena tipe CHECKLIST
                    'created_at'       => $now
                ];
            }
        }

        // 4. Lakukan Insert Massal ke tabel detail (Total: 14 baris)
        $this->table('master_materi_detail')->insert($dataInsert)->saveData();
    }
}