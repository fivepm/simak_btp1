<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriKarakter extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "29 Karakter Luhur" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = '29 Karakter Luhur' LIMIT 1");

        if (empty($row)) {
            echo "Peringatan: Kategori '29 Karakter Luhur' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Karakter Induk
        $daftarKarakter = [
            '[Tri Sukses] Akhlaqul Karimah',
            '[Tri Sukses] Alim Faqih',
            '[Tri Sukses] Mandiri',
            '[Empat Tali Keimanan] Bersyukur',
            '[Empat Tali Keimanan] Mempersungguh',
            '[Empat Tali Keimanan] Mengagungkan',
            '[Empat Tali Keimanan] Berdoa',
            '[Enam Thobiat Luhur] Rukun',
            '[Enam Thobiat Luhur] Kompak',
            '[Enam Thobiat Luhur] Kerjasama yang baik',
            '[Enam Thobiat Luhur] Jujur',
            '[Enam Thobiat Luhur] Amanah',
            '[Enam Thobiat Luhur] Mujhid Muzhid',
            '[Lima Syarat Kerukunan] Berbicara yang baik',
            '[Lima Syarat Kerukunan] Bisa percaya dan dipercaya',
            '[Lima Syarat Kerukunan] Sabar, Keporo Ngalah',
            '[Lima Syarat Kerukunan] Tidak Merusak Sesama Saudara',
            '[Lima Syarat Kerukunan] Saling Memperhatikan dan Menjaga Perasaan',
            '[Empat Roda Berputar] Sing iso mulang, sing ora iso diulangi',
            '[Empat Roda Berputar] Sing kuat mbantu, sing ora kuat dibantu',
            '[Empat Roda Berputar] Sing eling ngelingake, sing lali diilingake',
            '[Empat Roda Berputar] Sing bener ngarahake marang kebeneran, sing salah diarahke marang kebeneran lan dikongkon taubat',
            '[Prinsip Kerja Jamaah] Prinsip Kerja Jamaah',
            '[Empat Maqodirullah] Bersyukur Ketika Mendapat Nikmat',
            '[Empat Maqodirullah] Sabar Ketika Mendapat Cobaan',
            '[Empat Maqodirullah] Istirja Ketika Mendapat Cobaan',
            '[Empat Maqodirullah] Bertaubat Ketika Berbuat Salah'
        ];

        // 3. Transformasi array dan otomatis kalikan 6 pertemuan
        $dataInsert = [];
        foreach ($daftarKarakter as $karakter) {
            // Looping dari Pertemuan 1 sampai 6 untuk setiap karakter
            for ($i = 1; $i <= 6; $i++) {
                $dataInsert[] = [
                    'master_materi_id'    => $materiId, // FK ke tabel master_materi
                    'judul_detail'  => $karakter . ' - Pertemuan ' . $i, // Hasil: "... - Pertemuan 1" dst.
                    'total_isi' => 1,         // Nilai default karena tipe CHECKLIST biasanya dihitung 1
                    'created_at'   => $now
                ];
            }
        }

        // 4. Lakukan Insert Massal ke tabel detail (Total: 27 x 6 = 162 baris)
        $this->table('master_materi_detail')->insert($dataInsert)->saveData();
    }
}
