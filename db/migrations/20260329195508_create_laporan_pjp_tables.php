<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLaporanPjpTables extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function change(): void
    {
        // 1. Membuat Tabel Laporan PJP Kelompok
        $tableKelompok = $this->table('laporan_pjp_kelompok');
        $tableKelompok->addColumn('periode_id', 'integer', ['signed' => false, 'comment' => 'ID dari tabel periode'])
            ->addColumn('kelompok_id', 'integer', ['signed' => false, 'comment' => 'ID dari tabel kelompok'])
            ->addColumn('status', 'enum', [
                'values' => ['DRAFT', 'FINAL', 'TTD_KETUA'],
                'default' => 'DRAFT',
                'comment' => 'Status alur persetujuan dokumen'
            ])
            ->addColumn('checklist_musyawarah', 'json', ['null' => true, 'comment' => 'Snapshot status musyawarah PJP dan 5 Unsur'])
            ->addColumn('data_kepengurusan', 'json', ['null' => true, 'comment' => 'Snapshot data wali kelas dan pengurus saat itu'])
            ->addColumn('detail_kelas', 'json', ['null' => true, 'comment' => 'Snapshot jml siswa, guru, kehadiran, capaian materi, dan input manual'])
            ->addColumn('permasalahan', 'json', ['null' => true, 'comment' => 'Array poin-poin permasalahan yang diinput manual'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('ttd_at', 'datetime', ['null' => true, 'comment' => 'Waktu kapan Ketua PJP Kelompok menekan tombol TTD'])
            // Index unik agar tidak ada laporan ganda untuk kelompok yang sama di satu periode
            ->addIndex(['periode_id', 'kelompok_id'], ['unique' => true, 'name' => 'idx_unik_laporan_kelompok'])
            ->create();

        // 2. Membuat Tabel Laporan PJP Desa
        $tableDesa = $this->table('laporan_pjp_desa');
        $tableDesa->addColumn('periode_id', 'integer', ['signed' => false, 'comment' => 'ID dari tabel periode'])
            ->addColumn('status', 'enum', [
                'values' => ['DRAFT', 'FINAL', 'TTD_KETUA'],
                'default' => 'DRAFT',
                'comment' => 'Status alur persetujuan dokumen desa'
            ])
            ->addColumn('data_kepengurusan_desa', 'json', ['null' => true, 'comment' => 'Snapshot data pengurus tingkat desa'])
            ->addColumn('rekap_kelompok', 'json', ['null' => true, 'comment' => 'Snapshot gabungan rata-rata seluruh kelompok yang sudah TTD'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('ttd_at', 'datetime', ['null' => true, 'comment' => 'Waktu kapan Ketua PJP Desa menekan tombol TTD'])
            // Index unik agar tidak ada laporan desa ganda di satu periode
            ->addIndex(['periode_id'], ['unique' => true, 'name' => 'idx_unik_laporan_desa'])
            ->create();
    }
}
