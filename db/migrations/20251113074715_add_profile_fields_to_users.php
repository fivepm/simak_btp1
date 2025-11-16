<?php

use Phinx\Migration\AbstractMigration;

class AddProfileFieldsToUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');

        // Tambahkan nomor_wa jika belum ada
        if (!$table->hasColumn('nomor_wa')) {
            $table->addColumn('nomor_wa', 'string', [
                'limit' => 20, // Sesuaikan limit jika perlu
                'null' => true,
                'after' => 'password', // Sesuaikan 'after' dengan kolom terakhir Anda
            ]);
        }

        // Tambahkan foto_profil jika belum ada
        if (!$table->hasColumn('foto_profil')) {
            $table->addColumn('foto_profil', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => 'default.png',
                // 'after' => 'nomor_wa' // Phinx akan menempatkannya setelah nomor_wa jika didefinisikan di atas
            ]);
        }

        // Simpan perubahan ke tabel
        $table->update();
    }
}
