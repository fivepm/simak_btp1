<?php

use Phinx\Migration\AbstractMigration;

class AddFotoProfilToGuru extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('guru');

        // Cek jika kolom belum ada sebelum menambahkan
        if (!$table->hasColumn('foto_profil')) {
            $table->addColumn('foto_profil', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => 'default.png', // Default
                'after' => 'nomor_wa', // Menempatkan setelah nomor_wa
            ])
                ->update(); // Gunakan update() untuk tabel yang sudah ada
        }
    }
}
