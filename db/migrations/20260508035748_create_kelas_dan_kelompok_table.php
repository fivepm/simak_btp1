<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateKelasDanKelompokTable extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        // 1. Membuat Tabel Master Kelas (Tanpa tingkat)
        $tableKelas = $this->table('kelas', ['id' => true, 'primary_key' => 'id']);
        $tableKelas->addColumn('nama_kelas', 'string', ['limit' => 100, 'null' => false])
                   ->addColumn('keterangan', 'text', ['null' => true])
                   ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                   ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                   ->create();

        // 2. Membuat Tabel Master Kelompok (Tanpa desa_id)
        $tableKelompok = $this->table('kelompok', ['id' => true, 'primary_key' => 'id']);
        $tableKelompok->addColumn('nama_kelompok', 'string', ['limit' => 100, 'null' => false])
                      ->addColumn('keterangan', 'text', ['null' => true])
                      ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                      ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                      ->create();
    }
}