<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateWaliKelasTable extends AbstractMigration
{
    public function change(): void
    {
        // Membuat Tabel Pivot wali_kelas
        $table = $this->table('wali_kelas', ['id' => true, 'primary_key' => 'id']);
        
        $table->addColumn('id_guru', 'integer', ['null' => false, 'comment' => 'Relasi ke tabel guru'])
              ->addColumn('id_kelas', 'integer', ['null' => false, 'comment' => 'Relasi ke tabel kelas'])
              ->addColumn('kelompok', 'string', ['limit' => 100, 'null' => false, 'comment' => 'Nama kelompok tempat kelas ini berada'])
              ->addColumn('tahun_ajaran', 'string', ['limit' => 20, 'null' => false, 'default' => '2023/2024'])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->create();
    }
}