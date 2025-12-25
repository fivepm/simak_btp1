<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUsulanFiturToLaporanDev extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Menambahkan kolom 'usulan_fitur' ke tabel 'laporan_developer'
     * setelah kolom 'catatan_teknis'.
     */
    public function change(): void
    {
        $table = $this->table('laporan_developer');

        if (!$table->hasColumn('usulan_fitur')) {
            $table->addColumn('usulan_fitur', 'text', [
                'null' => true,
                'after' => 'catatan_teknis', // Posisikan setelah catatan teknis
                'comment' => 'List usulan fitur untuk pengembangan selanjutnya'
            ])
                ->update();
        }
    }
}
