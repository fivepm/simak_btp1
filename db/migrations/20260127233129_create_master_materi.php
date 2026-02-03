<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateMasterMateri extends AbstractMigration
{
    public function change(): void
    {
        // Tabel Master Materi (Daftar Mata Pelajaran / Kategori)
        // Contoh Data: "Makna Quran", "Makna Hadist", "Hafalan", "Tata Krama"
        $table = $this->table('master_materi');
        $table->addColumn('nama_kategori', 'string', ['limit' => 255]) // Nama Mapel
            ->addColumn('tipe_input', 'enum', ['values' => ['RANGE', 'CHECKLIST', 'MANUAL'], 'default' => 'MANUAL'])
            ->addColumn('satuan_default', 'string', ['limit' => 50, 'null' => true]) // Contoh: Ayat, Halaman
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
