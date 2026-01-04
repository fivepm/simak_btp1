<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSoftDeleteToGuru extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Metode ini digunakan untuk migrasi yang bisa di-reverse (rollback) otomatis.
     */
    public function change(): void
    {
        $table = $this->table('guru');

        // Pastikan kolom belum ada sebelum menambahkannya
        if (!$table->hasColumn('deleted_at')) {
            $table->addColumn('deleted_at', 'datetime', [
                'null' => true,     // Wajib NULL agar status defaultnya 'Aktif'
                'default' => null,
                'comment' => 'Diisi timestamp jika data dihapus (Soft Delete)'
            ])
                ->update();
        }
    }
}
