<?php

use Phinx\Migration\AbstractMigration;

class CreateSettingsTable extends AbstractMigration
{
    public function change()
    {
        // Buat tabel 'settings'
        $table = $this->table('settings', ['id' => false, 'primary_key' => ['setting_key']]);
        $table->addColumn('setting_key', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('setting_value', 'text', ['null' => true])
            ->create();

        // --- PENTING: Masukkan nilai default ---
        // Kita gunakan save() karena ini adalah data, bukan skema
        $defaultSettings = [
            [
                'setting_key' => 'maintenance_mode',
                'setting_value' => 'false'
            ],
            [
                'setting_key' => 'nama_sekolah',
                'setting_value' => 'SIMAK Banguntapan 1'
            ]
        ];

        // Gunakan bulkInsert() untuk efisiensi
        $this->table('settings')->insert($defaultSettings)->save();
    }
}
