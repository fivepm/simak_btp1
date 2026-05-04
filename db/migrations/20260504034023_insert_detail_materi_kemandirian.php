<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriKemandirian extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Kemandirian" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Kemandirian' LIMIT 1");

        if (empty($row)) {
            echo "Peringatan: Kategori 'Kemandirian' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Kemandirian (Kelompokkan berdasarkan Nama Poin)
        $daftarKemandirian = [
            'Kemandirian Pribadi' => [
                'Makan-minum secara mandiri',
                'Mandi secara mandiri',
                'Buang air (kencing dan berak) secara mandiri',
                'Memakai pakaian yang benar',
                'Menempatkan pakaian bersih dan kotor pada tempatnya (tidak berserakan)',
                'Menyiapkan peralatan sekolah dan mengaji secara mandiri',
                'Berlatih mengatur waktu ibadah, mengaji, belajar, bermain dan istirahat',
                'Mencuci peralatan makannya sendiri (piring,sendok,gelas,dll)',
                'Menyiapkan peralatan sekolah dan mengaji secara mandiri',
                'Melipat dan menata pakaian dalam almari',
                'Mengurangi kebiasaan jajan',
                'Membiasakan menabung',
                'Mencuci , menjemur dan menyetrika pakaian',
                'Mencuci dan membersihkan sepeda',
                'Terampil mengatur waktu ibadah, mengaji, belajar, bermain dan istirahat',
                'Mencuci, membersihkan dan merawat motor/mobil (mengontrol air radiator, air wiper, air accu, oli mesin)',
                'Perawatan diri (bersih, rapi/tidak koproh dan segar)'
            ],
            'Kemandirian Dalam Keluarga' => [
                'Meletakkan peralatan makan yang habis dipakai ditempatnya',
                'Meletakkan pakain kotor ditempatnya',
                'Membuang sampah pada tempatnya',
                'Merapikan tempat tidur',
                'Menata buku/kitab, peralatan belajar dan tempat belajar',
                'Menyapu lantai rumah',
                'Membantu kerepotan orang tua (menyesuaikan)',
                'Membantu mengasuh adik',
                'Membersihkan kamar mandi dan menguras bak mandi',
                'Membersihkan rumah',
                'Menata barang-barang dalam rumah',
                'Menyiram tanaman',
                'Merawat hewan piaraan',
                'Mencuci, menjemur, dan menyetrika pakaian',
                'Membantu kerepotan orangtua, seperti: membetulkan genteng yang bocor, memasang/mengganti ban mobil,dll.',
                'Mempersiapkan diri sebagai suami yang sholih dan pemimpin rumah tangga yang bertanggung jawab',
                'Mempersiapkan diri sebagai istri yang sholihah dan ibu rumah tangga yang baik'
            ],
            'Kemandirian Dalam Lingkungan Jamaah dan Sekolah' => [
                'Bisa menata dan merapikan perlengkapan pengajian dan sekolah',
                'Tanggap dengan hal-hal yang isrof dan mubadzir (memadamkan lampu, mematikan kipas angin/ AC, menutup kran air ketika sudah tidak diperlukan)',
                'Terampil membantu menyiapkan sarana kegiatan amrin jami’',
                'Peduli terhadap kebersihan lingkungan sarana-prasarana sabilillah (masjid, aula, kamar mandi, kamar tamu, kantor)',
                'Peduli terhadap keamanan dan kebersihan lingkungan jamaah',
                'Terampil beramal sholih dan memiliki kesiapan sebagai penerus perjuangan jamaah,dll.'
            ],
            'Kemandirian Dalam Lingkungan Umum/Masyarakat' => [
                'Mempunyai jiwa kegotong-royongan dalam masyarakat',
                'Berani mengurus surat-menyurat pada instansi pemerintah (mengurus KTP, KK, SIM, STNK, surat proses pernikahan, passport),dll.',
                'Mandiri dalam berkomunikasi dengan tokoh masyarakat dan tokoh agama'
            ],
            'Keterampilan Generus Putra' => [
                'Mengenal instalasi listrik',
                'Mengenal peralatan servis sepeda',
                'Membuat minuman teh, kopi, susu dan yang sejenisnya',
                'Belajar membuat karya sederhana melalui gadget yang dimiliki',
                'Memanfaatkan gadget untuk penjualan online',
                'Memiliki keterampilan sesuai dengan bakat dan minat (potong rambut, servis motor, servis HP/elektronik),dll.',
                'Memanfaatkan gadget untuk jualan online',
                'Menumbuhkan jiwa berwiraswasta dan berani mencoba',
                'Berwiraswasta dan berpenghasilan',
                'Pandai membaca dan mencari peluang usaha yang menghasilkan',
                'Bisa mengajukan lamaran pekerjaan',
                'Bekerja dan berpenghasilan dengan menerapkan prinsip kerja bener, kurup, janji',
                'Berinvestasi yang aman, menghasilkan dan halal'
            ],
            'Keterampilan Generus Putri' => [
                'Mengenal bumbu masak (bumbon-bumbon)',
                'Memasak air, nasi, mie instan, menggoreng telur, dan yang sejenis',
                'Membuat minuman the, kopi, susu dan yang sejenisnya',
                'Belajar membuat karya sederhana melalui gadget yang dimiliki',
                'Mengenal make up sederhana',
                'Belajar memasak',
                'Memanfaatkan gadget untuk jualan online',
                'Belajar menjahit',
                'Belajar kerajinan tangan (handycraft)',
                'Memiliki keterampilan sesuai dengan bakat dan minat (membikin olahan kuliner, kue, minuman, jamu instan),dll.',
                'Terampil memasak menu sehari-hari',
                'Menumbuhkan jiwa berwiraswasta dan berani mencoba',
                'Berwiraswasta dan berpenghasilan',
                'Memanfaatkan gadget untuk jualan online',
                'Pandai membaca dan mencari peluang usaha yang menghasilkan',
                'Berinvestasi yang aman, menghasilkan dan halal'
            ]
        ];

        // 3. Transformasi array menjadi format "Nama Poin. Poin (n) : Sub Poin"
        $dataInsert = [];
        foreach ($daftarKemandirian as $poin => $subPoins) {
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