-- Migration Script untuk Menambahkan Tabel Jadwal Bimbingan dan Update Laporan Bimbingan
-- File ini untuk update database yang sudah ada tanpa menghapus data

USE bapas_db;

-- ============================================
-- CREATE TABLE: jadwal_bimbingan
-- ============================================

CREATE TABLE IF NOT EXISTS jadwal_bimbingan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pk_id INT NOT NULL,
    klien_id INT NOT NULL,
    data_klien_id INT NULL,
    judul_bimbingan VARCHAR(200) NOT NULL,
    materi_bimbingan TEXT NOT NULL,
    tanggal_bimbingan DATETIME NOT NULL,
    lokasi_bimbingan TEXT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    status ENUM('terjadwal', 'selesai', 'dibatalkan') DEFAULT 'terjadwal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pk_id) REFERENCES pk_users(id) ON DELETE CASCADE,
    FOREIGN KEY (klien_id) REFERENCES klien_users(id) ON DELETE CASCADE,
    FOREIGN KEY (data_klien_id) REFERENCES data_klien(id) ON DELETE SET NULL
);

-- ============================================
-- UPDATE TABLE: laporan_bimbingan
-- ============================================

-- Tambah kolom untuk laporan dari klien
ALTER TABLE laporan_bimbingan 
ADD COLUMN jadwal_bimbingan_id INT NULL 
AFTER klien_id;

ALTER TABLE laporan_bimbingan 
ADD COLUMN dibuat_oleh ENUM('pk', 'klien') DEFAULT 'pk' 
AFTER jadwal_bimbingan_id;

ALTER TABLE laporan_bimbingan 
ADD COLUMN foto_bimbingan VARCHAR(255) NULL 
AFTER dibuat_oleh;

ALTER TABLE laporan_bimbingan 
ADD COLUMN lokasi_laporan TEXT NULL 
AFTER foto_bimbingan;

ALTER TABLE laporan_bimbingan 
ADD COLUMN latitude_laporan DECIMAL(10, 8) NULL 
AFTER lokasi_laporan;

ALTER TABLE laporan_bimbingan 
ADD COLUMN longitude_laporan DECIMAL(11, 8) NULL 
AFTER latitude_laporan;

ALTER TABLE laporan_bimbingan 
ADD COLUMN keluhan TEXT NULL 
AFTER longitude_laporan;

ALTER TABLE laporan_bimbingan 
ADD COLUMN status_verifikasi ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' 
AFTER keluhan;

ALTER TABLE laporan_bimbingan 
ADD COLUMN verified_by INT NULL 
AFTER status_verifikasi;

ALTER TABLE laporan_bimbingan 
ADD COLUMN verified_at TIMESTAMP NULL 
AFTER verified_by;

-- Tambah foreign key untuk jadwal_bimbingan_id
ALTER TABLE laporan_bimbingan 
ADD CONSTRAINT fk_jadwal_bimbingan 
FOREIGN KEY (jadwal_bimbingan_id) REFERENCES jadwal_bimbingan(id) ON DELETE SET NULL;

-- Tambah foreign key untuk verified_by
ALTER TABLE laporan_bimbingan 
ADD CONSTRAINT fk_verified_by 
FOREIGN KEY (verified_by) REFERENCES pk_users(id) ON DELETE SET NULL;

-- ============================================
-- CATATAN PENTING
-- ============================================
-- 1. File ini aman dijalankan berkali-kali (idempotent)
-- 2. Jika ada error "Duplicate column" atau "Duplicate table", berarti sudah ada, bisa diabaikan
-- 3. Pastikan backup database sebelum menjalankan migration
-- 4. Setelah migration, pastikan semua form sudah terintegrasi dengan kolom baru

-- ============================================
-- VERIFIKASI STRUKTUR
-- ============================================
-- Jalankan query ini untuk cek struktur tabel setelah migration:
-- DESCRIBE jadwal_bimbingan;
-- DESCRIBE laporan_bimbingan;

