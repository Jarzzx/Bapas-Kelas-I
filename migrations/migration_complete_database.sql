-- =====================================================
-- MIGRATION COMPLETE DATABASE BAPAS
-- File ini berisi struktur lengkap semua tabel database
-- =====================================================
-- Database: bapas_db
-- Tanggal: 2026-01-06
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =====================================================
-- 1. TABEL: pk_users
-- Tabel untuk Pembimbing Kemasyarakatan (PK)
-- =====================================================

CREATE TABLE IF NOT EXISTS `pk_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 2. TABEL: klien_users
-- Tabel untuk Klien Pemasyarakatan
-- =====================================================

CREATE TABLE IF NOT EXISTS `klien_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `no_registrasi` varchar(50) DEFAULT NULL,
  `alamat` text,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nik` varchar(16) DEFAULT NULL,
  `foto_klien` varchar(255) DEFAULT NULL,
  `riwayat_pendidikan` varchar(100) DEFAULT NULL,
  `agama` varchar(50) DEFAULT NULL,
  `status_pernikahan` enum('Belum Menikah','Menikah','Cerai Hidup','Cerai Mati') DEFAULT NULL,
  `pekerjaan` varchar(100) DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `biodata_lengkap` enum('belum','sudah') DEFAULT 'belum',
  `biodata_dilengkapi_at` timestamp NULL DEFAULT NULL,
  `pk_id` int DEFAULT NULL,
  `status_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `alamat_jalan` varchar(255) DEFAULT NULL COMMENT 'Nama jalan',
  `alamat_kelurahan` varchar(100) DEFAULT NULL COMMENT 'Kelurahan/Desa',
  `alamat_kecamatan` varchar(100) DEFAULT NULL COMMENT 'Kecamatan',
  `alamat_kota` varchar(100) DEFAULT NULL COMMENT 'Kota/Kabupaten',
  `alamat_provinsi` varchar(100) DEFAULT NULL COMMENT 'Provinsi',
  `alamat_kode_pos` varchar(10) DEFAULT NULL COMMENT 'Kode Pos',
  `alamat_negara` varchar(100) DEFAULT 'Indonesia' COMMENT 'Negara',
  `alamat_rt` varchar(10) DEFAULT NULL COMMENT 'RT',
  `alamat_rw` varchar(10) DEFAULT NULL COMMENT 'RW',
  `alamat_nomor` varchar(50) DEFAULT NULL COMMENT 'Nomor rumah/bangunan',
  `alamat_formatted` text COMMENT 'Alamat lengkap yang sudah diformat',
  `alamat_api_data` json DEFAULT NULL COMMENT 'Data mentah dari API geocoding',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `pk_id` (`pk_id`),
  KEY `klien_users_ibfk_2` (`approved_by`),
  CONSTRAINT `klien_users_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `klien_users_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `pk_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 3. TABEL: data_klien
-- Tabel untuk Data Klien yang dibimbing oleh PK
-- =====================================================

CREATE TABLE IF NOT EXISTS `data_klien` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pk_id` int NOT NULL,
  `nama` varchar(200) NOT NULL,
  `no_registrasi` varchar(50) NOT NULL,
  `alamat` text,
  `jenis_kasus` varchar(200) DEFAULT NULL,
  `pasal_pidana` text,
  `tanggal_mulai_bimbingan` date DEFAULT NULL,
  `tanggal_akhir_bimbingan` date DEFAULT NULL,
  `masa_bimbingan_tahun` int DEFAULT NULL,
  `masa_bimbingan_bulan` int DEFAULT NULL,
  `masa_bimbingan_hari` int DEFAULT NULL,
  `dokumen_lengkap` enum('belum','sudah') DEFAULT 'belum',
  `tanggal_mulai` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Aktif',
  `jenis_pembebasan` enum('PB','CMB','CMJB') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_registrasi` (`no_registrasi`),
  KEY `pk_id` (`pk_id`),
  CONSTRAINT `data_klien_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 4. TABEL: jadwal_bimbingan
