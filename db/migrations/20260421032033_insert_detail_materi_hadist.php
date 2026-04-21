<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriHadist extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Makna Al-Hadist" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Makna Al-Hadist' LIMIT 1");

        if (empty($row)) {
            // Jika belum ada, batalkan proses insert ini agar tidak error
            echo "Peringatan: Kategori 'Makna Al-Hadist' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Kitab beserta jumlah halamannya
        $daftarKitab = [
            ['nama' => 'K. Sholah', 'halaman' => 151],
            ['nama' => 'K. Sholatinawafil', 'halaman' => 98],
            ['nama' => 'K. Da\'wat', 'halaman' => 65], // Escape petik satu pada Da'wat
            ['nama' => 'K. Adab', 'halaman' => 96],
            ['nama' => 'K. Ahkam', 'halaman' => 124],
            ['nama' => 'K. Manasik Waljihad', 'halaman' => 51],
            ['nama' => 'K. Jihad', 'halaman' => 63],
            ['nama' => 'K. Haji', 'halaman' => 111],
            ['nama' => 'K. Manasikil Haji', 'halaman' => 113],
            ['nama' => 'K. Shifati Jannatiwannar', 'halaman' => 84],
            ['nama' => 'K. Janaiz', 'halaman' => 79],
            ['nama' => 'K. Adilah', 'halaman' => 96],
            ['nama' => 'K. Shoum', 'halaman' => 98],
            ['nama' => 'K. Imaroh', 'halaman' => 102],
            ['nama' => 'K. Kanzil Umal', 'halaman' => 122],
            ['nama' => 'K. Khutbah', 'halaman' => 152],
            ['nama' => 'K. Faroid', 'halaman' => 134]
        ];

        // 3. Transformasi array ke format insert tabel
        $dataInsert = [];
        foreach ($daftarKitab as $urutan => $kitab) {
            $dataInsert[] = [
                'master_materi_id'    => $materiId,           // FK ke tabel master_materi
                'judul_detail'  => $kitab['nama'], // Hasil: "1. K. Sholah"
                'total_isi' => $kitab['halaman'],   // Jumlah halaman
                'created_at'   => $now
            ];
        }

        // 4. Lakukan Insert Massal ke tabel detail
        $this->table('master_materi_detail')->insert($dataInsert)->saveData();
    }
}
