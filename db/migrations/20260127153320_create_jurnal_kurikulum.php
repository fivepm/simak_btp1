<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateJurnalKurikulum extends AbstractMigration
{
    public function change(): void
    {
        // 1. Tabel Master Target Pembelajaran (Diisi Admin per Periode)
        $target = $this->table('target_pembelajaran');
        $target->addColumn('periode_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('kelompok', 'string', ['limit' => 100, 'null' => true]) // Sasaran (Caberawit/Pra-remaja)
            ->addColumn('kelas', 'string', ['limit' => 50, 'null' => true]) // Sasaran Kelas

            // PERUBAHAN: Kategori sekarang String (Dinamis), bukan Enum
            ->addColumn('kategori', 'string', ['limit' => 255])

            // Optional: Link ke ID Master Materi (jika ingin relasi kuat)
            ->addColumn('master_materi_id', 'integer', ['signed' => false, 'null' => true])

            ->addColumn('judul_materi', 'string', ['limit' => 255]) // Contoh: "Makna Al-Kahfi (56-110)"
            ->addColumn('tipe_input', 'enum', ['values' => ['RANGE', 'CHECKLIST', 'MANUAL'], 'default' => 'MANUAL'])
            ->addColumn('satuan', 'string', ['limit' => 50, 'null' => true]) // Ayat, Halaman, Surat

            // Kolom khusus tipe RANGE
            ->addColumn('target_start', 'integer', ['default' => 0])
            ->addColumn('target_end', 'integer', ['default' => 0])
            ->addColumn('total_volume', 'integer', ['default' => 0]) // (End - Start) + 1

            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])

            // Tambahkan FK jika tabel master_materi sudah ada (opsional, uncomment jika perlu)
            // ->addForeignKey('master_materi_id', 'master_materi', 'id', ['delete'=> 'SET_NULL', 'update'=> 'CASCADE'])

            ->create();

        // 2. Tabel Item Checklist (Sub-table untuk target tipe CHECKLIST)
        // Ini menyimpan poin-poin checklist jika targetnya tipe CHECKLIST
        $checklist = $this->table('target_checklist_items');
        $checklist->addColumn('target_id', 'integer', ['signed' => false])
            ->addColumn('nama_poin', 'string', ['limit' => 255]) // Contoh: "Berbicara sopan"
            ->addForeignKey('target_id', 'target_pembelajaran', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // 3. Tabel Transaksi Jurnal Materi (Apa yang diajarkan Guru hari ini)
        // Ini adalah tabel One-to-Many dari Jadwal Presensi
        $jurnal = $this->table('jurnal_materi');
        $jurnal->addColumn('jadwal_id', 'integer', ['signed' => false]) // Link ke jadwal_presensi
            ->addColumn('target_id', 'integer', ['signed' => false]) // Link ke target_pembelajaran

            // Jika tipe RANGE/MANUAL
            ->addColumn('capaian_start', 'integer', ['default' => 0, 'null' => true]) // Misal: Ayat 56
            ->addColumn('capaian_end', 'integer', ['default' => 0, 'null' => true])   // Misal: Ayat 65
            ->addColumn('volume_capaian', 'integer', ['default' => 0]) // Hasil hitungan (10 ayat)

            // Jika tipe CHECKLIST
            ->addColumn('checklist_item_id', 'integer', ['signed' => false, 'null' => true])

            ->addColumn('catatan_tambahan', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])

            // Foreign Keys
            ->addForeignKey('target_id', 'target_pembelajaran', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            // Pastikan tabel jadwal_presensi ada sebelum menjalankan ini
            // ->addForeignKey('jadwal_id', 'jadwal_presensi', 'id', ['delete'=> 'CASCADE', 'update'=> 'CASCADE']) 
            ->create();
    }
}
