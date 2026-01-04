<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TabelPengampuSimple extends AbstractMigration
{
    public function change(): void
    {
        // Buat tabel 'pengampu'
        $table = $this->table('pengampu', ['id' => false, 'primary_key' => ['id_pengampu']]);

        $table->addColumn('id_pengampu', 'integer', ['signed' => false, 'identity' => true])

            // ID GURU (Relasi ke tabel guru)
            // Saya set signed => true (Default MySQL/phpMyAdmin)
            ->addColumn('id_guru', 'integer', ['signed' => true])

            // NAMA KELAS (Langsung String/Varchar)
            ->addColumn('nama_kelas', 'string', ['limit' => 100])

            // Tambahkan Foreign Key ke Guru (Biar kalau guru dihapus, data ini hilang)
            ->addForeignKey('id_guru', 'guru', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])

            ->create();
    }
}
