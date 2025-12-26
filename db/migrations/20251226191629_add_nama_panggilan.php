<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNamaPanggilan extends AbstractMigration
{
    public function change(): void
    {
        $tables = ['users', 'guru'];

        foreach ($tables as $tableName) {
            $table = $this->table($tableName);

            if (!$table->hasColumn('nama_panggilan')) {
                $table->addColumn('nama_panggilan', 'string', ['limit' => 15, 'null' => true, 'after' => 'nama', 'comment' => 'Nama Panggilan'])
                    ->update();
            }
        }
    }
}
