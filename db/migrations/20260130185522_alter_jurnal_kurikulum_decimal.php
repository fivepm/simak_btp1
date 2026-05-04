<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AlterJurnalKurikulumDecimal extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        // 1. Update Tabel Master Target Pembelajaran
        // Mengubah kolom start/end/volume menjadi DECIMAL(10,2) agar bisa menampung angka koma (misal 2.5)
        $this->table('target_pembelajaran')
            ->changeColumn('target_start', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->changeColumn('target_end', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->changeColumn('total_volume', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->update();

        // 2. Update Tabel Jurnal Materi (Inputan Guru)
        // Mengubah kolom capaian menjadi DECIMAL(10,2)
        $this->table('jurnal_materi')
            ->changeColumn('capaian_start', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'null' => true])
            ->changeColumn('capaian_end', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'null' => true])
            ->changeColumn('volume_capaian', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->update();
    }
}
