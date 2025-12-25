<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLaporanContributorsTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Membuat tabel pivot untuk menghubungkan satu laporan ke banyak developer.
     */
    public function change(): void
    {
        // Tabel ini tidak butuh kolom 'id' auto-increment, 
        // primary key-nya adalah kombinasi (laporan_id, user_id)
        $table = $this->table('laporan_contributors', ['id' => false, 'primary_key' => ['laporan_id', 'user_id']]);

        // PERBAIKAN: 
        // 1. Tambahkan 'null' => false
        // 2. Tambahkan 'signed' => true (agar cocok dengan default ID Phinx). 
        //    JIKA MASIH ERROR, GANTI MENJADI 'signed' => false (mungkin tabel induk Anda Unsigned).

        $table->addColumn('laporan_id', 'integer', [
            'null' => false,
            'signed' => false, // Sesuaikan ini dengan tipe ID tabel laporan_developer
            'comment' => 'ID dari tabel laporan_developer'
        ])
            ->addColumn('user_id', 'integer', [
                'null' => false,
                'signed' => true, // Sesuaikan ini dengan tipe ID tabel users
                'comment' => 'ID dari tabel users (developer)'
            ])

            // Foreign Key ke Laporan
            ->addForeignKey('laporan_id', 'laporan_developer', 'id', [
                'delete' => 'CASCADE', // Jika laporan dihapus, data kontributor ikut terhapus
                'update' => 'NO_ACTION'
            ])

            // Foreign Key ke Users
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE', // Jika user dihapus, dia hilang dari kontributor
                'update' => 'NO_ACTION'
            ])

            ->create();
    }
}
