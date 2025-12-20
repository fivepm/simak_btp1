<?php

use Phinx\Migration\AbstractMigration;

final class CreateBackupLogsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('backup_logs');
        $table->addColumn('admin_id', 'integer')
            ->addColumn('admin_name', 'string', ['limit' => 100])
            ->addColumn('filename', 'string', ['limit' => 255])
            ->addColumn('file_size', 'string', ['limit' => 50, 'null' => true]) // Simpan ukuran file juga
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['created_at']) // Index untuk sorting cepat
            ->create();
    }
}
