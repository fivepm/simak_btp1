<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLastLoginToGuru extends AbstractMigration
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
        // 1. Tambahkan kolom last_login ke tabel guru
        $tabelGuru = $this->table('guru');
        // Pengecekan agar tidak error jika kolom sudah ada
        if (!$tabelGuru->hasColumn('last_login')) {
            $tabelGuru->addColumn('last_login', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'deleted_at', // Menempatkan kolom setelah deleted_at (opsional)
                'comment' => 'Mencatat waktu terakhir user berhasil login'
            ])
                ->update();
        }
    }
}
