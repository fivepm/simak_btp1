<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriQuran extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Makna Al-Qur'an" di tabel induk
        // Menggunakan double quote ('') untuk escape tanda kutip tunggal di SQL
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Makna Al-Qur''an' LIMIT 1");

        if (empty($row)) {
            // Jika belum ada, batalkan proses insert ini agar tidak error
            echo "Peringatan: Kategori 'Makna Al-Qur'an' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data 114 Surat beserta jumlah ayatnya
        $daftarSurat = [
            ['nama' => 'Al-Fatihah', 'ayat' => 7],
            ['nama' => 'Al-Baqarah', 'ayat' => 286],
            ['nama' => 'Ali \'Imran', 'ayat' => 200],
            ['nama' => 'An-Nisa\'', 'ayat' => 176],
            ['nama' => 'Al-Ma\'idah', 'ayat' => 120],
            ['nama' => 'Al-An\'am', 'ayat' => 165],
            ['nama' => 'Al-A\'raf', 'ayat' => 206],
            ['nama' => 'Al-Anfal', 'ayat' => 75],
            ['nama' => 'At-Taubah', 'ayat' => 129],
            ['nama' => 'Yunus', 'ayat' => 109],
            ['nama' => 'Hud', 'ayat' => 123],
            ['nama' => 'Yusuf', 'ayat' => 111],
            ['nama' => 'Ar-Ra\'d', 'ayat' => 43],
            ['nama' => 'Ibrahim', 'ayat' => 52],
            ['nama' => 'Al-Hijr', 'ayat' => 99],
            ['nama' => 'An-Nahl', 'ayat' => 128],
            ['nama' => 'Al-Isra\'', 'ayat' => 111],
            ['nama' => 'Al-Kahf', 'ayat' => 110],
            ['nama' => 'Maryam', 'ayat' => 98],
            ['nama' => 'Ta Ha', 'ayat' => 135],
            ['nama' => 'Al-Anbiya\'', 'ayat' => 112],
            ['nama' => 'Al-Hajj', 'ayat' => 78],
            ['nama' => 'Al-Mu\'minun', 'ayat' => 118],
            ['nama' => 'An-Nur', 'ayat' => 64],
            ['nama' => 'Al-Furqan', 'ayat' => 77],
            ['nama' => 'Asy-Syu\'ara\'', 'ayat' => 227],
            ['nama' => 'An-Naml', 'ayat' => 93],
            ['nama' => 'Al-Qasas', 'ayat' => 88],
            ['nama' => 'Al-\'Ankabut', 'ayat' => 69],
            ['nama' => 'Ar-Rum', 'ayat' => 60],
            ['nama' => 'Luqman', 'ayat' => 34],
            ['nama' => 'As-Sajdah', 'ayat' => 30],
            ['nama' => 'Al-Ahzab', 'ayat' => 73],
            ['nama' => 'Saba\'', 'ayat' => 54],
            ['nama' => 'Fatir', 'ayat' => 45],
            ['nama' => 'Ya Sin', 'ayat' => 83],
            ['nama' => 'As-Saffat', 'ayat' => 182],
            ['nama' => 'Sad', 'ayat' => 88],
            ['nama' => 'Az-Zumar', 'ayat' => 75],
            ['nama' => 'Ghafir', 'ayat' => 85],
            ['nama' => 'Fussilat', 'ayat' => 54],
            ['nama' => 'Asy-Syura', 'ayat' => 53],
            ['nama' => 'Az-Zukhruf', 'ayat' => 89],
            ['nama' => 'Ad-Dukhan', 'ayat' => 59],
            ['nama' => 'Al-Jasiyah', 'ayat' => 37],
            ['nama' => 'Al-Ahqaf', 'ayat' => 35],
            ['nama' => 'Muhammad', 'ayat' => 38],
            ['nama' => 'Al-Fath', 'ayat' => 29],
            ['nama' => 'Al-Hujurat', 'ayat' => 18],
            ['nama' => 'Qaf', 'ayat' => 45],
            ['nama' => 'Az-Zariyat', 'ayat' => 60],
            ['nama' => 'At-Tur', 'ayat' => 49],
            ['nama' => 'An-Najm', 'ayat' => 62],
            ['nama' => 'Al-Qamar', 'ayat' => 55],
            ['nama' => 'Ar-Rahman', 'ayat' => 78],
            ['nama' => 'Al-Waqi\'ah', 'ayat' => 96],
            ['nama' => 'Al-Hadid', 'ayat' => 29],
            ['nama' => 'Al-Mujadilah', 'ayat' => 22],
            ['nama' => 'Al-Hasyr', 'ayat' => 24],
            ['nama' => 'Al-Mumtahanah', 'ayat' => 13],
            ['nama' => 'As-Saff', 'ayat' => 14],
            ['nama' => 'Al-Jumu\'ah', 'ayat' => 11],
            ['nama' => 'Al-Munafiqun', 'ayat' => 11],
            ['nama' => 'At-Tagabun', 'ayat' => 18],
            ['nama' => 'At-Talaq', 'ayat' => 12],
            ['nama' => 'At-Tahrim', 'ayat' => 12],
            ['nama' => 'Al-Mulk', 'ayat' => 30],
            ['nama' => 'Al-Qalam', 'ayat' => 52],
            ['nama' => 'Al-Haqqah', 'ayat' => 52],
            ['nama' => 'Al-Ma\'arij', 'ayat' => 44],
            ['nama' => 'Nuh', 'ayat' => 28],
            ['nama' => 'Al-Jinn', 'ayat' => 28],
            ['nama' => 'Al-Muzzammil', 'ayat' => 20],
            ['nama' => 'Al-Muddassir', 'ayat' => 56],
            ['nama' => 'Al-Qiyamah', 'ayat' => 40],
            ['nama' => 'Al-Insan', 'ayat' => 31],
            ['nama' => 'Al-Mursalat', 'ayat' => 50],
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
