<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriPraktekIbadah extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Praktek Ibadah" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Praktek Ibadah' LIMIT 1");

        if (empty($row)) {
            echo "Peringatan: Kategori 'Praktek Ibadah' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Praktek Ibadah (Kelompokkan berdasarkan Nama Poin)
        $daftarPraktek = [
            '1. Bersuci dan Menjaga Kesucian' => [
                'Mempraktikkan wudlu beserta doa sebelum dan sesudahnya',
                'Mempraktikkan mandi junub',
                'Mempraktikkan menjaga kesucian (mengenal suci najis)',
                'Mempraktikkan cara buang air kecil (kencing) dan air besar (berak)',
                'Mempraktikkan cara mensucikan najis setelah kencing dan berak'
            ],
            '2. Sholat' => [
                'Mempraktikkan sholat beserta bacaan dan doanya, serta menjaga tuma\'ninah dan kekhusu\'annya',
                'Mempraktikkan sholat berjamaah',
                'Mempraktikkan sholat sunnah rowatib',
                'Mempraktikkan sholat dhuha',
                'Mempraktikkan sholat tasbih',
                'Mempraktikkan sholat hajat',
                'Mempraktikkan sholat istikhoroh'
            ],
            '3. Membaca Al Quran, Dzikir, dan Berdoa' => [
                'Mempraktikkan rutin membaca al-Quran',
                'Mempraktikkan dzikir dan berdoa setiap selesai sholat',
                'Mempraktikkan dzikir, do\'a dan sholat malam/ sholat tahajjud',
                'Mempraktikkan rutin membaca PR 13',
                'Mempraktikkan doa-doa yang telah dihafal'
            ],
            '4. Berpuasa' => [
                'Mempraktikkan puasa Romadhan',
                'Mempraktikkan puasa sunah'
            ],
            '5. Berpakaian Syar\'i, Menjaga Pergaulan dan perbuatan dosa' => [
                'Mempraktikkan membiasakan berpakaian yang syar\'i',
                'Mempraktikkan menjaga pergaulan antara laki-laki dan perempuan yang bukan mahromnya',
                'Mempraktikkan menjaga diri dari lahan, kemaksiyatan, dan keharoman'
            ],
            '6. Menikah Sesama Jamaah' => [
                'Mempraktikkan memilih calon suami atau istri',
                'Mempraktikkan menikah dengan sesama jamaah'
            ]
        ];

        // 3. Transformasi array menjadi format "Nama Poin - Sub Poin"
        $dataInsert = [];
        foreach ($daftarPraktek as $poin => $subPoins) {
            foreach ($subPoins as $index => $subPoin) {
                $nomorSubPoin = $index + 1;
                $dataInsert[] = [
                    'master_materi_id' => $materiId, // FK ke tabel master_materi
                    'judul_detail'     => $poin . '. Poin (' . $nomorSubPoin . ') : ' . $subPoin, // Hasil: "1. Bersuci dan Menjaga Kesucian. Poin (1) : Mempraktikkan wudlu..."
                    'total_isi'        => 1,         // Nilai default karena tipe CHECKLIST
                    'created_at'       => $now
                ];
            }
        }

        // 4. Lakukan Insert Massal ke tabel detail (Total: 24 baris)
        $this->table('master_materi_detail')->insert($dataInsert)->saveData();
    }
}