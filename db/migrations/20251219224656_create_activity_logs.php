<?php

use Phinx\Migration\AbstractMigration;

final class CreateActivityLogs extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('activity_logs');
        $table->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('user_name', 'string', ['limit' => 100, 'default' => 'System'])
            ->addColumn('role', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('action_type', 'enum', ['values' => ['LOGIN', 'LOGOUT', 'INSERT', 'UPDATE', 'DELETE', 'OTHER'], 'default' => 'OTHER'])
            ->addColumn('description', 'text') // Contoh: "Menghapus data siswa ID 5"
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 255, 'null' => true]) // Info Browser
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['created_at'])
            ->addIndex(['user_id'])
            ->create();
    }
}
