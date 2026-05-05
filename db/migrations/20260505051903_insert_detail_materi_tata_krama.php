<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDetailMateriTataKrama extends AbstractMigration
{
    public function change(): void
    {
        // 1. Cari ID dari kategori "Tata Krama" di tabel induk
        $row = $this->fetchRow("SELECT id FROM master_materi WHERE nama_kategori = 'Tata Krama' LIMIT 1");

        if (empty($row)) {
            echo "Peringatan: Kategori 'Tata Krama' tidak ditemukan di master_materi. Insert dibatalkan.\n";
            return;
        }

        $materiId = $row['id'];
        $now = date('Y-m-d H:i:s');

        // 2. Siapkan data list Tata Krama (Dikelompokkan berdasarkan Bab/Poin Utama)
        $daftarTataKrama = [
            'Tatakrama ta’dhim dan berbuat baik kepada kedua orangtua' => [
                'Mengucapkan salam/menyapa dan menjawab salam',
                'Berjabat tangan sambil menundukkan kepala dan mencium tangannya',
                'Apabila dipanggil segera menjawab dan mendatangi',
                'Bertutur kata dengan bahasa yang halus dan sopan (boso) serta dengan nada suara lebih rendah',
                'Mendengarkan dan memperhatikan ketika dinasehati',
                'Ketika berjalan didepan orangtua sedikit membungkuk',
                'Berpamitan ketika akan keluar rumah',
                'Bersyukur atas pemberian orangtua',
                'Tidak meminta sesuatu dengan sak dek sak nyet (seketika harus dikabulkan)',
                'Selalu mendoakan kedua orangtua',
                'Mendahulukan kedua orangtua pada saat makan dan minum',
                'Mengerjakan/ mentaati perintah-perintah orangtua',
                'Membantu meringankan kesibukan / pekerjaan orangtua',
                'Menjaga nama baik dan kehormatan kedua orangtua',
                'Mendahulukan kepentingan kedua orangtua daripada diri sendiri',
                'Tidak memaksakan kehendak (meminta sesuatu tanpa mempertimbangkan kemampuan kedua orangtua)',
                'Segera meminta maaf apabila melakukan kesalahan / menyakiti hati kedua orangtua',
                'Selalu meminta keridhoan dan meminta doa yang baik dari kedua orangtua',
                'Berusaha mewujudkan harapan dan cita-cita orangtua'
            ],
            'Tatakrama menghormati saudara yang lebih tua dan menyayangi saudara yang lebih muda' => [
                'Mengucapkan salam/menyapa dan menjawab salam',
                'Berjabat tangan sambil agak menundukkan kepala kepada yang lebih tua',
                'Segera menjawab dan mendatangi bila dipanggil',
                'Bertutur kata dengan bahasa yang baik dan sopan',
                'Saling mensyukuri',
                'Saling mendoakan'
            ],
            'Tatakrama menghormati dengan Mubaligh/Mubalighot/Guru' => [
                'Mengucapkan salam/menyapa dan menjawab salam',
                'Berjabat tangan dengan cium tangan (perempuan dengan muballighot dan laki-laki terhadap muballigh)',
                'Bertutur kata dengan bahasa yang halus dan sopan (boso)',
                'Mendengarkan dan memperhatikan ketika menerima materi pelajaran dari guru dan muballigh-muballighot',
                'Bila melintas didepannya mengucapkan permisi sambil agak membungkukkan badan',
                'Bersyukur dan mendoakan kepada guru dan muballigh-muballighot',
                'Bila menunjukkan sesuatu menggunakan ibu jari tangan kanan'
            ],
            'Tatakrama bergaul dengan teman' => [
                'Mengucapkan salam/menyapa',
                'Berjabat tangan dengan lembut ketika bertemu',
                'Bertutur kata dengan bahasa yang baik dan sopan (papan,empan,adepan)',
                'Menghindari gurauan yang berlebihan dan gojlok-gojlokan',
                'Tidak memanggil dengan panggilan/ julukan yang tidak baik/dibenci',
                'Tidak mengejek, menghina, menggunjing, maupun mengadu domba',
                'Menghargai pendapat dan karya teman'
            ],
            'Tatakrama ketika dimasjid' => [
                'Duduk tenang, tidak lari-lari, dan tidak ramai dalam masjid',
                'Tidak membawa mainan ke dalam masjid',
                'Duduk dengan tenang, tidak bergurau, dan tidak ramai dalam masjid'
            ],
            'Tatakrama ketika ditempat pengajian dan sekolah' => [
                'Menjaga kebersihan dan kerapian kelas',
                'Mengikuti proses pembelajaran dengan tenang dan penuh perhatian'
            ],
            'Tatakrama terhadap lingkungan dan alam sekitar' => [
                'Membuang sampah pada tempatnya',
                'Tidak merusak tanaman',
                'Tidak menyakiti hewan piaraan'
            ],
            'Tatakrama terhadap Ulil Amri' => [
                'Mengucapkan salam atau menyapa dengan ramah bila berpapasan',
                'Berjabat tangan sambil menundukkan kepala dan mencium tangannya',
                'Bertutur kata dengan bahasa yang halus,baik dan sopan (boso)',
                'Bila melintas di depannya mengucapkan permisi sambil agak membungkukkan badan',
                'Bila menunjukkan sesuatu menggunakan ibu jari tangan kanan',
                'Mendengarkan nasehat dan mengerjakan/mentaati perintah-perintahnya',
                'Bersyukur dan mendoakan kepada Ulil Amri'
            ],
            'Tatakrama bertamu/diajak bertamu dan kedatangan tamu' => [
                'Ketika bertamu/diajak bertamu berpakaian rapi, pantas, dan sopan, mengetuk pintu dengan mengucapkan salam, berjabat tangan dengan tuan rumah, duduk dengan sopan, tenang dan tidak lari-lari, makan dan minum setelah dipersilahkan tuan rumah, dan tidak merusak barang-barang milik tuan rumah',
                'Menyambut tamu dengan wajah ceria dan berjabat tangan sambil cium tangan, menghindari ikut nimbrung dalam pembicaraan tamu dan menjaga kenyamanan tamu'
            ],
            'Tatakrama berpakaian' => [
                'Memperkenalkan pakaian rapi,sopan dan benar menurut syariat islam',
                'Memperkenalkan ciri-ciri pakaian laki-laki dan perempuan'
            ],
            'Tatakrama tidur' => [
                'Membaca doa sebelum tidur dan setelah bangun tidur',
                'Tidur dengan terlentang atau miring ke kanan, dan tidak tengkurap',
                'Tidak tidur di tempat yang membahayakan',
                'Merapikan kembali tempat tidur setelah bangun tidur'
            ],
            'Tatakrama ketika menguap' => [
                'Menutup mulut',
                'Tidak bersuara dan tidak mengeluarkan kata-kata yang dibuat-buat'
            ],
            'Tatakrama ketika bersin' => [
                'Menutup mulut dan tidak mempermainkan suara',
                'Berdoa setelah bersin dan menjawab doanya orang bersin'
            ],
            'Tatakrama terhadap kerabat' => [
                'Mengenal silsilah dan menyambung kerabat',
                'Berjabat tangan dengan lembut ketika bertemu',
                'Bertutur kata dengan Bahasa yang halus, baik dan sopan (boso)',
                'Mengutamakan perhatian terhadap kerabat',
                'Akrab dan menyaudara',
                'Saling titip salam dan mendoakan'
            ],
            'Tatakrama terhadap tetangga' => [
                'Mengucapkan salam / menyapa dan mengucapkan dengan ramah',
                'Bertutur kata dengan bahasa yang halus, baik dan sopan (papan, empan, adepan)',
                'Tidak mengejek, menghina, menggunjing , maupun mengadu domba',
                'Bila meminjam sesuatu segera mengembalikan dengan baik',
                'Tidak kikir/ pelit',
                'Menghargai pemberian tetangga walaupun tidak menyukainya',
                'Tidak mengganggu tetangga yang sedang istirahat',
                'Menjenguknya apabila sakit',
                'Membantu kerepotan tetangga apabila punya hajat',
                'Tidak pamer, sehingga memancing /menimbulkan kecemburuan dan kedengkian',
                'Turut berkabung dan berbela sungkawa apabila ada tetangga yang meninggal (takziah)'
            ],
            'Tatakrama bersepeda' => [
                'Mengucapkan salam atau menyapa/ permisi ketika berpapasan dengan orang yang berjalan kaki/ sedang duduk',
                'Tidak ngebut/ ugal-ugalan dijalan, terutama ketika melewati gang',
                'Tidak berboncengan tiga anak atau lebih'
            ],
            'Tatakrama ketika makan Bersama' => [
                'Duduk dengan sopan',
                'Mendahulukan yang lebih tua',
                'Tidak mengambil makanan yang dididangkan dengan sendok yang sudah digunakan untuk makan',
                'Makan dengan tangan kanan',
                'Mengambil makanan secukupnya dan dihabiskan',
                'Dalam hal makan prasmanan hendaknya mengambil makanan sewajarnya, serta segera pergi untuk memberi kesempatan kepada yang lain yang bisa mengambil makanan dengan mudah',
                'Mengambil makanan yang terdekat',
                'Mengambil makanan di wadah dari yang paling tepi',
                'Tidak berbicara ketika mulut masih penuh makanan',
                'Mengunyah makanan dengan bibir tertutup, sehingga kunyahannya tidak bersuara',
                'Tidak memasukkan makanan ke mulut sebelum makanan di dalam mulut habis',
                'Tidak terdengar suara garpu dan piring',
                'Tidak melakukan hal-hal yang tabu (kentut,bersendawa dan berdahak)',
                'Ketika membersihkan makanan di gigi menutup mulut dengan tangan dan tidak membuangnya dihadapan orang lain.'
            ],
            'Tatakrama mencari ilmu' => [
                'Memakai pakaian yang rapi , bersih, dan suci .',
                'Membawa kitab yang dimanqulkan',
                'Menata dan merapikan tempat mengaji',
                'Datang lebih awal sebelum pengajian dimulai',
                'Duduk dengan rapi dan meletakkan kitab di meja belajar/ dampar',
                'Selama guru mengajar memperhatikan dan menghadap kearah guru / tidak nyingkur',
                'Tidak bergurau , ngobrol, makan dan minum serta tidak saur manuk',
                'Selesai pengajian mengembalikan meja / dampar dan merapikan tempat mengaji',
                'Bersyukur dan bersalaman dengan guru dan muballigh-muballighot (perempuan dengan muballighot dan laki-laki dengan muballigh)'
            ],
            'Tatakrama dalam kehidupan berumah tangga' => [
                'Bertutur kata serta bersikap yang baik, romantis, dan harmonis',
                'Menghargai dan mensyukuri kebaikan suami/istri',
                'Tidak mencela masakan/makanan yang disiapkan istri/suami',
                'Tidak menerima tamu laki-laki ketika suami tidak ada dirumah',
                'Tidak melakukan sesuatu yang dibenci suami/istri',
                'Menjaga privasi dan kehormatan suami/istri dan tidak pernah menceritakan/menanyakan seseorang yang pernah dicintai pada masa lalu',
                'Tidak memperlihatkan buku harian/foto yang dicintai di masa lalu'
            ],
            'Tatakrama dalam bermasyarakat' => [
                'Mengucapkan salam atau menyapa/ permisi ketika berpapasan dengan orang yang berjalan kaki/ sedang duduk di gang',
                'Berjabat tangan dengan lembut ketika bertamu',
                'Bertutur kata dengan bahasa yang halus, baik dan sopan (papan, empan, adepan)',
                'Memanggil dengan awalan sebutan yang baik (mbah, bapak, ibu, mas, mbak dll)',
                'Ketika berkendaraan tidak ngebut terutama ketika melewati gang / perkampungan',
                'Tidak menggeber-geberkan/ memainkan gas motor/menggunakan knalpot blombongan',
                'Tidak berboncengan tiga orang dewasa atau lebih',
                'Turut berkabung dan berbela sungkawa apabila ada yang meninggal dunia'
            ],
            'Tatakrama dalam lingkungan kerja' => [
                'Menetapi jujur, dan amanah',
                'Bisa rukun, kompak dan bekerjasama yang baik dengan sesama pekerja/karyawan',
                'Tidak bersaing dengan cara yang buruk antar sesama pekerja/ karyawan'
            ]
        ];

        // 3. Transformasi array menjadi format "Nama Poin. Poin (n) : Sub Poin"
        $dataInsert = [];
        foreach ($daftarTataKrama as $poin => $subPoins) {
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