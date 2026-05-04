<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateJurnalTambahan extends AbstractMigration
{
    public function change(): void
    {
        // Tabel khusus untuk Materi Tambahan (Nasehat, Tamu, dll)
        $table = $this->table('jurnal_tambahan');
        $table->addColumn('jadwal_id', 'integer', ['signed' => false])
            ->addColumn('judul_materi', 'string', ['limit' => 255])
            ->addColumn('pemateri', 'string', ['limit' => 100])
            ->addColumn('keterangan', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
