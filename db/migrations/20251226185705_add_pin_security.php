<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPinSecurity extends AbstractMigration
{
    public function change(): void
    {
        // Default PIN: 123456 (Hash)
        $defaultPinHash = password_hash('354313', PASSWORD_DEFAULT);
        $tables = ['users', 'guru'];

        foreach ($tables as $tableName) {
            $table = $this->table($tableName);

            if (!$table->hasColumn('pin')) {
                $table->addColumn('pin', 'string', ['limit' => 255, 'default' => $defaultPinHash, 'after' => 'password', 'comment' => 'PIN 6 Digit (Hashed)'])
                    ->addColumn('failed_attempts', 'integer', ['default' => 0, 'after' => 'pin', 'comment' => 'Jumlah percobaan gagal'])
                    ->addColumn('last_attempt', 'datetime', ['null' => true, 'after' => 'failed_attempts', 'comment' => 'Waktu percobaan terakhir'])
                    ->addColumn('nama_panggilan', 'string', ['limit' => 15, 'null' => true, 'after' => 'nama', 'comment' => 'Nama Panggilan'])
                    ->update();
            }
        }
    }
}