-- Tabel untuk Jadwal Bimbingan yang dibuat oleh PK
-- =====================================================

CREATE TABLE IF NOT EXISTS `jadwal_bimbingan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pk_id` int NOT NULL,
  `klien_id` int NOT NULL,
  `data_klien_id` int DEFAULT NULL,
  `judul_bimbingan` varchar(200) NOT NULL,
  `materi_bimbingan` text NOT NULL,
  `tanggal_bimbingan` datetime NOT NULL,
  `lokasi_bimbingan` text,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('terjadwal','selesai','dibatalkan') DEFAULT 'terjadwal',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pk_id` (`pk_id`),
  KEY `klien_id` (`klien_id`),
  KEY `data_klien_id` (`data_klien_id`),
  CONSTRAINT `jadwal_bimbingan_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_bimbingan_ibfk_2` FOREIGN KEY (`klien_id`) REFERENCES `klien_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_bimbingan_ibfk_3` FOREIGN KEY (`data_klien_id`) REFERENCES `data_klien` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 5. TABEL: laporan_bimbingan
-- Tabel untuk Laporan Bimbingan dari Klien atau PK
-- =====================================================

CREATE TABLE IF NOT EXISTS `laporan_bimbingan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pk_id` int NOT NULL,
  `nama_pk` varchar(200) DEFAULT NULL,
  `klien_id` int NOT NULL,
  `jadwal_bimbingan_id` int DEFAULT NULL,
  `dibuat_oleh` enum('pk','klien') DEFAULT 'pk',
  `foto_bimbingan` varchar(255) DEFAULT NULL,
  `lokasi_laporan` text,
  `latitude_laporan` decimal(10,8) DEFAULT NULL,
  `longitude_laporan` decimal(11,8) DEFAULT NULL,
  `keluhan` text,
  `status_verifikasi` enum('pending','approved','rejected') DEFAULT 'pending',
  `verified_by` int DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `tanggal_laporan` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tanggal_bimbingan` date DEFAULT NULL,
  `jenis_bimbingan` varchar(100) DEFAULT NULL,
  `bentuk_pembimbingan` enum('tatap_muka','daring','kunjungan_rumah') DEFAULT NULL,
  `materi_bimbingan` text,
  `hasil_bimbingan` text,
  `tindak_lanjut` text,
  `isi_laporan` text NOT NULL,
  `hasil_evaluasi` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pk_id` (`pk_id`),
  KEY `klien_id` (`klien_id`),
  KEY `fk_jadwal_bimbingan` (`jadwal_bimbingan_id`),
  KEY `fk_verified_by` (`verified_by`),
  CONSTRAINT `fk_jadwal_bimbingan` FOREIGN KEY (`jadwal_bimbingan_id`) REFERENCES `jadwal_bimbingan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `pk_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `laporan_bimbingan_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `laporan_bimbingan_ibfk_2` FOREIGN KEY (`klien_id`) REFERENCES `data_klien` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 6. TABEL: laporan_pengawasan
-- Tabel untuk Laporan Pengawasan oleh PK
-- =====================================================

CREATE TABLE IF NOT EXISTS `laporan_pengawasan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pk_id` int NOT NULL,
  `klien_id` int NOT NULL,
  `tanggal_laporan` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `isi_laporan` text NOT NULL,
  `status_klien` varchar(100) DEFAULT NULL,
  `catatan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pk_id` (`pk_id`),
  KEY `klien_id` (`klien_id`),
  CONSTRAINT `laporan_pengawasan_ibfk_1` FOREIGN KEY (`pk_id`) REFERENCES `pk_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `laporan_pengawasan_ibfk_2` FOREIGN KEY (`klien_id`) REFERENCES `data_klien` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- END OF MIGRATION
-- =====================================================

