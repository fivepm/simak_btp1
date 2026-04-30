<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserPasskeysTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     */
    public function change(): void
    {
        // Phinx otomatis membuat kolom 'id' sebagai Primary Key dan Auto Increment
        $table = $this->table('user_passkeys');
        
        $table->addColumn('user_id', 'string', ['limit' => 50, 'null' => false, 'comment' => 'ID dari tabel users atau guru'])
              ->addColumn('tipe_user', 'string', ['limit' => 50, 'null' => false, 'comment' => 'Penanda asal tabel user_id'])
              ->addColumn('credential_id', 'text', ['null' => false, 'comment' => 'Credential ID dari WebAuthn'])
              ->addColumn('public_key', 'text', ['null' => false, 'comment' => 'Public Key dari WebAuthn'])
              ->addColumn('nama_perangkat', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Nama device, misal: Chrome on Windows'])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('last_used_at', 'timestamp', ['null' => true])
              ->create();
    }
}