<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddRoleBk extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        // Mengubah kolom 'level' pada tabel users untuk menambahkan 'bk'
        // Kita definisikan ulang seluruh enum value-nya
        $table = $this->table('users');
        $table->changeColumn('role', 'enum', [
            'values' => ['superadmin', 'admin', 'ketua pjp', 'guru', 'bk'], // Menambahkan 'bk'
            'null' => false
        ])->update();
    }
}
