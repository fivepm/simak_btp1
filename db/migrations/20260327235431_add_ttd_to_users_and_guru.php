<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTtdToUsersAndGuru extends AbstractMigration
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
        // 1. Tambahkan kolom ttd ke tabel users
        $tabelUsers = $this->table('users');
        if (!$tabelUsers->hasColumn('ttd')) {
            $tabelUsers->addColumn('ttd', 'string', [
                'limit' => 255,      // Cukup panjang untuk menyimpan nama file gambar (misal: ttd_admin_123.png)
                'null' => true,      // Boleh kosong karena tidak semua pengguna langsung punya TTD
                'default' => null,
                'after' => 'foto_profil', // Menempatkan kolom setelah foto_profil (opsional, sesuaikan dengan tabelmu)
                'comment' => 'Menyimpan nama file gambar tanda tangan digital pengguna'
            ])
                ->update();
        }

        // 2. Tambahkan kolom ttd ke tabel guru
        $tabelGuru = $this->table('guru');
        if (!$tabelGuru->hasColumn('ttd')) {
            $tabelGuru->addColumn('ttd', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'after' => 'nomor_wa', // Opsional
                'comment' => 'Menyimpan nama file gambar tanda tangan digital guru'
            ])
                ->update();
        }
    }
}
