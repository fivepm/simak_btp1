<?php

use Phinx\Migration\AbstractMigration;

final class AddCancelledStatus extends AbstractMigration
{
    public function change(): void
    {
        $this->execute("ALTER TABLE maintenance_sessions MODIFY COLUMN status ENUM('planned', 'active', 'completed', 'cancelled') DEFAULT 'planned'");
    }
}
