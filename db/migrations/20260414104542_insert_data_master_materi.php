<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDataMasterMateri extends AbstractMigration
{
    public function change(): void
    {
        $data = [
            [
                'nama_kategori'  => "Makna Al-Qur'an",
                'tipe_input'     => 'RANGE',
                'satuan_default' => 'Ayat',
                'created_at'     => date('Y-m-d H:i:s')
            ],
            [
                'nama_kategori'  => "Makna Al-Hadist",
                'tipe_input'     => 'RANGE',
                'satuan_default' => 'Halaman',
                'created_at'     => date('Y-m-d H:i:s')
            ],
            [
                'nama_kategori'  => "29 Karakter Luhur",
                'tipe_input'     => 'CHECKLIST',
                'satuan_default' => null,
                'created_at'     => date('Y-m-d H:i:s')
            ]
        ];

        // 2. Insert ke tabel master_materi
        $this->table('master_materi')->insert($data)->saveData();
    }
}
