<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeJudulDetailToText extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Tuliskan perubahan struktur tabel di dalam method ini.
     */
    public function change(): void
    {
        $table = $this->table('master_materi_detail');

        // Mengubah tipe kolom 'judul_detail' menjadi text
        $table->changeColumn('judul_detail', 'text', [
            'null' => false, // Ubah menjadi true jika kolom ini diizinkan kosong (NULL)
            // 'comment' => 'Diubah menjadi text untuk menampung kalimat panjang' // Opsional: Tambahkan komentar pada kolom
        ])
        ->update();
    }
}