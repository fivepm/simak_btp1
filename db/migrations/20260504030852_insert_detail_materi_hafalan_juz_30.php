<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriHafalanJuz30 extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Makna Al-Qur'an" di tabel induk
        // Menggunakan double quote ('') untuk escape tanda kutip tunggal di SQL
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Hafalan Juz 30' LIMIT 1");

        if (empty($row)) {
            // Jika belum ada, batalkan proses insert ini agar tidak error
            echo "Peringatan: Kategori 'Hafalan Juz 30' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data 114 Surat beserta jumlah ayatnya
        $daftarSurat = [
            ['nama' => 'An-Naba\'', 'ayat' => 40],
            ['nama' => 'An-Nazi\'at', 'ayat' => 46],
            ['nama' => '\'Abasa', 'ayat' => 42],
            ['nama' => 'At-Takwir', 'ayat' => 29],
            ['nama' => 'Al-Infitar', 'ayat' => 19],
            ['nama' => 'Al-Mutaffifin', 'ayat' => 36],
            ['nama' => 'Al-Insyiqaq', 'ayat' => 25],
            ['nama' => 'Al-Buruj', 'ayat' => 22],
            ['nama' => 'At-Tariq', 'ayat' => 17],
            ['nama' => 'Al-A\'la', 'ayat' => 19],
            ['nama' => 'Al-Gasyiyah', 'ayat' => 26],
            ['nama' => 'Al-Fajr', 'ayat' => 30],
            ['nama' => 'Al-Balad', 'ayat' => 20],
            ['nama' => 'Asy-Syams', 'ayat' => 15],
            ['nama' => 'Al-Lail', 'ayat' => 21],
            ['nama' => 'Ad-Duha', 'ayat' => 11],
            ['nama' => 'Asy-Syarh', 'ayat' => 8],
            ['nama' => 'At-Tin', 'ayat' => 8],
            ['nama' => 'Al-\'Alaq', 'ayat' => 19],
            ['nama' => 'Al-Qadr', 'ayat' => 5],
            ['nama' => 'Al-Bayyinah', 'ayat' => 8],
            ['nama' => 'Az-Zalzalah', 'ayat' => 8],
            ['nama' => 'Al-\'Adiyat', 'ayat' => 11],
            ['nama' => 'Al-Qari\'ah', 'ayat' => 11],
            ['nama' => 'At-Takasur', 'ayat' => 8],
            ['nama' => 'Al-\'Asr', 'ayat' => 3],
            ['nama' => 'Al-Humazah', 'ayat' => 9],
            ['nama' => 'Al-Fil', 'ayat' => 5],
            ['nama' => 'Quraisy', 'ayat' => 4],
            ['nama' => 'Al-Ma\'un', 'ayat' => 7],
            ['nama' => 'Al-Kausar', 'ayat' => 3],
            ['nama' => 'Al-Kafirun', 'ayat' => 6],
            ['nama' => 'An-Nasr', 'ayat' => 3],
            ['nama' => 'Al-Lahab', 'ayat' => 5],
            ['nama' => 'Al-Ikhlas', 'ayat' => 4],
            ['nama' => 'Al-Falaq', 'ayat' => 5],
            ['nama' => 'An-Nas', 'ayat' => 6]
        ];

        // 3. Transformasi array ke format database
        $dataInsert = [];
        foreach ($daftarSurat as $urutan => $surat) {
            $dataInsert[] = [
                'master_materi_id'    => $materiId,           // FK ke tabel master_materi
                'judul_detail'  => ($urutan + 1) . '. ' . $surat['nama'], // Hasil: "1. Al-Fatihah"
                'total_isi' => $surat['ayat'],      // Jumlah ayat
                'created_at'   => $now
            ];
        }

        // 4. Lakukan Insert Massal
        // SESUAIKAN: Ubah 'master_materi_detail' menjadi nama tabel detail Anda yang sebenarnya
        $this->table('master_materi_detail')->insert($dataInsert)->saveData();
    }
}
