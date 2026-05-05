<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriNasehat extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Nasehat" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Nasehat' LIMIT 1");

        if (empty($row)) {
            echo "Peringatan: Kategori 'Nasehat' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Nasehat (Dikelompokkan berdasarkan Bab/Poin Utama)
        $daftarNasehat = [
            'Faham Surga Neraka' => [
                'Meyakini perbuatan baik yang berpahala dan memasukkan ke surga',
                'Meyakini perbuatan jelek yang berdosa dan memasukkan ke neraka',
                'Meyakini dan hafal enam alam kehidupan manusia (alam ruh, alam rahim, alam dunia, alam kubur , alam qiyamat, dan alam akhirat)',
                'Meyakini tujuan ibadah (niat mencari surga selamat dari neraka)',
                'Meyakini tentang sak dermo karena Alloh',
                'Meyakini dan mengutamakan urusan akhirat daripada urusan dunia',
                'Meyakini dan Mengepolkan akhirat dalam segala aspek kehidupan (dalam hal kuliah, bekerja, menikah dan bertempat tinggal)',
                'Meyakini keutamaan amal jariyah',
                'Meyakini cinta dan ridlo Alloh'
            ],
            'Kewajiban Ibadah, Rukun Iman, Rukun Islam, QHJ' => [
                'Meyakini kewajiban beribadah kepada Alloh berdasarkan al-Qur\'an dan al-Hadits.',
                'Meyakini rukun iman',
                'Meyakini rukun islam',
                'Meyakini Qur\'an Hadits Jamaah',
                'Meyakini ilmu manqul, musnad, muttashil',
                'Meyakini haromnya bid\'ah, syirik, khurofat dan takhayyul',
                'Meyakini haromnya adat jahiliyyah',
                'Meyakini tentang ihsan',
                'Meyakini kemurnian ibadah secara al-Qur\'an dan al-Hadits'
            ],
            'Faham Halal-Harom, Taat-Maksiat, Mahrom, Muamalah, Hukum Waris' => [
                'Meyakini halal dan harom',
                'Meyakini taat dan maksiat',
                'Memahami/meyakini batas-batas mahrom',
                'Memahami/Meyakini tentang macam-macam dosa besar dan akibatnya',
                'Memahami/Meyakini tujuh transaksi yang diharomkan',
                'Meyakini hukum waris'
            ],
            'Memahami bab Thoharoh, Nikah-Talaq-Rujuk, Kewajiban Suami-Istri, Kewajiban Orang Tua' => [
                'Memahami/meyakini suci dan najis (thoharoh)',
                'Memahami/meyakini hukum dan permasalahan haid, mani, madzi dan wadhi',
                'Memahami/Meyakini hukum nikah, talaq dan rujuk',
                'Memahami/Meyakini kewajiban suami-istri',
                'Memahami/Meyakini kewajiban mendidik dan membina anak'
            ],
            'Faham Jamaah' => [
                'Meyakini pengertian jamaah dan wajibnya berjamaah',
                'Meyakini wajibnya menetapi Lima Bab',
                'Meyakini Empat Tali Keimanan',
                'Memahami Enam Thobiat Luhur Jamaah',
                'Memahami Tri Sukses Generasi Penerus'
            ],
            'Pembinaan, Peramutan, Kerukunan & Budi Luhur' => [
                'Memahami 5 tahapan pembinaan QHJ',
                'Memahami 10 peramutan dalam jamaah',
                'Memahami 5 usaha mencari kefahaman',
                'Memahami 5 pembinaan kedalam',
                'Memahami pengertian dan wajibnya budi luhur',
                'Memahami Lima Syarat Kerukunan',
                'Memahami Fathonah-Bithonah Budi Luhur',
                'Meyakini Empat Maqodirullah (qodar nikmat, qodar cobaan, qodar musibah, qodar salah)',
                'Memahami 4 roda berputar dalam jamaah'
            ],
            'Kepengurusan dalam Jamaah dan Peraturan Bernomor' => [
                'Memahami struktur, mekanisme, dan tugas kepengurusan dalam jamaah (Ulil Amri, 4S + KU, tim tujuh, lima unsur dan PPG)',
                'Memahami struktur kepengurusan organisasi dalam jamaah (LDII, Persinas Asad, Senkom)',
                'Memahami 4 sifat wajib Ulil Amri',
                'Memahami Prinsip Kerja Jamaah (bener, kurup, janji)',
                'Memahami Program Kerja Jamaah (acara, rencana, kerja, kontrol)',
                'Memahami ijtihad peraturan bernomor (55 perintah dan anjuran, 22 larangan)'
            ],
            'Pertolongan, Perjuangan, Fakta Sahnya Jamaah dan 30 point' => [
                'Meyakini 3 Syarat Mendapat Pertolongan',
                'Memahami 5 Tahapan Perjuangan',
                'Meyakini 4 Syarat Keberhasilan Perjuangan',
                'Memahami sejarah perintisan jamaah di Indonesia',
                'Meyakini 7 fakta sahnya jamaah',
                'Meyakini materi 30 point (bisa membedakan antara jamaah dan bukan jamaah)'
            ]
        ];

        // 3. Transformasi array menjadi format "Nama Poin. Poin (n) : Sub Poin"
        $dataInsert = [];
        foreach ($daftarNasehat as $poin => $subPoins) {
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