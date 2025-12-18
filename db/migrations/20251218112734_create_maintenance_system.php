<?php

use Phinx\Migration\AbstractMigration;

class CreateMaintenanceSystem extends AbstractMigration
{
    public function change()
    {
        // 1. Tabel Sesi Maintenance (Wadah Kegiatan)
        // PENTING: Tambahkan ['signed' => true] agar Primary Key 'id' pasti bertipe INT SIGNED (bukan Unsigned)
        // Ini memastikan tipe datanya cocok dengan kolom foreign key nanti.
        $sessions = $this->table('maintenance_sessions', ['signed' => true]);
        $sessions->addColumn('title', 'string', ['limit' => 255]) // Judul: "Update Raport V2"
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('status', 'enum', ['values' => ['planned', 'active', 'completed'], 'default' => 'planned'])
            ->addColumn('created_by', 'integer') // ID Admin
            ->addColumn('created_by_name', 'string', ['limit' => 100])
            ->addColumn('start_time', 'datetime', ['null' => true]) // Waktu tombol ON ditekan
            ->addColumn('end_time', 'datetime', ['null' => true])   // Waktu tombol OFF ditekan
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

        // 2. Tabel Tugas (Checklist)
        $tasks = $this->table('maintenance_tasks');
        // PENTING: session_id harus 'signed' => true agar cocok dengan id di tabel sessions
        $tasks->addColumn('session_id', 'integer', ['signed' => true, 'null' => false])
            ->addColumn('task_name', 'string', ['limit' => 255])
            ->addColumn('pic', 'string', ['limit' => 100, 'null' => true]) // Penanggung Jawab
            ->addColumn('is_completed', 'boolean', ['default' => 0])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('session_id', 'maintenance_sessions', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
