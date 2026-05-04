<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriTajwid extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Tajwid" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Tajwid' LIMIT 1");

        if (empty($row)) {
            echo "Peringatan: Kategori 'Tajwid' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Tajwid (Dikelompokkan berdasarkan Bab/Poin Utama)
        $daftarTajwid = [
            'Hukum Nun Sukun dan Tanwin' => [
                'Izhar Halqi',
                'Idgham Bighunnah',
                'Idgham Bilaghunnah',
                'Iqlab',
                'Ikhfa Haqiqi'
            ],
            'Hukum Mim Sukun' => [
                'Ikhfa Syafawi',
                'Idgham Mimi (Mutamatsilain)',
                'Izhar Syafawi'
            ],
            'Hukum Mim dan Nun Bertasydid' => [
                'Ghunnah Musyaddadah'
            ],
            'Hukum Idgham' => [
                'Idgham Mutamatsilain',
                'Idgham Mutajanisain',
                'Idgham Mutaqaribain'
            ],
            'Hukum Qalqalah' => [
                'Qalqalah Sugra',
                'Qalqalah Kubra'
            ],
            'Hukum Mad' => [
                'Mad Thabi\'i (Mad Asli)',
                'Mad Wajib Muttasil',
                'Mad Jaiz Munfasil',
                'Mad \'Aridl Lissukun',
                'Mad Badal',
                'Mad \'Iwad',
                'Mad Layyin (Lin)',
                'Mad Lazim Muthaqqal Kilmi',
                'Mad Lazim Mukhaffaf Kilmi',
                'Mad Lazim Harfi',
                'Mad Silah (Qasirah dan Thawilah)'
            ],
            'Hukum Bacaan Ra\' dan Lam Jalalah' => [
                'Ra\' Tafkhim (Tebal)',
                'Ra\' Tarqiq (Tipis)',
                'Ra\' Jawazul Wajhain (Boleh tebal/tipis)',
                'Lam Tafkhim',
                'Lam Tarqiq'
            ],
            'Makharijul Huruf (Tempat Keluar Huruf)' => [
                'Al-Jauf (Rongga Mulut)',
                'Al-Halq (Tenggorokan)',
                'Al-Lisan (Lidah)',
                'Asy-Syafatain (Dua Bibir)',
                'Al-Khaisyum (Rongga Hidung)'
            ],
            'Waqaf dan Ibtida\'' => [
                'Waqaf Lazim',
                'Waqaf Jaiz',
                'Waqaf Mamnu\' (Dilarang berhenti)',
                'Saktah (Berhenti sejenak tanpa bernapas)'
            ]
        ];

        // 3. Transformasi array menjadi format "Nama Poin. Poin (n) : Sub Poin"
        $dataInsert = [];
        foreach ($daftarTajwid as $poin => $subPoins) {
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

        // 4. Lakukan Insert Massal ke tabel detail
        $this->table('master_materi_detail')->insert($dataInsert)->saveData();
    }
}