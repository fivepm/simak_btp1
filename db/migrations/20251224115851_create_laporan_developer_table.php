<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLaporanDeveloperTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('laporan_developer');
        $table->addColumn('tanggal_laporan', 'date')
            ->addColumn('periode_awal', 'date')
            ->addColumn('periode_akhir', 'date')
            ->addColumn('summary', 'text', ['comment' => 'Ringkasan eksekutif laporan'])
            ->addColumn('fitur_selesai', 'text', ['null' => true, 'comment' => 'List fitur yang sudah selesai (bisa JSON atau text)'])
            ->addColumn('pekerjaan_berjalan', 'text', ['null' => true, 'comment' => 'List pekerjaan in-progress'])
            ->addColumn('kendala_teknis', 'text', ['null' => true, 'comment' => 'Isu atau blocker'])
            ->addColumn('catatan_teknis', 'text', ['null' => true, 'comment' => 'Catatan perubahan DB/Library'])
            ->addColumn('dibuat_oleh', 'integer', ['comment' => 'ID User yang membuat laporan'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])

            // Pastikan tabel 'users' sudah ada sebelum menjalankan migrasi ini
            ->addForeignKey('dibuat_oleh', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_laporan_developer_users'
            ])
            ->create();
    }
}
