<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InsertDataKelasDanKelompok extends AbstractMigration
{
    /**
     * Migrate Up.
     * Dijalankan saat perintah: vendor/bin/phinx migrate
     */
    public function up(): void
    {
        // 1. Data untuk tabel kelas
        $dataKelas = [
            ['nama_kelas' => 'PAUD'],
            ['nama_kelas' => 'Caberawit A'],
            ['nama_kelas' => 'Caberawit B'],
            ['nama_kelas' => 'Pra Remaja'],
            ['nama_kelas' => 'Remaja'],
            ['nama_kelas' => 'Pra Nikah'],
        ];

        // Eksekusi insert ke tabel kelas
        $tableKelas = $this->table('kelas');
        $tableKelas->insert($dataKelas)->saveData();


        // 2. Data untuk tabel kelompok
        $dataKelompok = [
            ['nama_kelompok' => 'Bintaran'],
            ['nama_kelompok' => 'Gedongkuning'],
            ['nama_kelompok' => 'Jombor'],
            ['nama_kelompok' => 'Sunten'],
        ];

        // Eksekusi insert ke tabel kelompok
        $tableKelompok = $this->table('kelompok');
        $tableKelompok->insert($dataKelompok)->saveData();
    }

    /**
     * Migrate Down.
     * Dijalankan saat perintah: vendor/bin/phinx rollback
     */
    public function down(): void
    {
        // Hapus data kelas yang barusan di-insert
        $this->execute("DELETE FROM kelas WHERE nama_kelas IN ('PAUD', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah')");
        
        // Hapus data kelompok yang barusan di-insert
        $this->execute("DELETE FROM kelompok WHERE nama_kelompok IN ('Bintaran', 'Gedongkuning', 'Jombor', 'Sunten')");
    }
}