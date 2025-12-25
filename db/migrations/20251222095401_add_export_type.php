<?php

use Phinx\Migration\AbstractMigration;

final class AddExportType extends AbstractMigration
{
    public function change(): void
    {
        $this->execute("ALTER TABLE activity_logs MODIFY COLUMN action_type ENUM('LOGIN', 'LOGOUT', 'INSERT', 'UPDATE', 'DELETE', 'EXPORT', 'MAINTENANCE', 'OTHER') DEFAULT 'OTHER'");
    }
}
