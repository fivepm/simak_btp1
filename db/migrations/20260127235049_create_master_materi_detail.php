<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateMasterMateriDetail extends AbstractMigration
{
    public function change(): void
    {
        // Tabel Detail Materi (Anak dari Master Materi)
        // Contoh: Induk="Quran", Detail="Surat Al-Baqarah", Total Isi="286" (Ayat)
        $table = $this->table('master_materi_detail');
        $table->addColumn('master_materi_id', 'integer', ['signed' => false]) // Link ke master_materi
            ->addColumn('judul_detail', 'string', ['limit' => 255]) // Nama Surat / Nama Kitab
            ->addColumn('total_isi', 'integer', ['default' => 0]) // Total Ayat / Total Halaman
            ->addColumn('keterangan', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])

            // Foreign Key
            ->addForeignKey('master_materi_id', 'master_materi', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
