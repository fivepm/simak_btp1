<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialSchema extends AbstractMigration
{
    /**
     * Metode 'up()' dijalankan saat Anda 'migrate'.
     * Kita akan menaruh SQL mentah kita di sini.
     */
    public function up(): void
    {
        // 1. Ambil SQL dari export phpMyAdmin Anda
        // (Pastikan hanya 'Structure'/'Struktur' yang diexport)
        $sql = "
            
        CREATE TABLE `balasan_wa` (
            `id` int NOT NULL,
            `nomor_pengirim` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
            `isi_balasan` text,
            `timestamp_balasan` datetime NOT NULL,
            `nama_pengirim` varchar(255) DEFAULT NULL,
            `id_grup` varchar(100) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `catatan_bk` (
            `id` int NOT NULL,
            `peserta_id` int NOT NULL,
            `tanggal_catatan` date NOT NULL,
            `permasalahan` text NOT NULL,
            `tindak_lanjut` text,
            `dicatat_oleh_user_id` int DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `grup_whatsapp` (
            `id` int NOT NULL,
            `nama_grup` varchar(255) NOT NULL,
            `kelompok` varchar(50) DEFAULT NULL,
            `kelas` varchar(50) DEFAULT NULL,
            `group_id` varchar(255) NOT NULL,
            `keterangan` text,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `guru` (
            `id` int NOT NULL,
            `nama` varchar(255) NOT NULL,
            `kelompok` varchar(50) NOT NULL,
            `kelas` varchar(50) NOT NULL,
            `tingkat` varchar(50) NOT NULL,
            `barcode` varchar(255) NOT NULL,
            `username` varchar(255) NOT NULL,
            `password` varchar(255) NOT NULL,
            `nomor_wa` varchar(20) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `jadwal_guru` (
            `id` int NOT NULL,
            `jadwal_id` int NOT NULL,
            `guru_id` int NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `jadwal_penasehat` (
            `id` int NOT NULL,
            `jadwal_id` int NOT NULL,
            `penasehat_id` int NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `jadwal_presensi` (
            `id` int NOT NULL,
            `periode_id` int NOT NULL,
            `kelas` varchar(50) NOT NULL,
            `kelompok` enum('bintaran','gedongkuning','jombor','sunten') NOT NULL,
            `tanggal` date NOT NULL,
            `jam_mulai` time NOT NULL,
            `jam_selesai` time NOT NULL,
            `pengajar` varchar(255) DEFAULT NULL,
            `materi1` text,
            `materi2` text,
            `materi3` text,
            `status_pengingat` enum('Belum Dikirim','Sudah Dikirim') NOT NULL DEFAULT 'Belum Dikirim'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `kehadiran_musyawarah` (
            `id` int NOT NULL,
            `id_musyawarah` int NOT NULL,
            `nama_peserta` varchar(255) NOT NULL,
            `jabatan` varchar(255) DEFAULT NULL,
            `status` enum('Hadir','Izin','Tanpa Keterangan') NOT NULL DEFAULT 'Tanpa Keterangan',
            `urutan` int DEFAULT '99'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `kepengurusan` (
            `id` int NOT NULL,
            `nama_pengurus` varchar(255) NOT NULL,
            `jabatan` varchar(50) NOT NULL,
            `tingkat` enum('desa','kelompok') NOT NULL,
            `kelompok` enum('bintaran','gedongkuning','jombor','sunten') DEFAULT NULL,
            `kelas` varchar(50) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `kurikulum_hafalan` (
            `id` int NOT NULL,
            `kelas` varchar(50) NOT NULL,
            `materi_id` int NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `laporan_harian` (
            `id` int NOT NULL,
            `tanggal_laporan` date NOT NULL,
            `id_admin_pembuat` int DEFAULT NULL,
            `nama_admin_pembuat` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
            `timestamp_dibuat` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `timestamp_diperbarui` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `status_laporan` enum('Draft','Final') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Draft',
            `data_statistik` json NOT NULL,
            `catatan_kondisi` text COLLATE utf8mb4_general_ci,
            `rekomendasi_tindakan` text COLLATE utf8mb4_general_ci,
            `tindak_lanjut_ketua` text COLLATE utf8mb4_general_ci
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `laporan_mingguan` (
            `id` int NOT NULL,
            `tanggal_mulai` date NOT NULL COMMENT 'Senin',
            `tanggal_akhir` date NOT NULL COMMENT 'Minggu',
            `id_admin_pembuat` int DEFAULT NULL,
            `nama_admin_pembuat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `timestamp_dibuat` datetime DEFAULT NULL,
            `timestamp_diperbarui` datetime DEFAULT NULL,
            `status_laporan` enum('Draft','Final') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
            `data_statistik` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'JSON snapshot data mingguan',
            `catatan_kondisi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            `rekomendasi_tindakan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            `tindak_lanjut_ketua` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        ) ;

        CREATE TABLE `log_pesan_wa` (
            `id` int NOT NULL,
            `fonnte_id` varchar(100) DEFAULT NULL,
            `nomor_tujuan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
            `tipe_penerima` varchar(50) DEFAULT NULL,
            `isi_pesan` text,
            `status_kirim` enum('Terkirim','Diterima','Dibaca','Gagal') DEFAULT 'Terkirim',
            `timestamp_kirim` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `timestamp_update` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `materi_file` (
            `id` int NOT NULL,
            `poin_id` int NOT NULL,
            `nama_file_asli` varchar(255) NOT NULL,
            `path_file` varchar(255) NOT NULL,
            `tipe_file` varchar(100) DEFAULT NULL,
            `ukuran_file` int DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `materi_hafalan` (
            `id` int NOT NULL,
            `kategori` enum('Surat','Doa','Dalil') NOT NULL,
            `nama_materi` varchar(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `materi_induk` (
            `id` int NOT NULL,
            `kategori_id` int NOT NULL,
            `judul_materi` varchar(255) NOT NULL,
            `deskripsi` text
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `materi_kategori` (
            `id` int NOT NULL,
            `nama_kategori` varchar(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `materi_poin` (
            `id` int NOT NULL,
            `materi_induk_id` int NOT NULL,
            `parent_id` int DEFAULT NULL,
            `nama_poin` varchar(255) NOT NULL,
            `urutan` int DEFAULT '0'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `materi_video` (
            `id` int NOT NULL,
            `poin_id` int NOT NULL,
            `url_video` varchar(255) NOT NULL,
            `deskripsi_video` varchar(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `musyawarah` (
            `id` int NOT NULL,
            `nama_musyawarah` varchar(255) NOT NULL,
            `tanggal` date NOT NULL,
            `waktu_mulai` time NOT NULL,
            `pimpinan_rapat` varchar(100) DEFAULT NULL,
            `tempat` varchar(150) DEFAULT NULL,
            `notulensi` longtext,
            `status` enum('Terjadwal','Selesai','Dibatalkan') NOT NULL DEFAULT 'Terjadwal',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `musyawarah_laporan_kelompok` (
            `id` int NOT NULL,
            `id_musyawarah` int NOT NULL,
            `nama_kelompok` varchar(100) NOT NULL,
            `isi_laporan` text,
            `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `musyawarah_laporan_kmm` (
            `id` int NOT NULL,
            `id_musyawarah` int NOT NULL,
            `nama_kmm` varchar(100) NOT NULL,
            `isi_laporan` text,
            `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `notulensi_poin` (
            `id` int NOT NULL,
            `id_musyawarah` int NOT NULL,
            `poin_pembahasan` text NOT NULL,
            `status_evaluasi` enum('Belum Dievaluasi','Terlaksana','Belum Terlaksana') NOT NULL DEFAULT 'Belum Dievaluasi',
            `keterangan` text
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `penasehat` (
            `id` int NOT NULL,
            `nama` varchar(255) NOT NULL,
            `nomor_wa` varchar(20) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `pengaturan_pengingat` (
            `id` int NOT NULL,
            `kelompok` varchar(50) NOT NULL,
            `kelas` varchar(50) NOT NULL,
            `waktu_pengingat_jam` int NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `pengumuman_template` (
            `id` int NOT NULL,
            `judul_template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `isi_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE `periode` (
            `id` int NOT NULL,
            `nama_periode` varchar(255) NOT NULL,
            `tanggal_mulai` date NOT NULL,
            `tanggal_selesai` date NOT NULL,
            `status` enum('Aktif','Selesai','Arsip') NOT NULL DEFAULT 'Aktif',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `pesan_terjadwal` (
            `id` int NOT NULL,
            `jadwal_id` int DEFAULT NULL,
            `tipe_penerima` varchar(20) DEFAULT NULL,
            `penerima_id` int DEFAULT NULL,
            `nomor_tujuan` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
            `isi_pesan` text NOT NULL,
            `waktu_kirim` datetime NOT NULL,
            `status` enum('pending','terkirim','gagal') NOT NULL DEFAULT 'pending',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `peserta` (
            `id` int NOT NULL,
            `kelompok` enum('bintaran','gedongkuning','jombor','sunten') NOT NULL,
            `nama_lengkap` varchar(255) NOT NULL,
            `kelas` varchar(50) DEFAULT NULL,
            `jenis_kelamin` enum('Laki-laki','Perempuan') NOT NULL,
            `tempat_lahir` varchar(100) DEFAULT NULL,
            `tanggal_lahir` date DEFAULT NULL,
            `nomor_hp` varchar(20) DEFAULT NULL,
            `status` enum('Aktif','Tidak Aktif','Lulus') NOT NULL DEFAULT 'Aktif',
            `nama_orang_tua` varchar(255) DEFAULT NULL,
            `nomor_hp_orang_tua` varchar(20) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `progres_hafalan` (
            `id` int NOT NULL,
            `peserta_id` int NOT NULL,
            `materi_id` int NOT NULL,
            `status_hafalan` tinyint(1) NOT NULL DEFAULT '0',
            `tanggal_hafal` date DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `rekap_presensi` (
            `id` int NOT NULL,
            `jadwal_id` int NOT NULL,
            `peserta_id` int NOT NULL,
            `status_kehadiran` enum('Hadir','Izin','Sakit','Alpa','Belum Absen') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
            `keterangan` text,
            `kirim_wa` enum('no','yes') NOT NULL DEFAULT 'no',
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `template_pesan` (
            `id` int NOT NULL,
            `tipe_pesan` varchar(50) NOT NULL,
            `kelas` varchar(50) NOT NULL,
            `kelompok` varchar(50) DEFAULT NULL,
            `template` text NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        CREATE TABLE `users` (
            `id` int NOT NULL,
            `nama` varchar(255) NOT NULL,
            `kelompok` enum('bintaran','gedongkuning','jombor','sunten') NOT NULL,
            `role` enum('superadmin','admin','ketua pjp','guru') NOT NULL,
            `tingkat` enum('desa','kelompok') NOT NULL,
            `barcode` varchar(255) NOT NULL,
            `username` varchar(255) NOT NULL,
            `password` varchar(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

        ALTER TABLE `balasan_wa`
            ADD PRIMARY KEY (`id`);

        ALTER TABLE `catatan_bk`
            ADD PRIMARY KEY (`id`),
            ADD KEY `peserta_id` (`peserta_id`),
            ADD KEY `dicatat_oleh_user_id` (`dicatat_oleh_user_id`);

        ALTER TABLE `grup_whatsapp`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `group_id` (`group_id`);

        ALTER TABLE `guru`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `barcode` (`barcode`),
            ADD UNIQUE KEY `username` (`username`);

        ALTER TABLE `jadwal_guru`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `unique_jadwal_guru` (`jadwal_id`,`guru_id`),
            ADD KEY `guru_id` (`guru_id`);

        ALTER TABLE `jadwal_penasehat`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `unique_jadwal_penasehat` (`jadwal_id`,`penasehat_id`),
            ADD KEY `penasehat_id` (`penasehat_id`);

        ALTER TABLE `jadwal_presensi`
            ADD PRIMARY KEY (`id`),
            ADD KEY `periode_id` (`periode_id`);

        ALTER TABLE `kehadiran_musyawarah`
            ADD PRIMARY KEY (`id`),
            ADD KEY `id_musyawarah` (`id_musyawarah`);

        ALTER TABLE `kepengurusan`
            ADD PRIMARY KEY (`id`);

        ALTER TABLE `kurikulum_hafalan`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `kelas` (`kelas`,`materi_id`),
            ADD KEY `materi_id` (`materi_id`);

        ALTER TABLE `laporan_harian`
            ADD PRIMARY KEY (`id`),
            ADD KEY `tanggal_laporan` (`tanggal_laporan`);

        ALTER TABLE `laporan_mingguan`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `idx_tanggal_mulai_unik` (`tanggal_mulai`) COMMENT 'Hanya boleh ada 1 laporan per minggu (berdasarkan Senin)';

        ALTER TABLE `log_pesan_wa`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `fonnte_id` (`fonnte_id`);

        ALTER TABLE `materi_file`
            ADD PRIMARY KEY (`id`),
            ADD KEY `poin_id` (`poin_id`);

        ALTER TABLE `materi_hafalan`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `kategori` (`kategori`,`nama_materi`);

        ALTER TABLE `materi_induk`
            ADD PRIMARY KEY (`id`),
            ADD KEY `kategori_id` (`kategori_id`);

        ALTER TABLE `materi_kategori`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `nama_kategori` (`nama_kategori`);

        ALTER TABLE `materi_poin`
            ADD PRIMARY KEY (`id`),
            ADD KEY `materi_induk_id` (`materi_induk_id`),
            ADD KEY `fk_parent_poin` (`parent_id`);

        ALTER TABLE `materi_video`
            ADD PRIMARY KEY (`id`),
            ADD KEY `fk_video_poin` (`poin_id`);

        ALTER TABLE `musyawarah`
            ADD PRIMARY KEY (`id`);

        ALTER TABLE `musyawarah_laporan_kelompok`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `unique_report` (`id_musyawarah`,`nama_kelompok`);

        ALTER TABLE `musyawarah_laporan_kmm`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `unique_kmm_report` (`id_musyawarah`,`nama_kmm`);

        ALTER TABLE `notulensi_poin`
            ADD PRIMARY KEY (`id`),
            ADD KEY `id_musyawarah` (`id_musyawarah`);

        ALTER TABLE `penasehat`
            ADD PRIMARY KEY (`id`);

        ALTER TABLE `pengaturan_pengingat`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `unik_aturan` (`kelompok`,`kelas`);

        ALTER TABLE `pengumuman_template`
            ADD PRIMARY KEY (`id`);

        ALTER TABLE `periode`
            ADD PRIMARY KEY (`id`);

        ALTER TABLE `pesan_terjadwal`
            ADD PRIMARY KEY (`id`),
            ADD KEY `idx_jadwal_id` (`jadwal_id`);

        ALTER TABLE `peserta`
            ADD PRIMARY KEY (`id`);

        ALTER TABLE `progres_hafalan`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `peserta_id` (`peserta_id`,`materi_id`),
            ADD KEY `materi_id` (`materi_id`);

        ALTER TABLE `rekap_presensi`
            ADD PRIMARY KEY (`id`),
            ADD KEY `jadwal_id` (`jadwal_id`),
            ADD KEY `peserta_id` (`peserta_id`);

        ALTER TABLE `template_pesan`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `unique_template` (`tipe_pesan`,`kelas`,`kelompok`);

        ALTER TABLE `users`
            ADD PRIMARY KEY (`id`),
            ADD UNIQUE KEY `username` (`username`);

        ALTER TABLE `balasan_wa`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `catatan_bk`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `grup_whatsapp`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `guru`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `jadwal_guru`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `jadwal_penasehat`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `jadwal_presensi`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `kehadiran_musyawarah`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `kepengurusan`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `kurikulum_hafalan`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `laporan_harian`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `laporan_mingguan`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `log_pesan_wa`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `materi_file`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `materi_hafalan`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `materi_induk`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `materi_kategori`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `materi_poin`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `materi_video`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `musyawarah`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `musyawarah_laporan_kelompok`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `musyawarah_laporan_kmm`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `notulensi_poin`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `penasehat`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `pengaturan_pengingat`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `pengumuman_template`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `periode`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `pesan_terjadwal`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `peserta`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `progres_hafalan`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `rekap_presensi`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `template_pesan`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `users`
            MODIFY `id` int NOT NULL AUTO_INCREMENT;

        ALTER TABLE `catatan_bk`
            ADD CONSTRAINT `catatan_bk_ibfk_1` FOREIGN KEY (`peserta_id`) REFERENCES `peserta` (`id`) ON DELETE CASCADE;

        ALTER TABLE `jadwal_guru`
            ADD CONSTRAINT `jadwal_guru_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_presensi` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `jadwal_guru_ibfk_2` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE;

        ALTER TABLE `jadwal_penasehat`
            ADD CONSTRAINT `jadwal_penasehat_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_presensi` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `jadwal_penasehat_ibfk_2` FOREIGN KEY (`penasehat_id`) REFERENCES `penasehat` (`id`) ON DELETE CASCADE;

        ALTER TABLE `jadwal_presensi`
            ADD CONSTRAINT `jadwal_presensi_ibfk_1` FOREIGN KEY (`periode_id`) REFERENCES `periode` (`id`) ON DELETE CASCADE;

        ALTER TABLE `kehadiran_musyawarah`
            ADD CONSTRAINT `kehadiran_musyawarah_ibfk_1` FOREIGN KEY (`id_musyawarah`) REFERENCES `musyawarah` (`id`) ON DELETE CASCADE;

        ALTER TABLE `kurikulum_hafalan`
            ADD CONSTRAINT `kurikulum_hafalan_ibfk_1` FOREIGN KEY (`materi_id`) REFERENCES `materi_hafalan` (`id`) ON DELETE CASCADE;

        ALTER TABLE `materi_file`
            ADD CONSTRAINT `materi_file_ibfk_1` FOREIGN KEY (`poin_id`) REFERENCES `materi_poin` (`id`) ON DELETE CASCADE;

        ALTER TABLE `materi_induk`
            ADD CONSTRAINT `materi_induk_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `materi_kategori` (`id`) ON DELETE CASCADE;

        ALTER TABLE `materi_poin`
            ADD CONSTRAINT `fk_parent_poin` FOREIGN KEY (`parent_id`) REFERENCES `materi_poin` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `materi_poin_ibfk_1` FOREIGN KEY (`materi_induk_id`) REFERENCES `materi_induk` (`id`) ON DELETE CASCADE;

        ALTER TABLE `materi_video`
            ADD CONSTRAINT `fk_video_poin` FOREIGN KEY (`poin_id`) REFERENCES `materi_poin` (`id`) ON DELETE CASCADE;

        ALTER TABLE `musyawarah_laporan_kelompok`
            ADD CONSTRAINT `musyawarah_laporan_kelompok_ibfk_1` FOREIGN KEY (`id_musyawarah`) REFERENCES `musyawarah` (`id`) ON DELETE CASCADE;

        ALTER TABLE `musyawarah_laporan_kmm`
            ADD CONSTRAINT `musyawarah_laporan_kmm_ibfk_1` FOREIGN KEY (`id_musyawarah`) REFERENCES `musyawarah` (`id`) ON DELETE CASCADE;

        ALTER TABLE `notulensi_poin`
            ADD CONSTRAINT `notulensi_poin_ibfk_1` FOREIGN KEY (`id_musyawarah`) REFERENCES `musyawarah` (`id`) ON DELETE CASCADE;

        ALTER TABLE `pesan_terjadwal`
            ADD CONSTRAINT `fk_pesan_ke_jadwal` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_presensi` (`id`) ON DELETE CASCADE;

        ALTER TABLE `progres_hafalan`
            ADD CONSTRAINT `progres_hafalan_ibfk_1` FOREIGN KEY (`peserta_id`) REFERENCES `peserta` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `progres_hafalan_ibfk_2` FOREIGN KEY (`materi_id`) REFERENCES `materi_hafalan` (`id`) ON DELETE CASCADE;

        ALTER TABLE `rekap_presensi`
            ADD CONSTRAINT `rekap_presensi_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_presensi` (`id`) ON DELETE CASCADE;

        ";

        // 2. Jalankan SQL tersebut
        $this->execute($sql);
    }

    /**
     * Metode 'down()' dijalankan saat Anda 'rollback'.
     * Ini adalah kebalikan dari 'up()'.
     */
    public function down(): void
    {
        // Tulis perintah untuk menghapus semua tabel itu
        // (Ini hanya untuk jaga-jaga jika Anda perlu rollback)
        $this->execute("DROP TABLE `balasan_wa`");
        $this->execute("DROP TABLE `catatan_bk`");
        $this->execute("DROP TABLE `grup_whatsapp`");
        $this->execute("DROP TABLE `guru`");
        $this->execute("DROP TABLE `jadwal_guru`");
        $this->execute("DROP TABLE `jadwal_penasehat`");
        $this->execute("DROP TABLE `jadwal_presensi`");
        $this->execute("DROP TABLE `kehadiran_musyawarah`");
        $this->execute("DROP TABLE `kepengurusan`");
        $this->execute("DROP TABLE `kurikulum_hafalan`");
        $this->execute("DROP TABLE `laporan_harian`");
        $this->execute("DROP TABLE `laporan_mingguan`");
        $this->execute("DROP TABLE `log_pesan_wa`");
        $this->execute("DROP TABLE `materi_file`");
        $this->execute("DROP TABLE `materi_hafalan`");
        $this->execute("DROP TABLE `materi_induk`");
        $this->execute("DROP TABLE `materi_kategori`");
        $this->execute("DROP TABLE `materi_poin`");
        $this->execute("DROP TABLE `materi_video`");
        $this->execute("DROP TABLE `musyawarah`");
        $this->execute("DROP TABLE `musyawarah_laporan_kelompok`");
        $this->execute("DROP TABLE `musyawarah_laporan_kmm`");
        $this->execute("DROP TABLE `notulensi_poin`");
        $this->execute("DROP TABLE `penasehat`");
        $this->execute("DROP TABLE `pengaturan_pengingat`");
        $this->execute("DROP TABLE `pengumuman_template`");
        $this->execute("DROP TABLE `periode`");
        $this->execute("DROP TABLE `pesan_terjadwal`");
        $this->execute("DROP TABLE `peserta`");
        $this->execute("DROP TABLE `progres_hafalan`");
        $this->execute("DROP TABLE `rekap_presensi`");
        $this->execute("DROP TABLE `template_pesan`");
        $this->execute("DROP TABLE `users`");
    }
}
