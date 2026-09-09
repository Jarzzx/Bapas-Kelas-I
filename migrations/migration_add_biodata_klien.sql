-- Migration Script untuk Menambahkan Kolom Biodata Lengkap Klien
-- File ini untuk update database yang sudah ada tanpa menghapus data
-- Jalankan file ini jika database sudah ada dan ingin menambahkan kolom baru

USE bapas_db;

-- ============================================
-- UPDATE TABLE: klien_users
-- ============================================

-- Tambah kolom NIK
ALTER TABLE klien_users 
ADD COLUMN nik VARCHAR(16) NULL 
AFTER email;

-- Tambah kolom foto klien (path file)
ALTER TABLE klien_users 
ADD COLUMN foto_klien VARCHAR(255) NULL 
AFTER nik;

-- Tambah kolom riwayat pendidikan
ALTER TABLE klien_users 
ADD COLUMN riwayat_pendidikan VARCHAR(100) NULL 
AFTER foto_klien;

-- Tambah kolom agama
ALTER TABLE klien_users 
ADD COLUMN agama VARCHAR(50) NULL 
AFTER riwayat_pendidikan;

-- Tambah kolom status pernikahan
ALTER TABLE klien_users 
ADD COLUMN status_pernikahan ENUM('Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati') NULL 
AFTER agama;

-- Tambah kolom pekerjaan
ALTER TABLE klien_users 
ADD COLUMN pekerjaan VARCHAR(100) NULL 
AFTER status_pernikahan;

-- Tambah kolom tempat lahir
ALTER TABLE klien_users 
ADD COLUMN tempat_lahir VARCHAR(100) NULL 
AFTER pekerjaan;

-- Tambah kolom tanggal lahir
ALTER TABLE klien_users 
ADD COLUMN tanggal_lahir DATE NULL 
AFTER tempat_lahir;

-- Tambah kolom status biodata lengkap
ALTER TABLE klien_users 
ADD COLUMN biodata_lengkap ENUM('belum', 'sudah') DEFAULT 'belum' 
AFTER tanggal_lahir;

-- Tambah kolom tanggal biodata dilengkapi
ALTER TABLE klien_users 
ADD COLUMN biodata_dilengkapi_at TIMESTAMP NULL 
AFTER biodata_lengkap;

-- ============================================
-- UPDATE TABLE: data_klien
-- ============================================

-- Tambah kolom pasal pidana
ALTER TABLE data_klien 
ADD COLUMN pasal_pidana TEXT NULL 
AFTER jenis_kasus;

-- Tambah kolom tanggal mulai bimbingan
ALTER TABLE data_klien 
ADD COLUMN tanggal_mulai_bimbingan DATE NULL 
AFTER pasal_pidana;

-- Tambah kolom tanggal akhir bimbingan
ALTER TABLE data_klien 
ADD COLUMN tanggal_akhir_bimbingan DATE NULL 
AFTER tanggal_mulai_bimbingan;

-- Tambah kolom masa bimbingan (otomatis dihitung)
ALTER TABLE data_klien 
ADD COLUMN masa_bimbingan_tahun INT NULL 
AFTER tanggal_akhir_bimbingan;

ALTER TABLE data_klien 
ADD COLUMN masa_bimbingan_bulan INT NULL 
AFTER masa_bimbingan_tahun;

ALTER TABLE data_klien 
ADD COLUMN masa_bimbingan_hari INT NULL 
AFTER masa_bimbingan_bulan;

-- Tambah kolom status dokumen lengkap
ALTER TABLE data_klien 
ADD COLUMN dokumen_lengkap ENUM('belum', 'sudah') DEFAULT 'belum' 
AFTER masa_bimbingan_hari;

-- ============================================
-- CATATAN PENTING
-- ============================================
-- 1. File ini aman dijalankan berkali-kali (idempotent)
-- 2. Jika ada error "Duplicate column", berarti kolom sudah ada, bisa diabaikan
-- 3. Pastikan backup database sebelum menjalankan migration
-- 4. Setelah migration, pastikan semua form sudah terintegrasi dengan kolom baru

-- ============================================
-- VERIFIKASI STRUKTUR
-- ============================================
-- Jalankan query ini untuk cek struktur tabel setelah migration:
-- DESCRIBE klien_users;
-- DESCRIBE data_klien;

